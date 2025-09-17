#!/bin/bash

# S3 Storage API - Linux Aliases and Functions
# Source this file to add convenient aliases for the S3 Storage API
# Usage: source examples/s3-aliases.sh

# Configuration - Update these values for your setup
export S3_API_BASE_URL="${S3_API_BASE_URL:-http://localhost:8000}"
export S3_API_KEY="${S3_API_KEY:-}"  # Set if you have API key authentication

# Colors for output
export S3_COLOR_RED='\033[0;31m'
export S3_COLOR_GREEN='\033[0;32m'
export S3_COLOR_YELLOW='\033[1;33m'
export S3_COLOR_BLUE='\033[0;34m'
export S3_COLOR_NC='\033[0m' # No Color

# Helper function to make API calls
s3_api_call() {
    local method=$1
    local endpoint=$2
    local data=$3
    local content_type=$4
    local extra_args=$5
    
    local curl_cmd="curl -s -X $method"
    
    # Add API key if set
    if [ -n "$S3_API_KEY" ]; then
        curl_cmd="$curl_cmd -H \"X-API-Key: $S3_API_KEY\""
    fi
    
    # Add content type if specified
    if [ -n "$content_type" ]; then
        curl_cmd="$curl_cmd -H \"Content-Type: $content_type\""
    fi
    
    # Add data if specified
    if [ -n "$data" ]; then
        if [ "$content_type" = "application/json" ]; then
            curl_cmd="$curl_cmd -d '$data'"
        else
            curl_cmd="$curl_cmd $data"
        fi
    fi
    
    # Add extra arguments
    if [ -n "$extra_args" ]; then
        curl_cmd="$curl_cmd $extra_args"
    fi
    
    # Execute the curl command
    eval "$curl_cmd \"$S3_API_BASE_URL/$endpoint\""
}

# Pretty print JSON if jq is available
s3_pretty_json() {
    if command -v jq >/dev/null 2>&1; then
        jq '.'
    else
        cat
    fi
}

# Basic API Information
alias s3-info='s3_api_call "GET" "api.php" | s3_pretty_json'

# File Upload Functions
s3-upload() {
    local file_path="$1"
    local key="$2"
    local access_type="${3:-private}"
    local metadata="$4"
    
    if [ -z "$file_path" ] || [ -z "$key" ]; then
        echo -e "${S3_COLOR_RED}Usage: s3-upload <file_path> <key> [access_type] [metadata_json]${S3_COLOR_NC}"
        echo -e "${S3_COLOR_YELLOW}Example: s3-upload document.pdf uploads/doc.pdf public-read${S3_COLOR_NC}"
        return 1
    fi
    
    if [ ! -f "$file_path" ]; then
        echo -e "${S3_COLOR_RED}Error: File '$file_path' not found${S3_COLOR_NC}"
        return 1
    fi
    
    local form_data="-F \"file=@$file_path\" -F \"key=$key\" -F \"access_type=$access_type\""
    
    if [ -n "$metadata" ]; then
        form_data="$form_data -F \"metadata=$metadata\""
    fi
    
    echo -e "${S3_COLOR_BLUE}Uploading '$file_path' as '$key' with access '$access_type'...${S3_COLOR_NC}"
    s3_api_call "POST" "api.php" "$form_data" "" | s3_pretty_json
}

# Content Upload Function
s3-upload-content() {
    local key="$1"
    local content="$2"
    local access_type="${3:-private}"
    local content_type="${4:-text/plain}"
    
    if [ -z "$key" ] || [ -z "$content" ]; then
        echo -e "${S3_COLOR_RED}Usage: s3-upload-content <key> <content> [access_type] [content_type]${S3_COLOR_NC}"
        echo -e "${S3_COLOR_YELLOW}Example: s3-upload-content data.json '{\"hello\":\"world\"}' public-read application/json${S3_COLOR_NC}"
        return 1
    fi
    
    local json_data="{\"key\":\"$key\",\"content\":\"$content\",\"access_type\":\"$access_type\",\"content_type\":\"$content_type\"}"
    
    echo -e "${S3_COLOR_BLUE}Uploading content to '$key' with access '$access_type'...${S3_COLOR_NC}"
    s3_api_call "PUT" "api.php" "$json_data" "application/json" | s3_pretty_json
}

