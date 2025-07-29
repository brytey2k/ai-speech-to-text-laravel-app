# Speech-to-Text Application Setup Instructions

This document provides instructions for setting up the Speech-to-Text application on an Ubuntu server without using Docker.

## Overview

The `build.sh` script automates the setup process for the Speech-to-Text application on an Ubuntu server. It installs all necessary dependencies, configures the environment, and sets up the application to run with Supervisor.

## Requirements

- Ubuntu server (preferably Ubuntu 22.04 LTS or Ubuntu 24.04 LTS)
- Root access to the server
- Internet connection to download packages

## What the Script Does

The `build.sh` script performs the following tasks:

1. **Interactive Configuration**
   - Prompts for database credentials (name, username, password)
   - Prompts for Redis credentials (host, port, password)

2. **System Setup**
   - Sets the timezone to UTC
   - Installs system dependencies (curl, git, etc.)
   - Adds necessary repositories

3. **PHP Setup**
   - Installs PHP 8.4 and required extensions
   - Configures PHP with custom settings

4. **Database Setup**
   - Installs MySQL server
   - Creates a database and user with the provided credentials
   - Configures the application to use the database

5. **Redis Setup**
   - Installs Redis server
   - Configures Redis with the provided credentials
   - Sets up the application to use Redis for queue processing and caching

6. **Application Setup**
   - Sets appropriate file permissions
   - Installs Composer dependencies
   - Configures the environment file
   - Generates application key
   - Runs database migrations
   - Installs Node.js dependencies
   - Builds frontend assets
   - Installs and configures Laravel Octane with FrankenPHP
   - Installs and configures Laravel Reverb for WebSockets

7. **Process Management**
   - Sets up Supervisor to manage:
     - Laravel Octane with FrankenPHP (main application)
     - Queue worker
     - Reverb WebSocket server

## Usage Instructions

1. **Copy the application files to your server**

   ```bash
   # Example using scp
   scp -r /path/to/local/speech-to-text user@your-server-ip:/path/on/server
   ```

2. **Navigate to the application directory**

   ```bash
   cd /path/on/server/speech-to-text
   ```

3. **Make the script executable (if not already)**

   ```bash
   chmod +x build.sh
   ```

4. **Run the script as root**

   ```bash
   sudo ./build.sh
   ```

5. **Respond to the prompts**

   The script will ask you to provide:
   - Database name (default: speech_to_text)
   - Database username (default: laravel)
   - Database password (default: password)
   - Redis host (default: 127.0.0.1)
   - Redis port (default: 6379)
   - Redis password (optional)
   
   You can press Enter to accept the default values or enter your own values.

6. **Wait for the script to complete**

   The script will display progress messages as it runs. This may take some time depending on your server's speed and internet connection.

7. **Access the application**

   Once the script completes successfully, you can access the application at:
   
   ```
   http://your-server-ip
   ```

   The WebSocket server will be running on port 8080.

## Customization

The script now provides interactive prompts for the most common customization options:

- **Database credentials**: You can specify the database name, username, and password during installation
- **Redis configuration**: You can specify the Redis host, port, and password during installation

If you need additional customizations, you can:

1. **Respond to the interactive prompts** during installation to provide your preferred configuration values

2. **Edit the `build.sh` script** before running it for more advanced customizations:
   - **Port configuration**: Change the ports in the Supervisor configuration files
   - **PHP settings**: Modify the PHP configuration in the script
   - **Default values**: Change the default values for the interactive prompts

3. **Edit the environment file** after installation for application-specific settings:
   ```bash
   sudo nano /path/on/server/speech-to-text/.env
   ```

## Troubleshooting

If you encounter issues during installation:

1. **Check the logs**

   ```bash
   # Check Supervisor logs
   sudo tail -f /var/log/supervisor/laravel-octane.log
   sudo tail -f /var/log/supervisor/laravel-queue.log
   sudo tail -f /var/log/supervisor/laravel-reverb.log
   
   # Check Laravel logs
   sudo tail -f /path/on/server/speech-to-text/storage/logs/laravel.log
   ```

2. **Verify services are running**

   ```bash
   # Check MySQL
   sudo systemctl status mysql
   
   # Check Redis
   sudo systemctl status redis-server
   
   # Check Supervisor
   sudo systemctl status supervisor
   sudo supervisorctl status
   ```

3. **Restart services if needed**

   ```bash
   sudo supervisorctl restart all
   ```

## Security Considerations

This script is designed for initial setup. Before using the application in production, consider:

1. Configuring a firewall (UFW)
2. Setting up HTTPS with Let's Encrypt
3. Restricting file permissions further

## Maintenance

To update the application in the future:

1. Pull the latest code
2. Run Composer and npm updates
3. Run migrations
4. Rebuild assets
5. Restart Supervisor processes

```bash
cd /path/on/server/speech-to-text
git pull
composer install --optimize-autoloader
npm install
npm run build
php artisan migrate
sudo supervisorctl restart all
```
