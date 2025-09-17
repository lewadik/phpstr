@echo off
REM S3 Storage API - cURL Examples for Windows
REM Make sure your server is running and update the BASE_URL accordingly

set BASE_URL=http://localhost:8000
set API_KEY=your-secret-api-key

echo S3 Storage API - cURL Examples
echo ==================================

echo.
echo 1. Get API Information
echo Command: curl -X GET %BASE_URL%/api.php
curl -s -X GET "%BASE_URL%/api.php"

echo.
echo.
echo 2. Upload a File (Private)
echo Creating test file...
echo Hello, World! This is a test file. > test-file.txt

if defined API_KEY (
    echo Command: curl -X POST -H "X-API-Key: %API_KEY%" -F "file=@test-file.txt" -F "key=uploads/test.txt" -F "access_type=private" %BASE_URL%/api.php
    curl -s -X POST -H "X-API-Key: %API_KEY%" -F "file=@test-file.txt" -F "key=uploads/test.txt" -F "access_type=private" "%BASE_URL%/api.php"
) else (
    echo Command: curl -X POST -F "file=@test-file.txt" -F "key=uploads/test.txt" -F "access_type=private" %BASE_URL%/api.php
    curl -s -X POST -F "file=@test-file.txt" -F "key=uploads/test.txt" -F "access_type=private" "%BASE_URL%/api.php"
)

echo.
echo.
echo 3. Upload a File (Public Read)
echo Command: curl -X POST -F "file=@test-file.txt" -F "key=public/readme.txt" -F "access_type=public-read" %BASE_URL%/api.php
curl -s -X POST -F "file=@test-file.txt" -F "key=public/readme.txt" -F "access_type=public-read" "%BASE_URL%/api.php"

echo.
echo.
echo 4. Upload Content Directly
set content_data={"key": "content/hello.json", "content": "{\"message\": \"Hello from API!\", \"timestamp\": \"%date% %time%\"}", "access_type": "public-read", "content_type": "application/json"}

echo Command: curl -X PUT -H "Content-Type: application/json" -d "%content_data%" %BASE_URL%/api.php
curl -s -X PUT -H "Content-Type: application/json" -d "%content_data%" "%BASE_URL%/api.php"

echo.
echo.
echo 5. List All Files
echo Command: curl -X GET %BASE_URL%/api.php/files
curl -s -X GET "%BASE_URL%/api.php/files"

echo.
echo.
echo 6. List Files with Prefix
echo Command: curl -X GET "%BASE_URL%/api.php/files?prefix=uploads/"
curl -s -X GET "%BASE_URL%/api.php/files?prefix=uploads/"

echo.
echo.
echo 7. Change File Access Level
set access_data={"key": "uploads/test.txt", "access_type": "public-read"}

echo Command: curl -X PUT -H "Content-Type: application/json" -d "%access_data%" %BASE_URL%/api.php/access
curl -s -X PUT -H "Content-Type: application/json" -d "%access_data%" "%BASE_URL%/api.php/access"

echo.
echo.
echo 8. Upload with Metadata
echo Creating another test file...
echo This file has custom metadata. > test-with-metadata.txt

set metadata_json={"author":"curl-script","category":"test","version":"1.0"}

if defined API_KEY (
    curl -s -X POST -H "X-API-Key: %API_KEY%" -F "file=@test-with-metadata.txt" -F "key=uploads/metadata-test.txt" -F "access_type=private" -F "metadata=%metadata_json%" "%BASE_URL%/api.php"
) else (
    curl -s -X POST -F "file=@test-with-metadata.txt" -F "key=uploads/metadata-test.txt" -F "access_type=private" -F "metadata=%metadata_json%" "%BASE_URL%/api.php"
)

echo.
echo.
echo 9. Delete a File
set delete_data={"key": "uploads/metadata-test.txt"}

echo Command: curl -X DELETE -H "Content-Type: application/json" -d "%delete_data%" %BASE_URL%/api.php
curl -s -X DELETE -H "Content-Type: application/json" -d "%delete_data%" "%BASE_URL%/api.php"

echo.
echo.
echo Cleaning up test files...
del test-file.txt test-with-metadata.txt 2>nul

echo.
echo Examples completed!
echo.
echo Tips:
echo - Set API_KEY variable at the top of this script if you have API authentication enabled
echo - Check your server logs for any errors
echo - Use the web interface at %BASE_URL% to verify uploads

pause