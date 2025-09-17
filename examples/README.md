# S3 Storage API Examples

This directory contains comprehensive examples and tools for using the S3 Storage API.

## Files Overview

### Basic Examples
- **`curl-examples.sh`** - Basic cURL examples for Linux/Mac
- **`curl-examples.bat`** - Basic cURL examples for Windows
- **`advanced-curl-examples.sh`** - Advanced usage patterns and real-world scenarios

### CLI Tools
- **`s3-aliases.sh`** - Linux aliases and functions for easy API access
- **`s3-cli-installer.sh`** - System-wide CLI installer with shell completion
- **`sftp-setup-guide.md`** - Complete SFTP storage setup guide

## Quick Start

### 1. Basic cURL Usage

**Linux/Mac:**
```bash
# Make executable and run
chmod +x examples/curl-examples.sh
./examples/curl-examples.sh
```

**Windows:**
```cmd
examples\curl-examples.bat
```

### 2. Install CLI Aliases (Linux/Mac)

```bash
# Source aliases for current session
source examples/s3-aliases.sh

# Or install system-wide
chmod +x examples/s3-cli-installer.sh
./examples/s3-cli-installer.sh
```

### 3. Configure API Endpoint

```bash
# Set your API endpoint
export S3_API_BASE_URL="http://your-server.com:8000"

# Optional: Set API key if authentication is enabled
export S3_API_KEY="your-api-key"
```

## CLI Commands Reference

Once you've sourced the aliases, you can use these commands:

### Basic Operations
```bash
s3-info                                    # Get API information
s3-upload file.txt uploads/file.txt        # Upload a file
s3-upload-content data.json '{"key":"val"}' # Upload content directly
s3-list                                    # List all files
s3-list-prefix uploads/                    # List files with prefix
s3-download uploads/file.txt               # Download a file
s3-rm uploads/old-file.txt                 # Delete a file
```

### Advanced Operations
```bash
s3-chmod uploads/file.txt public-read      # Change access level
s3-upload-batch ./docs/ uploads/docs/     # Batch upload directory
s3-sync ./website/ public/site/           # Sync local directory
s3-stats                                  # Show storage statistics
```

### Configuration
```bash
s3-config set url http://localhost:8000   # Set API URL
s3-config set key your-api-key            # Set API key
s3-config get                             # Show current config
```

## Real-World Examples

### Website Deployment
```bash
# Upload website files with public access
s3-upload-batch ./website/ public/site/ public-read

# Update a specific file
s3-upload index.html public/site/index.html public-read

# Sync entire website (uploads only changed files)
s3-sync ./website/ public/site/ public-read
```

### Document Management
```bash
# Upload private documents
s3-upload contract.pdf documents/contracts/contract-2024.pdf private

# Upload public policy
s3-upload policy.pdf documents/public/policy.pdf public-read

# Change document access level
s3-chmod documents/public/policy.pdf private
```

### Configuration Management
```bash
# Store application configs
s3-upload-content config/prod.json '{"debug":false,"api":"https://api.prod.com"}' private

# Store environment-specific settings
s3-upload-content config/dev.json '{"debug":true,"api":"http://localhost:3000"}' private
```

### Backup Operations
```bash
# Create timestamped backup
tar -czf backup-$(date +%Y%m%d).tar.gz ./important-data/
s3-upload backup-$(date +%Y%m%d).tar.gz backups/backup-$(date +%Y%m%d).tar.gz private

# Batch upload backup files
s3-upload-batch ./backups/ archive/backups/ private
```

## Advanced Usage Patterns

### 1. Content Delivery Network (CDN)
```bash
# Upload static assets with public access
s3-upload-batch ./assets/css/ cdn/v1.0/css/ public-read
s3-upload-batch ./assets/js/ cdn/v1.0/js/ public-read
s3-upload-batch ./assets/images/ cdn/v1.0/images/ public-read

# Access files via: http://your-server.com/files/cdn/v1.0/css/main.css
```

### 2. API Data Storage
```bash
# Store API responses
curl https://api.example.com/users | s3-upload-content data/users.json "$(cat)" private

# Store processed data
s3-upload-content reports/daily-$(date +%Y%m%d).json "$(./generate-report.sh)" private
```

