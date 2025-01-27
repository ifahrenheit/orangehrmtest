<?php
/**
 * OrangeHRM is a comprehensive Human Resource Management (HRM) System that captures
 * all the essential functionalities required for any enterprise.
 * Copyright (C) 2006 OrangeHRM Inc., http://www.orangehrm.com
 *
 * OrangeHRM is free software: you can redistribute it and/or modify it under the terms of
 * the GNU General Public License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * OrangeHRM is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY;
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with OrangeHRM.
 * If not, see <https://www.gnu.org/licenses/>.
 */

namespace OrangeHRM\Leave\Service;

use DateTime;
use Exception;
use OrangeHRM\Core\Traits\Auth\AuthUserTrait;
use OrangeHRM\Entity\Employee;
use OrangeHRM\Entity\Leave;
use OrangeHRM\Entity\LeaveRequest;
use OrangeHRM\Entity\LeaveRequestComment;
use OrangeHRM\Entity\WorkflowStateMachine;
use OrangeHRM\Leave\Dto\LeaveParameterObject;
use OrangeHRM\Leave\Event\LeaveApply;
use OrangeHRM\Leave\Event\LeaveEvent;
use OrangeHRM\Leave\Exception\LeaveAllocationServiceException;
use OrangeHRM\Leave\Traits\Service\LeaveEntitlementServiceTrait;
use OrangeHRM\Leave\Traits\Service\LeaveRequestServiceTrait;

class LeaveApplicationService extends AbstractLeaveAllocationService
{
    use LeaveEntitlementServiceTrait;
    use LeaveRequestServiceTrait;
    use AuthUserTrait;

    protected ?WorkflowStateMachine $applyWorkflowItem = null;

    /**
     * Creates a new leave application
     *
     * @param LeaveParameterObject $leaveAssignmentData
     * @return LeaveRequest|null
     * @throws LeaveAllocationServiceException
     */
    public function applyLeave(LeaveParameterObject $leaveAssignmentData): ?LeaveRequest
    {
        $maxAllowedLeavePeriodEndDate = $this->getLeavePeriodService()->getMaxAllowedLeavePeriodEndDate();
        if ($leaveAssignmentData->getToDate() > $maxAllowedLeavePeriodEndDate) {
            throw LeaveAllocationServiceException::cannotApplyLeaveBeyondMaxAllowedLeavePeriodEndDate(
                $this->getDateTimeHelper()->formatDateTimeToYmd($maxAllowedLeavePeriodEndDate)
            );
        }
        if ($this->hasOverlapLeaves($leaveAssignmentData)) {
            throw LeaveAllocationServiceException::overlappingLeavesFound();
        }

        if ($this->isWorkShiftLengthExceeded($leaveAssignmentData)) {
            throw LeaveAllocationServiceException::workShiftLengthExceeded();
        }

        return $this->saveLeaveRequest($leaveAssignmentData);
    }

