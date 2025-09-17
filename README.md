# PHP S3 Storage App

A PHP application that provides S3 storage functionality with different public access types.

## Features

- **Multiple Storage Backends**: AWS S3 or Local S3-compatible storage
- **Multiple Access Types**: Private, Public Read, and Public Read/Write
- **File Upload**: Upload files directly to storage with chosen access level
- **Content Upload**: Create files directly from text content
- **Access Management**: Change access levels of existing files
- **Presigned URLs**: Generate temporary URLs for private files
- **File Management**: List, delete, and manage objects
- **Dashboard**: Overview of storage statistics
- **Local S3 Storage**: Built-in S3-compatible storage system for development or self-hosting

## Access Types Explained

1. **Private**: Only accessible with proper AWS credentials or presigned URLs
2. **Public Read**: Anyone can download/view the file via direct URL
3. **Public Read/Write**: Anyone can read and modify the file (use with caution)

## Prerequisites

- PHP 7.4 or higher with extensions:
  - `curl`
  - `json`
  - `mbstring`
  - `openssl`
- Composer (PHP package manager)
- AWS S3 bucket or S3-compatible storage service (optional - can use local storage)
- Web server (Apache, Nginx, or PHP built-in server for development)

## AWS Setup

### 1. Create S3 Bucket

1. Log into AWS Console
2. Navigate to S3 service
3. Click "Create bucket"
4. Choose a unique bucket name
5. Select your preferred region
6. Configure bucket settings:
   - **Block Public Access**: Uncheck "Block all public access" if you plan to use public-read or public-read-write files
   - **Bucket Versioning**: Enable if desired
   - **Server-side encryption**: Enable for security

### 2. Create IAM User

1. Navigate to IAM service in AWS Console
2. Click "Users" → "Add user"
3. Set username (e.g., `s3-storage-app`)
4. Select "Programmatic access"
5. Attach policies:
   - `AmazonS3FullAccess` (or create custom policy with specific bucket permissions)

### 3. Custom IAM Policy (Recommended)

Create a custom policy for better security:

```json
{
    "Version": "2012-10-17",
    "Statement": [
        {
            "Effect": "Allow",
            "Action": [
                "s3:GetObject",
                "s3:PutObject",
                "s3:DeleteObject",
                "s3:GetObjectAcl",
                "s3:PutObjectAcl",
                "s3:ListBucket"
            ],
            "Resource": [
                "arn:aws:s3:::your-bucket-name",
                "arn:aws:s3:::your-bucket-name/*"
            ]
        }
    ]
}
```

Replace `your-bucket-name` with your actual bucket name.

## Installation & Configuration

### 1. Download and Setup

```bash
# Clone or download the project
git clone <repository-url>
cd php-s3-storage-app

# Install PHP dependencies
composer install
```

### 2. Environment Configuration

```bash
# Copy environment template
cp .env.example .env

# Edit configuration file
nano .env  # or use your preferred editor
```

Configure your `.env` file:

#### For AWS S3 Storage:
```env
# Storage Configuration
STORAGE_TYPE=aws

# AWS Credentials
AWS_ACCESS_KEY_ID=AKIA...your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_access_key

# AWS Region (e.g., us-east-1, eu-west-1, ap-southeast-1)
AWS_DEFAULT_REGION=us-east-1

# S3 Bucket Name
AWS_BUCKET=your-unique-bucket-name

# Optional: Custom S3 Endpoint (for S3-compatible services like MinIO, DigitalOcean Spaces)
AWS_ENDPOINT=
```

#### For Local S3-Compatible Storage:
```env
# Storage Configuration
STORAGE_TYPE=local

# Local Storage Configuration
LOCAL_STORAGE_PATH=./storage
LOCAL_BASE_URL=http://localhost:8000
```

### 3. File Permissions

Ensure proper file permissions:

```bash
# Make sure web server can read files
chmod -R 755 .
chmod -R 644 *.php *.json *.md

# Secure environment file
chmod 600 .env
```

## Deployment Options

### Option 1: Local Development Server

```bash
# Start PHP built-in server
php -S localhost:8000 -t public

# Access application
# Open http://localhost:8000 in your browser
```

### Option 2: Apache Deployment

1. **Upload files to web server**:
   ```bash
   # Upload to your web server document root
   scp -r . user@yourserver.com:/var/www/html/s3-app/
   ```

2. **Create Apache virtual host** (`/etc/apache2/sites-available/s3-app.conf`):
   ```apache
   <VirtualHost *:80>
       ServerName s3app.yourdomain.com
       DocumentRoot /var/www/html/s3-app/public
       
       <Directory /var/www/html/s3-app/public>
           AllowOverride All
           Require all granted
           DirectoryIndex index.php
       </Directory>
       
       ErrorLog ${APACHE_LOG_DIR}/s3-app_error.log
       CustomLog ${APACHE_LOG_DIR}/s3-app_access.log combined
   </VirtualHost>
   ```

