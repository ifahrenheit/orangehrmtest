#!/usr/bin/env python3

import os
import pandas as pd
import urllib.parse
from google.oauth2 import service_account
from googleapiclient.discovery import build
from googleapiclient.http import MediaIoBaseDownload
from sqlalchemy import create_engine

# Step 1: Authenticate and connect to Google Drive
SERVICE_ACCOUNT_FILE = '/var/www/html/orangehrm/json/credentials.json'
SCOPES = ['https://www.googleapis.com/auth/drive']

credentials = service_account.Credentials.from_service_account_file(
    SERVICE_ACCOUNT_FILE, scopes=SCOPES)

drive_service = build('drive', 'v3', credentials=credentials)

def download_csv_from_drive(file_id, destination):
    request = drive_service.files().get_media(fileId=file_id)  # Change this line back to get_media
    with open(destination, 'wb') as file:
        downloader = MediaIoBaseDownload(file, request)
        done = False
        while not done:
            status, done = downloader.next_chunk()
            print(f"Download {int(status.progress() * 100)}%.")


# Step 2: Connect to MySQL (or another SQL database)
def connect_to_sql():
    username = 'root'
    password = urllib.parse.quote_plus('Rootpass123!@#')
    host = '127.0.0.1'
    database = 'central_db'
    db_connection_str = f'mysql+mysqlconnector://{username}:{password}@{host}/{database}'
    return create_engine(db_connection_str)

# Step 3: Read CSV and upload to SQL
def upload_csv_to_sql(csv_path, table_name, engine):
    df = pd.read_csv(csv_path)
    df.to_sql(table_name, con=engine, if_exists='replace', index=False)
    print(f"Uploaded to table {table_name}")

# Main workflow
def main():
    file_id = '1_Nbn-rMyjYQPzi0UqTkPmTie4JeHu4uE'  # Replace with your file ID from Google Drive
    destination = '/var/www/html/orangehrm/json/tempfile.csv'

    # Download CSV from Google Drive
    download_csv_from_drive(file_id, destination)

    # Connect to SQL
    engine = connect_to_sql()

    # Upload CSV data to SQL
    upload_csv_to_sql(destination, 'Test', engine)

    # Clean up temporary file
    os.remove(destination)

if __name__ == '__main__':
    main()

