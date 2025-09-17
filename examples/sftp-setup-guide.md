# SFTP Storage Setup Guide

This guide explains how to configure and use SFTP remote storage as a backend for your S3-compatible storage application.

## What is SFTP Storage?

SFTP (SSH File Transfer Protocol) storage allows you to use any remote server with SSH access as your storage backend. This is useful for:

- **Remote Storage**: Store files on a different server
- **Shared Storage**: Multiple applications can access the same SFTP server
- **Backup Solutions**: Use existing backup servers as storage
- **Cost Efficiency**: Use existing infrastructure instead of cloud storage
- **Security**: Full control over your data and access

## Prerequisites

1. **SFTP Server**: A remote server with SSH/SFTP access
2. **PHP Extension**: The `phpseclib/phpseclib` library (installed via Composer)
3. **Credentials**: Either password or SSH key authentication

## Configuration

### 1. Environment Variables

Set these variables in your `.env` file:

```env
# Set storage type to SFTP
STORAGE_TYPE=sftp

# SFTP Server Configuration
SFTP_HOST=your-server.example.com
SFTP_PORT=22
SFTP_USERNAME=your-username

# Authentication Method 1: Password (less secure)
SFTP_PASSWORD=your-password

# Authentication Method 2: SSH Key (recommended)
SFTP_PRIVATE_KEY=/path/to/your/private/key
SFTP_PRIVATE_KEY_PASSWORD=passphrase-if-key-is-encrypted

# Storage Configuration
SFTP_PATH=/home/username/storage
SFTP_BASE_URL=http://your-app-domain.com
```

### 2. Authentication Methods

#### Password Authentication
```env
SFTP_USERNAME=myuser
SFTP_PASSWORD=mypassword
```

#### SSH Key Authentication (Recommended)
```env
SFTP_USERNAME=myuser
SFTP_PRIVATE_KEY=/home/user/.ssh/id_rsa
# Optional: if your key has a passphrase
SFTP_PRIVATE_KEY_PASSWORD=my-key-passphrase
```

## SFTP Server Setup

### 1. Create Storage Directory

On your SFTP server, create the storage directory:

```bash
# SSH into your SFTP server
ssh username@your-server.example.com

# Create storage directory
mkdir -p /home/username/storage
chmod 755 /home/username/storage

# The application will create these subdirectories:
# /home/username/storage/files/     - Actual file storage
# /home/username/storage/.metadata/ - File metadata and permissions
```

### 2. SSH Key Setup (Recommended)

#### Generate SSH Key Pair
```bash
# On your application server
ssh-keygen -t rsa -b 4096 -f ~/.ssh/sftp_storage_key

# Copy public key to SFTP server
ssh-copy-id -i ~/.ssh/sftp_storage_key.pub username@your-server.example.com
```

#### Configure Environment
```env
SFTP_PRIVATE_KEY=/home/appuser/.ssh/sftp_storage_key
```

### 3. User Permissions

Ensure your SFTP user has proper permissions:

```bash
# On SFTP server
sudo usermod -d /home/username username
sudo chown -R username:username /home/username/storage
sudo chmod -R 755 /home/username/storage
```

## Testing SFTP Connection

Create a test script to verify your SFTP connection:

```php
<?php
require_once 'vendor/autoload.php';

use phpseclib3\Net\SFTP;
use phpseclib3\Crypt\PublicKeyLoader;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$sftp = new SFTP($_ENV['SFTP_HOST'], $_ENV['SFTP_PORT'] ?? 22);

// Test authentication
if (isset($_ENV['SFTP_PRIVATE_KEY'])) {
    $key = PublicKeyLoader::load(file_get_contents($_ENV['SFTP_PRIVATE_KEY']));
    if (isset($_ENV['SFTP_PRIVATE_KEY_PASSWORD'])) {
        $key = $key->withPassword($_ENV['SFTP_PRIVATE_KEY_PASSWORD']);
    }
    $login = $sftp->login($_ENV['SFTP_USERNAME'], $key);
} else {
    $login = $sftp->login($_ENV['SFTP_USERNAME'], $_ENV['SFTP_PASSWORD']);
}

if ($login) {
    echo "✅ SFTP connection successful!\n";
    
    // Test directory creation
    $testDir = $_ENV['SFTP_PATH'] . '/test';
    if ($sftp->mkdir($testDir)) {
        echo "✅ Directory creation successful!\n";
        $sftp->rmdir($testDir);
    } else {
        echo "❌ Directory creation failed!\n";
    }
} else {
    echo "❌ SFTP connection failed!\n";
}
```