3. **Enable site and restart Apache**:
   ```bash
   sudo a2ensite s3-app
   sudo systemctl restart apache2
   ```

### Option 3: Nginx Deployment

1. **Create Nginx server block** (`/etc/nginx/sites-available/s3-app`):
   ```nginx
   server {
       listen 80;
       server_name s3app.yourdomain.com;
       root /var/www/html/s3-app/public;
       index index.php;

       location / {
           try_files $uri $uri/ /index.php?$query_string;
       }

       location ~ \.php$ {
           fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
           fastcgi_index index.php;
           fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
           include fastcgi_params;
       }

       location ~ /\.ht {
           deny all;
       }
   }
   ```

2. **Enable site and restart Nginx**:
   ```bash
   sudo ln -s /etc/nginx/sites-available/s3-app /etc/nginx/sites-enabled/
   sudo systemctl restart nginx
   ```

### Option 4: Docker Deployment

Create `Dockerfile`:

```dockerfile
FROM php:8.1-apache

# Install required extensions
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    && docker-php-ext-install curl

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy application files
COPY . /var/www/html/
WORKDIR /var/www/html

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Configure Apache document root
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

EXPOSE 80
```

Create `docker-compose.yml`:

```yaml
version: '3.8'
services:
  s3-app:
    build: .
    ports:
      - "8080:80"
    environment:
      - AWS_ACCESS_KEY_ID=${AWS_ACCESS_KEY_ID}
      - AWS_SECRET_ACCESS_KEY=${AWS_SECRET_ACCESS_KEY}
      - AWS_DEFAULT_REGION=${AWS_DEFAULT_REGION}
      - AWS_BUCKET=${AWS_BUCKET}
      - AWS_ENDPOINT=${AWS_ENDPOINT}
    volumes:
      - ./.env:/var/www/html/.env:ro
```

Deploy with Docker:

```bash
# Build and run
docker-compose up -d

# Access application at http://localhost:8080
```

## SSL/HTTPS Configuration (Recommended)

For production deployments, always use HTTPS:

### Let's Encrypt with Certbot

```bash
# Install Certbot
sudo apt install certbot python3-certbot-apache  # For Apache
# OR
sudo apt install certbot python3-certbot-nginx   # For Nginx

# Obtain SSL certificate
sudo certbot --apache -d s3app.yourdomain.com    # For Apache
# OR
sudo certbot --nginx -d s3app.yourdomain.com     # For Nginx

# Auto-renewal (add to crontab)
0 12 * * * /usr/bin/certbot renew --quiet
```

## Local S3-Compatible Storage

The application includes a built-in S3-compatible storage system that works entirely with local files. This is perfect for:

- **Development**: No need for AWS credentials during development
- **Self-hosting**: Complete control over your data
- **Testing**: Isolated testing environment
- **Cost savings**: No cloud storage costs

### Local Storage Features

- **S3-Compatible API**: Same interface as AWS S3
- **Access Control**: Full support for private, public-read, and public-read-write permissions
- **Presigned URLs**: Temporary access tokens for private files
- **Metadata Storage**: File information and permissions stored locally
- **Direct File Access**: Public files accessible via direct URLs with proper access control

### Local Storage Setup

1. **Set storage type in `.env`**:
   ```env
   STORAGE_TYPE=local
   LOCAL_STORAGE_PATH=./storage
   LOCAL_BASE_URL=http://localhost:8000
   ```

2. **File Structure Created**:
   ```
   storage/
   ├── files/           # Actual file storage
   └── .metadata/       # Access control and file metadata
   ```

3. **Access Control**:
   - **Private files**: Only accessible via presigned URLs
   - **Public files**: Direct access via `/files/filename` URLs
   - **Access validation**: Automatic permission checking

### Local vs AWS S3 Comparison

| Feature | Local Storage | AWS S3 |
|---------|---------------|---------|
| Setup Complexity | Minimal | Requires AWS account |
| Cost | Free | Pay per usage |
| Scalability | Limited by server | Unlimited |
| Reliability | Single server | 99.999999999% durability |
| Access Control | Full support | Full support |
| Presigned URLs | Token-based | AWS signature |
| Best For | Development, small projects | Production, large scale |

## Testing the Installation

### 1. Basic Connectivity Test

Create `test-connection.php` in the project root:

```php
<?php
require_once 'vendor/autoload.php';

use App\StorageFactory;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$storageType = $_ENV['STORAGE_TYPE'] ?? 'aws';

try {
    if ($storageType === 'local') {
        $config = [
            'storage_type' => 'local',
            'local_storage_path' => $_ENV['LOCAL_STORAGE_PATH'] ?? './storage',
            'local_base_url' => $_ENV['LOCAL_BASE_URL'] ?? 'http://localhost:8000'
        ];
        
        $storage = StorageFactory::create($config);
        echo "✅ Local storage initialized successfully!\n";
        echo "Storage path: " . $config['local_storage_path'] . "\n";
        
    } else {
        use Aws\S3\S3Client;
        
        $s3 = new S3Client([
            'version' => 'latest',
            'region' => $_ENV['AWS_DEFAULT_REGION'],
            'credentials' => [
                'key' => $_ENV['AWS_ACCESS_KEY_ID'],
                'secret' => $_ENV['AWS_SECRET_ACCESS_KEY']
            ]
        ]);
        
        $result = $s3->headBucket(['Bucket' => $_ENV['AWS_BUCKET']]);
        echo "✅ AWS S3 connection successful! Bucket is accessible.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Connection failed: " . $e->getMessage() . "\n";
}
```

