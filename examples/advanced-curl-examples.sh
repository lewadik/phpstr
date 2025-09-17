#!/bin/bash

# Advanced S3 Storage API - cURL Examples
# This script demonstrates advanced usage patterns and real-world scenarios

BASE_URL="${S3_API_BASE_URL:-http://localhost:8000}"
API_KEY="${S3_API_KEY:-}"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Helper function for API calls
api_call() {
    local method=$1
    local endpoint=$2
    local data=$3
    local content_type=$4
    
    local curl_cmd="curl -s -X $method"
    
    if [ -n "$API_KEY" ]; then
        curl_cmd="$curl_cmd -H \"X-API-Key: $API_KEY\""
    fi
    
    if [ -n "$content_type" ]; then
        curl_cmd="$curl_cmd -H \"Content-Type: $content_type\""
    fi
    
    if [ -n "$data" ]; then
        if [ "$content_type" = "application/json" ]; then
            curl_cmd="$curl_cmd -d '$data'"
        else
            curl_cmd="$curl_cmd $data"
        fi
    fi
    
    eval "$curl_cmd \"$BASE_URL/$endpoint\""
}

# Pretty print JSON
pretty_json() {
    if command -v jq >/dev/null 2>&1; then
        jq '.'
    else
        python -m json.tool 2>/dev/null || cat
    fi
}

echo -e "${GREEN}Advanced S3 Storage API Examples${NC}"
echo "=================================="
echo -e "Base URL: ${BLUE}$BASE_URL${NC}"
echo -e "API Key: ${API_KEY:+${GREEN}[SET]${NC}}${API_KEY:-${RED}[NOT SET]${NC}}"
echo ""

# Example 1: Website Deployment Workflow
echo -e "${YELLOW}Example 1: Website Deployment Workflow${NC}"
echo "--------------------------------------"

# Create sample website files
mkdir -p temp_website/{css,js,images}
cat > temp_website/index.html << 'EOF'
<!DOCTYPE html>
<html>
<head>
    <title>My Website</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <h1>Welcome to My Website</h1>
    <script src="js/app.js"></script>
</body>
</html>
EOF