    /**
     * Saves Leave Request and Sends Email Notification
     *
     * @param LeaveParameterObject $leaveAssignmentData
     * @return LeaveRequest|null True if leave request is saved else false
     * @throws LeaveAllocationServiceException
     *
     * @todo Don't catch general Exception. Catch specific one.
     */
    protected function saveLeaveRequest(LeaveParameterObject $leaveAssignmentData): ?LeaveRequest
    {
//............................................................................
	    


// Display all PHP errors for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Get today's date
$today = new DateTime();
$today->setTime(0, 0); // Set time to 00:00:00 to avoid time differences

// Ensure the date is passed as a string
$leaveStartDateString = $leaveAssignmentData->getFromDate();

if ($leaveStartDateString instanceof DateTime) {
    $leaveStartDateString = $leaveStartDateString->format('Y-m-d');
}

// Get the requested leave start date
$leaveStartDate = new DateTime($leaveStartDateString);
$leaveStartDate->setTime(0, 0); // Set time to 00:00:00 to avoid time differences

// Calculate the difference in days
$interval = $today->diff($leaveStartDate);
$daysDifference = $interval->days;

// Retrieve the leave type ID from $leaveAssignmentData
$leaveTypeId = $leaveAssignmentData->getLeaveType();

// Fetch the leave type details using the existing method
$leaveTypeDetails = $this->getLeaveTypeService()->getLeaveTypeDao()->getLeaveTypeById($leaveTypeId);

// Extract the leave type name
$leaveType = $leaveTypeDetails ? $leaveTypeDetails->getName() : null;

if (!$leaveType) {
    throw new LeaveAllocationServiceException('Unable to determine leave type.');
}

// Define a list of leave types that are exempt from the 7-day advance rule
$exemptLeaveTypes = ['Sick Leave', 'Maternity Leave'];

// Debugging statement to check $leaveType
error_log("Leave Type: " . print_r($leaveType, true)); // Log the leave type to PHP error log

// Check if the requested leave start date is less than 7 days from today
if (!in_array($leaveType, $exemptLeaveTypes) && $daysDifference < 7) {
    throw new LeaveAllocationServiceException('Leave requests must be made at least 7 days in advance.');
}

// Existing code to save leave request
$this->getLeaveRequestService()->getLeaveRequestDao($leaveAssignmentData);




//.............................................................................


	    $leaveRequest = $this->generateLeaveRequest($leaveAssignmentData);
        $leaveType = $this->getLeaveTypeService()->getLeaveTypeDao()->getLeaveTypeById(
            $leaveAssignmentData->getLeaveType()
        );
        $leaves = $this->createLeaveObjectListForAppliedRange($leaveAssignmentData);

        if ($this->isEmployeeAllowedToApply($leaveType)) {
            $nonHolidayLeaveDays = [];

            $holidayCount = 0;
            $holidays = [Leave::LEAVE_STATUS_LEAVE_WEEKEND, Leave::LEAVE_STATUS_LEAVE_HOLIDAY];
            foreach ($leaves as $k => $leave) {
                if (in_array($leave->getStatus(), $holidays)) {
                    $holidayCount++;
                } else {
                    $nonHolidayLeaveDays[] = $leave;
                }
            }

            if (count($nonHolidayLeaveDays) > 0) {
                $strategy = $this->getLeaveEntitlementService()->getLeaveEntitlementStrategy();
                $empNumber = $this->getAuthUser()->getEmpNumber();
                $entitlements = $strategy->handleLeaveCreate(
                    $empNumber,
                    $leaveType->getId(),
                    $nonHolidayLeaveDays,
                    false
                );

                if (!$this->allowToExceedLeaveBalance() && $entitlements == null) {
                    throw LeaveAllocationServiceException::leaveBalanceExceeded();
                }
            }

            if ($holidayCount != count($leaves)) {
                try {
                    $loggedInUserId = $this->getAuthUser()->getUserId();
                    $loggedInEmpNumber = $this->getAuthUser()->getEmpNumber();

                    $leaveRequest = $this->getLeaveRequestService()
                        ->getLeaveRequestDao()
                        ->saveLeaveRequest($leaveRequest, $leaves, $entitlements);

                    if (!empty($leaveAssignmentData->getComment())) {
                        $leaveRequestComment = new LeaveRequestComment();
                        $leaveRequestComment->setLeaveRequest($leaveRequest);
                        $leaveRequestComment->getDecorator()->setCreatedByUserById($loggedInUserId);
                        $leaveRequestComment->getDecorator()->setCreatedByEmployeeByEmpNumber($loggedInEmpNumber);
                        $leaveRequestComment->setComment($leaveAssignmentData->getComment());
                        $this->getLeaveRequestService()
                            ->getLeaveRequestDao()
                            ->saveLeaveRequestComment($leaveRequestComment);
                    }

                    $workFlowItem = $this->getWorkflowItemForApplyAction($leaveAssignmentData);
                    $this->getEventDispatcher()->dispatch(
                        new LeaveApply($leaveRequest, $workFlowItem, $this->getUserRoleManager()->getUser()),
                        LeaveEvent::APPLY
                    );

                    return $leaveRequest;
                } catch (Exception $e) {
                    $this->getLogger()->error('Exception while saving leave:' . $e->getMessage());
                    throw LeaveAllocationServiceException::leaveQuotaWillExceed();
                }
            } else {
                throw LeaveAllocationServiceException::noWorkingDaysSelected();
            }
        }

        return null;
    }

    /**
     * Returns leave status based on weekend and holiday
     *
     * If weekend, returns Leave::LEAVE_STATUS_LEAVE_WEEKEND
     * If holiday, returns Leave::LEAVE_STATUS_LEAVE_HOLIDAY
     * Else, returns Leave::LEAVE_STATUS_LEAVE_PENDING_APPROVAL
     *
     * @inheritDoc
     */
    public function getLeaveRequestStatus(
        bool $isWeekend,
        bool $isHoliday,
        DateTime $leaveDate,
        LeaveParameterObject $leaveAssignmentData
    ): int {
        $status = null;

        if ($isWeekend) {
            $status = Leave::LEAVE_STATUS_LEAVE_WEEKEND;
        }

        if ($isHoliday) {
            $status = Leave::LEAVE_STATUS_LEAVE_HOLIDAY;
        }

        if (is_null($status)) {
            $workFlowItem = $this->getWorkflowItemForApplyAction($leaveAssignmentData);
            $status = Leave::LEAVE_STATUS_LEAVE_PENDING_APPROVAL;
            if ($workFlowItem instanceof WorkflowStateMachine) {
                $status = $this->getLeaveRequestService()->getLeaveStatusByName($workFlowItem->getResultingState());
            }
        }

        return $status;
    }

    /**
     * @inheritDoc
     */
    protected function allowToExceedLeaveBalance(): bool
    {
        return false;
    }

    /**
     * @param LeaveParameterObject $leaveAssignmentData
     * @return WorkflowStateMachine|null
     */
    protected function getWorkflowItemForApplyAction(LeaveParameterObject $leaveAssignmentData): ?WorkflowStateMachine
    {
        if (is_null($this->applyWorkflowItem)) {
            $empNumber = $leaveAssignmentData->getEmployeeNumber();
            $workFlowItems = $this->getUserRoleManager()
                ->getAllowedActions(
                    WorkflowStateMachine::FLOW_LEAVE,
                    'INITIAL',
                    [],
                    [],
                    [Employee::class => $empNumber]
                );

            // get apply action
            foreach ($workFlowItems as $item) {
                if ($item->getAction() == 'APPLY') {
                    $this->applyWorkflowItem = $item;
                    break;
                }
            }
        }

        if (is_null($this->applyWorkflowItem)) {
            $this->getLogger()->error('No workflow item found for APPLY leave action!');
        }

        return $this->applyWorkflowItem;
    }
}