# List Files Functions
alias s3-list='s3_api_call "GET" "api.php/files" | s3_pretty_json'

s3-list-prefix() {
    local prefix="$1"
    
    if [ -z "$prefix" ]; then
        echo -e "${S3_COLOR_RED}Usage: s3-list-prefix <prefix>${S3_COLOR_NC}"
        echo -e "${S3_COLOR_YELLOW}Example: s3-list-prefix uploads/${S3_COLOR_NC}"
        return 1
    fi
    
    echo -e "${S3_COLOR_BLUE}Listing files with prefix '$prefix'...${S3_COLOR_NC}"
    s3_api_call "GET" "api.php/files?prefix=$prefix" | s3_pretty_json
}

# Change Access Level Function
s3-chmod() {
    local key="$1"
    local access_type="$2"
    
    if [ -z "$key" ] || [ -z "$access_type" ]; then
        echo -e "${S3_COLOR_RED}Usage: s3-chmod <key> <access_type>${S3_COLOR_NC}"
        echo -e "${S3_COLOR_YELLOW}Access types: private, public-read, public-read-write${S3_COLOR_NC}"
        echo -e "${S3_COLOR_YELLOW}Example: s3-chmod uploads/doc.pdf public-read${S3_COLOR_NC}"
        return 1
    fi
    
    local json_data="{\"key\":\"$key\",\"access_type\":\"$access_type\"}"
    
    echo -e "${S3_COLOR_BLUE}Changing access level of '$key' to '$access_type'...${S3_COLOR_NC}"
    s3_api_call "PUT" "api.php/access" "$json_data" "application/json" | s3_pretty_json
}

# Delete File Function
s3-rm() {
    local key="$1"
    
    if [ -z "$key" ]; then
        echo -e "${S3_COLOR_RED}Usage: s3-rm <key>${S3_COLOR_NC}"
        echo -e "${S3_COLOR_YELLOW}Example: s3-rm uploads/old-file.txt${S3_COLOR_NC}"
        return 1
    fi
    
    echo -e "${S3_COLOR_YELLOW}Are you sure you want to delete '$key'? (y/N)${S3_COLOR_NC}"
    read -r confirmation
    
    if [ "$confirmation" = "y" ] || [ "$confirmation" = "Y" ]; then
        local json_data="{\"key\":\"$key\"}"
        echo -e "${S3_COLOR_BLUE}Deleting '$key'...${S3_COLOR_NC}"
        s3_api_call "DELETE" "api.php" "$json_data" "application/json" | s3_pretty_json
    else
        echo -e "${S3_COLOR_GREEN}Deletion cancelled${S3_COLOR_NC}"
    fi
}

# Batch Upload Function
s3-upload-batch() {
    local directory="$1"
    local prefix="$2"
    local access_type="${3:-private}"
    
    if [ -z "$directory" ] || [ -z "$prefix" ]; then
        echo -e "${S3_COLOR_RED}Usage: s3-upload-batch <directory> <prefix> [access_type]${S3_COLOR_NC}"
        echo -e "${S3_COLOR_YELLOW}Example: s3-upload-batch ./documents/ uploads/docs/ public-read${S3_COLOR_NC}"
        return 1
    fi
    
    if [ ! -d "$directory" ]; then
        echo -e "${S3_COLOR_RED}Error: Directory '$directory' not found${S3_COLOR_NC}"
        return 1
    fi
    
    echo -e "${S3_COLOR_BLUE}Batch uploading files from '$directory' to prefix '$prefix'...${S3_COLOR_NC}"
    
    find "$directory" -type f | while read -r file; do
        local relative_path="${file#$directory}"
        local key="$prefix${relative_path#/}"
        echo -e "${S3_COLOR_YELLOW}Uploading: $file -> $key${S3_COLOR_NC}"
        s3-upload "$file" "$key" "$access_type"
        echo ""
    done
}