cat > temp_website/css/style.css << 'EOF'
body { font-family: Arial, sans-serif; margin: 40px; }
h1 { color: #333; }
EOF

cat > temp_website/js/app.js << 'EOF'
console.log('Website loaded successfully!');
EOF

echo "Sample website created. Uploading files..."

# Upload HTML files as public-read
find temp_website -name "*.html" | while read file; do
    key="website/$(basename "$file")"
    echo -e "${BLUE}Uploading HTML: $file -> $key${NC}"
    api_call "POST" "api.php" "-F \"file=@$file\" -F \"key=$key\" -F \"access_type=public-read\"" | pretty_json
done

# Upload CSS files as public-read
find temp_website -name "*.css" | while read file; do
    key="website/css/$(basename "$file")"
    echo -e "${BLUE}Uploading CSS: $file -> $key${NC}"
    api_call "POST" "api.php" "-F \"file=@$file\" -F \"key=$key\" -F \"access_type=public-read\"" | pretty_json
done

# Upload JS files as public-read
find temp_website -name "*.js" | while read file; do
    key="website/js/$(basename "$file")"
    echo -e "${BLUE}Uploading JS: $file -> $key${NC}"
    api_call "POST" "api.php" "-F \"file=@$file\" -F \"key=$key\" -F \"access_type=public-read\"" | pretty_json
done

echo ""

# Example 2: Configuration Management
echo -e "${YELLOW}Example 2: Configuration Management${NC}"
echo "-----------------------------------"

# Upload different environment configs
configs=("development" "staging" "production")
for env in "${configs[@]}"; do
    config_content="{\"environment\":\"$env\",\"debug\":$([ "$env" = "production" ] && echo "false" || echo "true"),\"api_url\":\"https://api-$env.example.com\"}"
    
    echo -e "${BLUE}Creating $env configuration...${NC}"
    api_call "PUT" "api.php" "{\"key\":\"configs/$env.json\",\"content\":\"$config_content\",\"access_type\":\"private\",\"content_type\":\"application/json\"}" "application/json" | pretty_json
done

echo ""

# Example 3: Document Management System
echo -e "${YELLOW}Example 3: Document Management System${NC}"
echo "------------------------------------"

# Create sample documents
echo "This is a public company policy document." > temp_policy.txt
echo "Confidential employee handbook content." > temp_handbook.txt
echo "Internal meeting notes - confidential." > temp_notes.txt

# Upload with different access levels and metadata
echo -e "${BLUE}Uploading policy document (public)...${NC}"
api_call "POST" "api.php" "-F \"file=@temp_policy.txt\" -F \"key=documents/policy.txt\" -F \"access_type=public-read\" -F \"metadata={\\\"category\\\":\\\"policy\\\",\\\"department\\\":\\\"hr\\\",\\\"version\\\":\\\"1.0\\\"}\"" | pretty_json

echo -e "${BLUE}Uploading employee handbook (private)...${NC}"
api_call "POST" "api.php" "-F \"file=@temp_handbook.txt\" -F \"key=documents/handbook.txt\" -F \"access_type=private\" -F \"metadata={\\\"category\\\":\\\"handbook\\\",\\\"department\\\":\\\"hr\\\",\\\"confidential\\\":true}\"" | pretty_json

echo -e "${BLUE}Uploading meeting notes (private)...${NC}"
api_call "POST" "api.php" "-F \"file=@temp_notes.txt\" -F \"key=documents/meetings/notes-$(date +%Y%m%d).txt\" -F \"access_type=private\" -F \"metadata={\\\"category\\\":\\\"meeting\\\",\\\"date\\\":\\\"$(date -I)\\\",\\\"confidential\\\":true}\"" | pretty_json

echo ""

# Example 4: Image Gallery Management
echo -e "${YELLOW}Example 4: Image Gallery Management${NC}"
echo "-----------------------------------"

# Create sample image placeholder (text file for demo)
echo "Sample image data - imagine this is a JPEG" > temp_image1.jpg
echo "Another sample image - imagine this is a PNG" > temp_image2.png

# Upload images with metadata
echo -e "${BLUE}Uploading gallery images...${NC}"
api_call "POST" "api.php" "-F \"file=@temp_image1.jpg\" -F \"key=gallery/nature/sunset.jpg\" -F \"access_type=public-read\" -F \"metadata={\\\"category\\\":\\\"nature\\\",\\\"tags\\\":[\\\"sunset\\\",\\\"landscape\\\"],\\\"photographer\\\":\\\"John Doe\\\"}\"" | pretty_json

api_call "POST" "api.php" "-F \"file=@temp_image2.png\" -F \"key=gallery/portraits/person1.png\" -F \"access_type=private\" -F \"metadata={\\\"category\\\":\\\"portrait\\\",\\\"tags\\\":[\\\"person\\\",\\\"professional\\\"],\\\"photographer\\\":\\\"Jane Smith\\\"}\"" | pretty_json

echo ""

# Example 5: API Data Storage
echo -e "${YELLOW}Example 5: API Data Storage${NC}"
echo "---------------------------"

# Store API responses or data
api_data='{"users":[{"id":1,"name":"John","email":"john@example.com"},{"id":2,"name":"Jane","email":"jane@example.com"}],"total":2,"page":1}'

echo -e "${BLUE}Storing API data...${NC}"
api_call "PUT" "api.php" "{\"key\":\"data/users.json\",\"content\":\"$api_data\",\"access_type\":\"private\",\"content_type\":\"application/json\"}" "application/json" | pretty_json

# Store CSV data
csv_data="id,name,email,department\n1,John Doe,john@example.com,Engineering\n2,Jane Smith,jane@example.com,Marketing"

echo -e "${BLUE}Storing CSV data...${NC}"
api_call "PUT" "api.php" "{\"key\":\"data/employees.csv\",\"content\":\"$csv_data\",\"access_type\":\"private\",\"content_type\":\"text/csv\"}" "application/json" | pretty_json

echo ""

# Example 6: Backup and Archive
echo -e "${YELLOW}Example 6: Backup and Archive${NC}"
echo "-----------------------------"

# Create a backup archive (simulated)
backup_content="Backup data from $(date)"
echo "$backup_content" > temp_backup.txt

echo -e "${BLUE}Creating backup archive...${NC}"
api_call "POST" "api.php" "-F \"file=@temp_backup.txt\" -F \"key=backups/$(date +%Y%m%d_%H%M%S)_backup.txt\" -F \"access_type=private\" -F \"metadata={\\\"type\\\":\\\"backup\\\",\\\"created\\\":\\\"$(date -I)\\\",\\\"size\\\":\\\"$(wc -c < temp_backup.txt)\\\"}\"" | pretty_json

echo ""

# Example 7: Content Delivery Network (CDN) Simulation
echo -e "${YELLOW}Example 7: CDN Asset Management${NC}"
echo "-------------------------------"

# Upload static assets
echo "/* Main stylesheet */" > temp_main.css
echo "console.log('Main app script');" > temp_app.js

echo -e "${BLUE}Uploading CDN assets...${NC}"
api_call "POST" "api.php" "-F \"file=@temp_main.css\" -F \"key=cdn/v1.0/css/main.css\" -F \"access_type=public-read\"" | pretty_json
api_call "POST" "api.php" "-F \"file=@temp_app.js\" -F \"key=cdn/v1.0/js/app.js\" -F \"access_type=public-read\"" | pretty_json

echo ""

# Example 8: File Management Operations
echo -e "${YELLOW}Example 8: File Management Operations${NC}"
echo "------------------------------------"

echo -e "${BLUE}Listing all files...${NC}"
api_call "GET" "api.php/files" | pretty_json

echo ""
echo -e "${BLUE}Listing files by category...${NC}"

# List website files
echo -e "${CYAN}Website files:${NC}"
api_call "GET" "api.php/files?prefix=website/" | pretty_json

echo ""
# List document files
echo -e "${CYAN}Document files:${NC}"
api_call "GET" "api.php/files?prefix=documents/" | pretty_json

echo ""

# Example 9: Access Level Management
echo -e "${YELLOW}Example 9: Access Level Management${NC}"
echo "----------------------------------"

# Change access levels for different scenarios
echo -e "${BLUE}Making a document public...${NC}"
api_call "PUT" "api.php/access" "{\"key\":\"documents/policy.txt\",\"access_type\":\"public-read\"}" "application/json" | pretty_json

echo -e "${BLUE}Making a backup more secure...${NC}"
# Find the backup file key first
backup_key=$(api_call "GET" "api.php/files?prefix=backups/" | jq -r '.data.files[0].key' 2>/dev/null)
if [ "$backup_key" != "null" ] && [ -n "$backup_key" ]; then
    api_call "PUT" "api.php/access" "{\"key\":\"$backup_key\",\"access_type\":\"private\"}" "application/json" | pretty_json
fi

echo ""

# Example 10: Cleanup Operations
echo -e "${YELLOW}Example 10: Cleanup Operations${NC}"
echo "------------------------------"

echo -e "${BLUE}Cleaning up temporary files...${NC}"

# List and optionally delete temporary files
temp_files=("temp_policy.txt" "temp_handbook.txt" "temp_notes.txt" "temp_image1.jpg" "temp_image2.png" "temp_backup.txt" "temp_main.css" "temp_app.js")

for file in "${temp_files[@]}"; do
    if [ -f "$file" ]; then
        rm "$file"
        echo "Removed local file: $file"
    fi
done

# Clean up temporary website directory
if [ -d "temp_website" ]; then
    rm -rf temp_website
    echo "Removed temporary website directory"
fi

echo ""

# Example 11: Monitoring and Statistics
echo -e "${YELLOW}Example 11: Storage Statistics${NC}"
echo "-----------------------------"

echo -e "${BLUE}Getting storage overview...${NC}"
files_response=$(api_call "GET" "api.php/files")

if command -v jq >/dev/null 2>&1; then
    total_files=$(echo "$files_response" | jq -r '.data.count // 0')
    private_count=$(echo "$files_response" | jq -r '[.data.files[] | select(.access_level == "private")] | length')
    public_read_count=$(echo "$files_response" | jq -r '[.data.files[] | select(.access_level == "public-read")] | length')
    public_write_count=$(echo "$files_response" | jq -r '[.data.files[] | select(.access_level == "public-read-write")] | length')
    
    echo -e "${GREEN}Storage Statistics:${NC}"
    echo -e "  Total Files: ${YELLOW}$total_files${NC}"
    echo -e "  Private Files: ${RED}$private_count${NC}"
    echo -e "  Public Read Files: ${BLUE}$public_read_count${NC}"
    echo -e "  Public Read/Write Files: ${PURPLE}$public_write_count${NC}"
    
    echo ""
    echo -e "${GREEN}Files by Category:${NC}"
    echo "$files_response" | jq -r '.data.files[] | "\(.key) - \(.access_level)"' | sort
else
    echo "$files_response" | pretty_json
fi

echo ""
echo -e "${GREEN}Advanced examples completed!${NC}"
echo ""
echo -e "${YELLOW}Next Steps:${NC}"
echo "- Integrate these patterns into your applications"
echo "- Set up monitoring for your storage usage"
echo "- Implement automated backup strategies"
echo "- Consider access control policies for your use case"
echo ""
echo -e "${BLUE}Useful Commands:${NC}"
echo "- Source the aliases: source examples/s3-aliases.sh"
echo "- Get help: s3-help"
echo "- Monitor usage: s3-stats"