### 3. Multi-Environment Configuration
```bash
# Development environment
s3-upload-content config/dev.json '{"env":"dev","debug":true}' private

# Staging environment  
s3-upload-content config/staging.json '{"env":"staging","debug":false}' private

# Production environment
s3-upload-content config/prod.json '{"env":"prod","debug":false}' private
```

### 4. Image Gallery Management
```bash
# Upload images with metadata
s3-upload photo1.jpg gallery/nature/sunset.jpg public-read '{"category":"nature","tags":["sunset","landscape"]}'

# Batch upload with consistent access
s3-upload-batch ./photos/ gallery/events/ public-read
```

## Shell Integration

### Bash/Zsh Integration
Add to your `~/.bashrc` or `~/.zshrc`:

```bash
# S3 Storage CLI
export S3_API_BASE_URL="http://your-server.com:8000"
export S3_API_KEY="your-api-key"  # Optional
source /path/to/examples/s3-aliases.sh
```

### Fish Shell Integration
Add to your `~/.config/fish/config.fish`:

```fish
# S3 Storage CLI
set -gx S3_API_BASE_URL "http://your-server.com:8000"
set -gx S3_API_KEY "your-api-key"  # Optional

# Create fish functions (convert bash functions manually)
function s3-upload
    # Implementation here
end
```

## Automation Scripts

### Daily Backup Script
```bash
#!/bin/bash
# daily-backup.sh

source /path/to/s3-aliases.sh

# Create backup
backup_file="backup-$(date +%Y%m%d_%H%M%S).tar.gz"
tar -czf "$backup_file" /path/to/important/data

# Upload to storage
s3-upload "$backup_file" "backups/daily/$backup_file" private

# Clean up local file
rm "$backup_file"

echo "Daily backup completed: $backup_file"
```

### Website Deployment Script
```bash
#!/bin/bash
# deploy-website.sh

source /path/to/s3-aliases.sh

# Build website
npm run build

# Sync to storage
s3-sync ./dist/ public/website/ public-read

echo "Website deployed successfully"
```

### Log Archival Script
```bash
#!/bin/bash
# archive-logs.sh

source /path/to/s3-aliases.sh

# Archive old logs
find /var/log/myapp -name "*.log" -mtime +7 | while read log_file; do
    relative_path="${log_file#/var/log/myapp/}"
    s3-upload "$log_file" "logs/archive/$relative_path" private
    rm "$log_file"
done

echo "Log archival completed"
```

## Troubleshooting

### Common Issues

**Command not found:**
```bash
# Make sure aliases are sourced
source examples/s3-aliases.sh

# Or check if CLI is installed
which s3-storage
```

**Connection errors:**
```bash
# Check API endpoint
s3-info

# Verify configuration
s3-config get
```

**Permission errors:**
```bash
# Check API key
echo $S3_API_KEY

# Test with a simple operation
s3-list
```

### Debug Mode
Enable verbose output:
```bash
# Add to your commands
curl -v ...  # For verbose cURL output

# Or modify the aliases to include debug flags
```

## Performance Tips

1. **Batch Operations**: Use `s3-upload-batch` for multiple files
2. **Compression**: Compress large files before upload
3. **Parallel Uploads**: Use `xargs -P` for parallel processing
4. **Incremental Sync**: Use `s3-sync` instead of full re-upload

## Security Best Practices

1. **API Keys**: Store API keys securely, never in scripts
2. **Access Levels**: Use appropriate access levels (private by default)
3. **HTTPS**: Always use HTTPS in production
4. **Key Rotation**: Regularly rotate API keys
5. **Monitoring**: Monitor API usage and access patterns

## Integration Examples

### CI/CD Pipeline (GitHub Actions)
```yaml
- name: Deploy to S3 Storage
  run: |
    source examples/s3-aliases.sh
    s3-sync ./dist/ public/app/ public-read
  env:
    S3_API_BASE_URL: ${{ secrets.S3_API_URL }}
    S3_API_KEY: ${{ secrets.S3_API_KEY }}
```

### Docker Container
```dockerfile
COPY examples/s3-aliases.sh /usr/local/bin/
RUN echo "source /usr/local/bin/s3-aliases.sh" >> ~/.bashrc
```

### Cron Jobs
```bash
# Add to crontab
0 2 * * * /path/to/daily-backup.sh
0 */6 * * * /path/to/sync-website.sh
```

This comprehensive set of examples should cover most use cases for the S3 Storage API. Choose the approach that best fits your workflow and requirements.