# Download Function (for public files)
s3-download() {
    local key="$1"
    local output_file="$2"
    
    if [ -z "$key" ]; then
        echo -e "${S3_COLOR_RED}Usage: s3-download <key> [output_file]${S3_COLOR_NC}"
        echo -e "${S3_COLOR_YELLOW}Example: s3-download uploads/doc.pdf ./downloaded-doc.pdf${S3_COLOR_NC}"
        return 1
    fi
    
    if [ -z "$output_file" ]; then
        output_file="$(basename "$key")"
    fi
    
    echo -e "${S3_COLOR_BLUE}Downloading '$key' to '$output_file'...${S3_COLOR_NC}"
    curl -s -o "$output_file" "$S3_API_BASE_URL/files/$key"
    
    if [ $? -eq 0 ] && [ -f "$output_file" ]; then
        echo -e "${S3_COLOR_GREEN}Downloaded successfully: $output_file${S3_COLOR_NC}"
    else
        echo -e "${S3_COLOR_RED}Download failed${S3_COLOR_NC}"
    fi
}

# Sync Function (upload changed files)
s3-sync() {
    local local_dir="$1"
    local remote_prefix="$2"
    local access_type="${3:-private}"
    
    if [ -z "$local_dir" ] || [ -z "$remote_prefix" ]; then
        echo -e "${S3_COLOR_RED}Usage: s3-sync <local_directory> <remote_prefix> [access_type]${S3_COLOR_NC}"
        echo -e "${S3_COLOR_YELLOW}Example: s3-sync ./website/ public/site/ public-read${S3_COLOR_NC}"
        return 1
    fi
    
    if [ ! -d "$local_dir" ]; then
        echo -e "${S3_COLOR_RED}Error: Directory '$local_dir' not found${S3_COLOR_NC}"
        return 1
    fi
    
    echo -e "${S3_COLOR_BLUE}Syncing '$local_dir' to '$remote_prefix'...${S3_COLOR_NC}"
    
    # Get list of remote files
    local remote_files=$(s3_api_call "GET" "api.php/files?prefix=$remote_prefix" | jq -r '.data.files[].key' 2>/dev/null)
    
    # Upload local files
    find "$local_dir" -type f | while read -r file; do
        local relative_path="${file#$local_dir}"
        local key="$remote_prefix${relative_path#/}"
        
        # Check if file needs uploading (simple timestamp check)
        local should_upload=true
        
        if echo "$remote_files" | grep -q "^$key$"; then
            echo -e "${S3_COLOR_YELLOW}File exists remotely: $key (uploading anyway)${S3_COLOR_NC}"
        fi
        
        if [ "$should_upload" = true ]; then
            echo -e "${S3_COLOR_BLUE}Syncing: $file -> $key${S3_COLOR_NC}"
            s3-upload "$file" "$key" "$access_type" | grep -E "(success|error)" || true
        fi
    done
}

# Statistics Function
s3-stats() {
    echo -e "${S3_COLOR_BLUE}Getting storage statistics...${S3_COLOR_NC}"
    
    local files_data=$(s3_api_call "GET" "api.php/files")
    
    if command -v jq >/dev/null 2>&1; then
        local total_files=$(echo "$files_data" | jq -r '.data.count // 0')
        local private_count=$(echo "$files_data" | jq -r '[.data.files[] | select(.access_level == "private")] | length')
        local public_read_count=$(echo "$files_data" | jq -r '[.data.files[] | select(.access_level == "public-read")] | length')
        local public_write_count=$(echo "$files_data" | jq -r '[.data.files[] | select(.access_level == "public-read-write")] | length')
        
        echo -e "${S3_COLOR_GREEN}Storage Statistics:${S3_COLOR_NC}"
        echo -e "  Total Files: $total_files"
        echo -e "  Private: $private_count"
        echo -e "  Public Read: $public_read_count"
        echo -e "  Public Read/Write: $public_write_count"
    else
        echo "$files_data" | s3_pretty_json
    fi
}