## Common SFTP Server Configurations

### 1. Ubuntu/Debian Server

```bash
# Install OpenSSH server
sudo apt update
sudo apt install openssh-server

# Configure SSH (optional)
sudo nano /etc/ssh/sshd_config

# Add these lines for better security:
# PasswordAuthentication no  # If using key auth only
# PubkeyAuthentication yes
# AuthorizedKeysFile .ssh/authorized_keys

# Restart SSH service
sudo systemctl restart ssh
```

### 2. CentOS/RHEL Server

```bash
# Install OpenSSH server
sudo yum install openssh-server

# Start and enable SSH service
sudo systemctl start sshd
sudo systemctl enable sshd

# Configure firewall
sudo firewall-cmd --permanent --add-service=ssh
sudo firewall-cmd --reload
```

### 3. Shared Hosting

Many shared hosting providers support SFTP access:

1. Check your hosting control panel for SFTP credentials
2. Use the same credentials as your hosting account
3. Set `SFTP_PATH` to a directory within your hosting space
4. Ensure the directory is outside your web root for security

## Security Best Practices

### 1. Use SSH Key Authentication
- Generate strong SSH keys (RSA 4096-bit or Ed25519)
- Protect private keys with passphrases
- Regularly rotate SSH keys

### 2. Server Security
```bash
# Disable password authentication (after setting up keys)
sudo nano /etc/ssh/sshd_config
# Set: PasswordAuthentication no

# Change default SSH port (optional)
# Set: Port 2222

# Restart SSH service
sudo systemctl restart ssh
```

### 3. File Permissions
```bash
# Secure storage directory
chmod 700 /home/username/storage
chmod 600 /home/username/storage/.metadata/*
```

### 4. Network Security
- Use VPN or private networks when possible
- Configure firewall rules to restrict SSH access
- Consider using fail2ban to prevent brute force attacks

## Troubleshooting

### Connection Issues

**"SFTP connection failed"**
- Verify host, port, and credentials
- Check if SSH service is running on the server
- Verify firewall settings

**"Authentication failed"**
- Check username and password/key
- Verify SSH key permissions (600 for private key)
- Check if public key is in `~/.ssh/authorized_keys`

### Permission Issues

**"Failed to create directory"**
- Check user permissions on SFTP server
- Verify the user owns the storage directory
- Check directory permissions (755 recommended)

**"Failed to upload file"**
- Check available disk space on SFTP server
- Verify write permissions
- Check file size limits

### Performance Issues

**Slow uploads/downloads**
- Check network bandwidth between servers
- Consider using compression (built into SFTP)
- Optimize file sizes before upload

## Monitoring and Maintenance

### 1. Log Monitoring
```bash
# Monitor SSH logs on SFTP server
sudo tail -f /var/log/auth.log

# Monitor application logs
tail -f /var/log/apache2/error.log
```

### 2. Disk Space Monitoring
```bash
# Check disk usage on SFTP server
df -h /home/username/storage

# Set up alerts for low disk space
```

### 3. Connection Health
The application automatically handles connection issues and will attempt to reconnect if the SFTP connection is lost.

## Comparison with Other Storage Types

| Feature | SFTP | Local | AWS S3 |
|---------|------|-------|---------|
| Setup Complexity | Medium | Low | High |
| Cost | Server costs | Free | Pay per use |
| Scalability | Server dependent | Limited | Unlimited |
| Reliability | Server dependent | Single point | 99.999999999% |
| Security | Full control | Full control | AWS managed |
| Network Dependency | Yes | No | Yes |
| Backup | Manual/scripted | Manual/scripted | Built-in |

## Example Use Cases

1. **Development Environment**: Use a development server as SFTP storage
2. **Multi-server Setup**: Share storage between multiple application servers
3. **Backup Integration**: Use existing backup servers as storage
4. **Hybrid Cloud**: Combine local processing with remote storage
5. **Cost Optimization**: Use existing infrastructure instead of cloud storage

This SFTP storage option provides a flexible middle ground between local storage and cloud storage, giving you the benefits of remote storage while maintaining full control over your infrastructure.