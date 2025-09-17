#!/bin/bash

# S3 Storage API - cURL Examples
# Make sure your server is running and update the BASE_URL accordingly

BASE_URL="http://localhost:8000"
API_KEY="your-secret-api-key"  # Optional: set if you have API_KEY in .env

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}S3 Storage API - cURL Examples${NC}"
echo "=================================="

# Function to make API calls with optional API key
api_call() {
    local method=$1
    local endpoint=$2
    local data=$3
    local content_type=$4
    
    if [ -n "$API_KEY" ]; then
        if [ -n "$content_type" ]; then
            curl -s -X "$method" \
                -H "X-API-Key: $API_KEY" \
                -H "Content-Type: $content_type" \
                -d "$data" \
                "$BASE_URL/$endpoint"
        else
            curl -s -X "$method" \
                -H "X-API-Key: $API_KEY" \
                "$BASE_URL/$endpoint" \
                $data
        fi
    else
        if [ -n "$content_type" ]; then
            curl -s -X "$method" \
                -H "Content-Type: $content_type" \
                -d "$data" \
                "$BASE_URL/$endpoint"
        else
            curl -s -X "$method" \
                "$BASE_URL/$endpoint" \
                $data
        fi
    fi
}

echo -e "\n${YELLOW}1. Get API Information${NC}"
echo "Command: curl -X GET $BASE_URL/api.php"
api_call "GET" "api.php" | jq '.' 2>/dev/null || echo "Response received (install jq for formatted JSON)"

echo -e "\n${YELLOW}2. Upload a File (Private)${NC}"
echo "Creating test file..."
echo "Hello, World! This is a test file." > test-file.txt

if [ -n "$API_KEY" ]; then
    echo "Command: curl -X POST -H \"X-API-Key: $API_KEY\" -F \"file=@test-file.txt\" -F \"key=uploads/test.txt\" -F \"access_type=private\" $BASE_URL/api.php"
    curl -s -X POST \
        -H "X-API-Key: $API_KEY" \
        -F "file=@test-file.txt" \
        -F "key=uploads/test.txt" \
        -F "access_type=private" \
        "$BASE_URL/api.php" | jq '.' 2>/dev/null || echo "Response received"
else
    echo "Command: curl -X POST -F \"file=@test-file.txt\" -F \"key=uploads/test.txt\" -F \"access_type=private\" $BASE_URL/api.php"
    curl -s -X POST \
        -F "file=@test-file.txt" \
        -F "key=uploads/test.txt" \
        -F "access_type=private" \
        "$BASE_URL/api.php" | jq '.' 2>/dev/null || echo "Response received"
fi

echo -e "\n${YELLOW}3. Upload a File (Public Read)${NC}"
echo "Command: curl -X POST -F \"file=@test-file.txt\" -F \"key=public/readme.txt\" -F \"access_type=public-read\" $BASE_URL/api.php"
api_call "POST" "api.php" "-F \"file=@test-file.txt\" -F \"key=public/readme.txt\" -F \"access_type=public-read\""

echo -e "\n${YELLOW}4. Upload Content Directly${NC}"
content_data='{
    "key": "content/hello.json",
    "content": "{\"message\": \"Hello from API!\", \"timestamp\": \"'$(date -Iseconds)'\"}",
    "access_type": "public-read",
    "content_type": "application/json"
}'

echo "Command: curl -X PUT -H \"Content-Type: application/json\" -d '$content_data' $BASE_URL/api.php"
api_call "PUT" "api.php" "$content_data" "application/json" | jq '.' 2>/dev/null || echo "Response received"

echo -e "\n${YELLOW}5. List All Files${NC}"
echo "Command: curl -X GET $BASE_URL/api.php/files"
api_call "GET" "api.php/files" | jq '.' 2>/dev/null || echo "Response received"

echo -e "\n${YELLOW}6. List Files with Prefix${NC}"
echo "Command: curl -X GET \"$BASE_URL/api.php/files?prefix=uploads/\""
api_call "GET" "api.php/files?prefix=uploads/" | jq '.' 2>/dev/null || echo "Response received"

echo -e "\n${YELLOW}7. Change File Access Level${NC}"
access_data='{
    "key": "uploads/test.txt",
    "access_type": "public-read"
}'

echo "Command: curl -X PUT -H \"Content-Type: application/json\" -d '$access_data' $BASE_URL/api.php/access"
api_call "PUT" "api.php/access" "$access_data" "application/json" | jq '.' 2>/dev/null || echo "Response received"

echo -e "\n${YELLOW}8. Upload with Metadata${NC}"
echo "Creating another test file..."
echo "This file has custom metadata." > test-with-metadata.txt

if [ -n "$API_KEY" ]; then
    curl -s -X POST \
        -H "X-API-Key: $API_KEY" \
        -F "file=@test-with-metadata.txt" \
        -F "key=uploads/metadata-test.txt" \
        -F "access_type=private" \
        -F "metadata={\"author\":\"curl-script\",\"category\":\"test\",\"version\":\"1.0\"}" \
        "$BASE_URL/api.php" | jq '.' 2>/dev/null || echo "Response received"
else
    curl -s -X POST \
        -F "file=@test-with-metadata.txt" \
        -F "key=uploads/metadata-test.txt" \
        -F "access_type=private" \
        -F "metadata={\"author\":\"curl-script\",\"category\":\"test\",\"version\":\"1.0\"}" \
        "$BASE_URL/api.php" | jq '.' 2>/dev/null || echo "Response received"
fi

echo -e "\n${YELLOW}9. Delete a File${NC}"
delete_data='{
    "key": "uploads/metadata-test.txt"
}'

echo "Command: curl -X DELETE -H \"Content-Type: application/json\" -d '$delete_data' $BASE_URL/api.php"
api_call "DELETE" "api.php" "$delete_data" "application/json" | jq '.' 2>/dev/null || echo "Response received"

echo -e "\n${YELLOW}10. Upload Large File Example${NC}"
echo "Creating a larger test file..."
for i in {1..100}; do
    echo "Line $i: This is a test line with some content to make the file larger." >> large-test-file.txt
done

echo "Command: curl -X POST -F \"file=@large-test-file.txt\" -F \"key=uploads/large-file.txt\" -F \"access_type=private\" $BASE_URL/api.php"
api_call "POST" "api.php" "-F \"file=@large-test-file.txt\" -F \"key=uploads/large-file.txt\" -F \"access_type=private\""

# Cleanup
echo -e "\n${GREEN}Cleaning up test files...${NC}"
rm -f test-file.txt test-with-metadata.txt large-test-file.txt

echo -e "\n${GREEN}Examples completed!${NC}"
echo -e "\n${YELLOW}Tips:${NC}"
echo "- Set API_KEY variable at the top of this script if you have API authentication enabled"
echo "- Install 'jq' for formatted JSON output: sudo apt install jq"
echo "- Check your server logs for any errors"
echo "- Use the web interface at $BASE_URL to verify uploads"