Run the test:

```bash
php test-connection.php
```

### 2. Upload Test

After accessing the web interface:

1. Go to the Upload page
2. Try uploading a small test file with "Private" access
3. Check the file appears in the file list
4. Try changing its access level to "Public Read"
5. Generate a presigned URL and test access

## Troubleshooting

### Common Issues

**1. "Class 'Dotenv\Dotenv' not found"**
```bash
# Ensure Composer dependencies are installed
composer install
```

**2. "Access Denied" errors**
- Check IAM user permissions
- Verify bucket name in `.env` file
- Ensure bucket exists in the specified region

**3. "Invalid credentials" errors**
- Verify AWS_ACCESS_KEY_ID and AWS_SECRET_ACCESS_KEY in `.env`
- Check for extra spaces or quotes in credentials

**4. File upload errors**
- Check PHP upload limits in `php.ini`:
  ```ini
  upload_max_filesize = 50M
  post_max_size = 50M
  max_execution_time = 300
  ```

**5. Permission denied on files**
```bash
# Fix file permissions
sudo chown -R www-data:www-data /path/to/app
sudo chmod -R 755 /path/to/app
sudo chmod 600 /path/to/app/.env
```

### Debug Mode

Enable debug mode by adding to `.env`:

```env
APP_DEBUG=true
```

This will show detailed error messages (disable in production).

## Usage Guide

### Dashboard
- View storage statistics and file counts by access type
- Quick overview of your S3 usage
- Navigation to main functions

### Upload Files
- **File Upload**: Select files from your computer
- **Content Upload**: Create files directly from text
- Choose access level during upload (Private, Public Read, Public Read/Write)

### File Management
- View all files with their access levels
- Change access permissions on existing files
- Generate presigned URLs for temporary access to private files
- Delete files from S3

### Access Level Management
- **Private**: Secure files requiring authentication
- **Public Read**: Files accessible via direct URL (good for images, documents)
- **Public Read/Write**: Editable by anyone (use with extreme caution)

## Security Best Practices

### Environment Security
- Never commit `.env` file to version control
- Use strong, unique AWS credentials
- Regularly rotate access keys
- Use IAM policies with minimal required permissions

### Application Security
- Always use HTTPS in production
- Implement user authentication for admin functions
- Regularly update dependencies: `composer update`
- Monitor S3 access logs for suspicious activity

### S3 Security
- Enable S3 bucket logging
- Use bucket policies to restrict access
- Enable versioning for important data
- Consider S3 encryption at rest

## Monitoring and Maintenance

### Log Files
- Check web server error logs: `/var/log/apache2/error.log` or `/var/log/nginx/error.log`
- Monitor S3 access patterns in AWS CloudTrail
- Set up CloudWatch alarms for unusual activity

### Regular Maintenance
```bash
# Update dependencies monthly
composer update

# Check for security updates
composer audit

# Clean up old log files
sudo logrotate -f /etc/logrotate.conf
```

## File Structure

```
├── src/
│   ├── S3StorageManager.php      # AWS S3 operations class
│   ├── LocalS3Storage.php        # Local S3-compatible storage class
│   ├── LocalS3StorageHelper.php  # Helper for local storage operations
│   └── StorageFactory.php        # Factory to create storage instances
├── public/
│   ├── index.php                 # Application entry point
│   ├── dashboard.php             # Storage dashboard
│   ├── upload.php                # File upload interface
│   ├── list.php                  # File management
│   ├── download.php              # Presigned URL handler
│   ├── access-control.php        # Local file access control
│   └── files/                    # Local storage files (when using local storage)
│       └── .htaccess             # Access control for local files
├── storage/                      # Local storage directory (created automatically)
│   ├── files/                    # Actual stored files
│   └── .metadata/                # File metadata and permissions
├── vendor/                       # Composer dependencies
├── composer.json                 # PHP dependencies
├── composer.lock                 # Dependency lock file
├── .env.example                  # Environment template
├── .env                          # Environment config (create from example)
├── Dockerfile                    # Docker configuration (optional)
├── docker-compose.yml            # Docker Compose (optional)
└── README.md                     # This documentation
```

## Support and Contributing

### Getting Help
- Check the troubleshooting section above
- Review AWS S3 documentation for bucket configuration
- Verify PHP and web server configurations

### Contributing
- Follow PSR-12 coding standards
- Add tests for new features
- Update documentation for changes
- Submit pull requests with clear descriptions