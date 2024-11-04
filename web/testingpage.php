<!DOCTYPE html>
<html>
<head>
    <title>Employee Attendance</title>
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table, th, td {
            border: 1px solid black;
        }
        th, td {
            padding: 10px;
            text-align: left;
        }
    </style>
</head>
<body>

<h1>Employee Attendance Records</h1>

<?php
// Database connection setup
$servername = "localhost";
$username = "root";
$password = "Rootpass123!@#";
$dbname = "central_db";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Query to fetch employee data with attendance
$sql = "
    SELECT e.EmployeeID, e.FirstName, e.LastName, e.Email, e.Picture, ea.TimeIN, ea.TimeOUT
    FROM Employees e
    LEFT JOIN EmployeeAttendance ea ON e.EmployeeID = ea.EmployeeID
    ORDER BY ea.TimeIN DESC
    LIMIT 500";

$result = $conn->query($sql);

if ($result->num_rows > 0) {
    echo "<table>
            <tr>
                <th>Employee ID</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th>Time In</th>
                <th>Time Out</th>
            </tr>";

    while($row = $result->fetch_assoc()) {
        echo "<tr>
                <td>" . $row['EmployeeID'] . "</td>
                <td>" . $row['FirstName'] . "</td>
                <td>" . $row['LastName'] . "</td>
                <td>" . $row['Email'] . "</td>
                <td>" . $row['TimeIN'] . "</td>
                <td>" . $row['TimeOUT'] . "</td>
              </tr>";
    }

    echo "</table>";
} else {
    echo "No records found.";
}

$conn->close();
?>

</body>
</html>