# Help Function
s3-help() {
    echo -e "${S3_COLOR_GREEN}S3 Storage API - Command Line Tools${S3_COLOR_NC}"
    echo -e "${S3_COLOR_BLUE}Configuration:${S3_COLOR_NC}"
    echo -e "  S3_API_BASE_URL: $S3_API_BASE_URL"
    echo -e "  S3_API_KEY: ${S3_API_KEY:+[SET]}${S3_API_KEY:-[NOT SET]}"
    echo ""
    echo -e "${S3_COLOR_BLUE}Available Commands:${S3_COLOR_NC}"
    echo -e "  ${S3_COLOR_YELLOW}s3-info${S3_COLOR_NC}                           - Get API information"
    echo -e "  ${S3_COLOR_YELLOW}s3-upload <file> <key> [access] [metadata]${S3_COLOR_NC} - Upload a file"
    echo -e "  ${S3_COLOR_YELLOW}s3-upload-content <key> <content> [access]${S3_COLOR_NC} - Upload content directly"
    echo -e "  ${S3_COLOR_YELLOW}s3-list${S3_COLOR_NC}                          - List all files"
    echo -e "  ${S3_COLOR_YELLOW}s3-list-prefix <prefix>${S3_COLOR_NC}           - List files with prefix"
    echo -e "  ${S3_COLOR_YELLOW}s3-chmod <key> <access_type>${S3_COLOR_NC}      - Change file access level"
    echo -e "  ${S3_COLOR_YELLOW}s3-rm <key>${S3_COLOR_NC}                       - Delete a file"
    echo -e "  ${S3_COLOR_YELLOW}s3-download <key> [output_file]${S3_COLOR_NC}   - Download a public file"
    echo -e "  ${S3_COLOR_YELLOW}s3-upload-batch <dir> <prefix> [access]${S3_COLOR_NC} - Batch upload directory"
    echo -e "  ${S3_COLOR_YELLOW}s3-sync <local_dir> <remote_prefix> [access]${S3_COLOR_NC} - Sync directory"
    echo -e "  ${S3_COLOR_YELLOW}s3-stats${S3_COLOR_NC}                          - Show storage statistics"
    echo -e "  ${S3_COLOR_YELLOW}s3-help${S3_COLOR_NC}                           - Show this help"
    echo ""
    echo -e "${S3_COLOR_BLUE}Access Types:${S3_COLOR_NC}"
    echo -e "  ${S3_COLOR_YELLOW}private${S3_COLOR_NC}        - Only accessible with authentication"
    echo -e "  ${S3_COLOR_YELLOW}public-read${S3_COLOR_NC}    - Anyone can download/view"
    echo -e "  ${S3_COLOR_YELLOW}public-read-write${S3_COLOR_NC} - Anyone can read and modify (use with caution)"
    echo ""
    echo -e "${S3_COLOR_BLUE}Examples:${S3_COLOR_NC}"
    echo -e "  s3-upload document.pdf uploads/doc.pdf public-read"
    echo -e "  s3-upload-content config.json '{\"debug\":true}' private application/json"
    echo -e "  s3-list-prefix uploads/"
    echo -e "  s3-chmod uploads/doc.pdf private"
    echo -e "  s3-upload-batch ./images/ gallery/ public-read"
    echo -e "  s3-sync ./website/ public/site/ public-read"
}

# Show help on source
echo -e "${S3_COLOR_GREEN}S3 Storage API aliases loaded!${S3_COLOR_NC}"
echo -e "Type ${S3_COLOR_YELLOW}s3-help${S3_COLOR_NC} for available commands"
echo -e "Configure with: ${S3_COLOR_BLUE}export S3_API_BASE_URL=http://your-server.com${S3_COLOR_NC}"
echo -e "Set API key with: ${S3_COLOR_BLUE}export S3_API_KEY=your-api-key${S3_COLOR_NC}"