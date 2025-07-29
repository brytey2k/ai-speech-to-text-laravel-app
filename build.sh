#!/bin/bash

# Speech-to-Text Application Setup Script
# This script sets up the Speech-to-Text application on an Ubuntu server
# without using Docker, but following a similar setup to the Docker configuration.

set -e  # Exit immediately if a command exits with a non-zero status

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Function to print status messages
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

# Function to print warning messages
print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Function to print error messages
print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if script is run as root
if [ "$(id -u)" -ne 0 ]; then
    print_error "This script must be run as root"
    exit 1
fi

# Get the directory of the script
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
APP_DIR="$SCRIPT_DIR"

print_status "Setting up Speech-to-Text application in $APP_DIR"

# Set timezone
print_status "Setting timezone to UTC"
ln -snf /usr/share/zoneinfo/UTC /etc/localtime
echo "UTC" > /etc/timezone

# Create app_user
print_status "Creating app_user for application ownership"
if ! id -u app_user &>/dev/null; then
    useradd -m -s /bin/bash app_user
    usermod -aG www-data app_user
    print_status "Created app_user successfully"
else
    print_status "app_user already exists"
fi

# Update package lists
print_status "Updating package lists"
apt-get update

# Install system dependencies
print_status "Installing system dependencies"
apt-get install -y gnupg curl ca-certificates zip unzip git supervisor sqlite3 libcap2-bin \
    libpng-dev python3 dnsutils librsvg2-bin fswatch ffmpeg nano

# Add PHP repository
print_status "Adding PHP repository"
mkdir -p /etc/apt/keyrings
curl -sS 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0xb8dc7e53946656efbce4c1dd71daeaab4ad4cab6' | gpg --dearmor | tee /etc/apt/keyrings/ppa_ondrej_php.gpg > /dev/null
echo "deb [signed-by=/etc/apt/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu $(lsb_release -cs) main" > /etc/apt/sources.list.d/ppa_ondrej_php.list

# Update package lists again
apt-get update

# Install PHP and extensions
print_status "Installing PHP 8.4 and extensions"
apt-get install -y php8.4-cli php8.4-dev \
    php8.4-pgsql php8.4-sqlite3 php8.4-gd \
    php8.4-curl php8.4-mongodb \
    php8.4-imap php8.4-mysql php8.4-mbstring \
    php8.4-xml php8.4-zip php8.4-bcmath php8.4-soap \
    php8.4-intl php8.4-readline \
    php8.4-ldap \
    php8.4-msgpack php8.4-igbinary php8.4-redis php8.4-swoole \
    php8.4-memcached php8.4-pcov php8.4-imagick php8.4-xdebug

# Configure PHP
print_status "Configuring PHP"
cat > /etc/php/8.4/cli/conf.d/99-app.ini << 'EOF'
[PHP]
post_max_size = 100M
upload_max_filesize = 100M
variables_order = EGPCS
pcov.directory = .
EOF

# Install Composer
print_status "Installing Composer"
curl -sLS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer

# Install Node.js
print_status "Installing Node.js"
NODE_VERSION=22
curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg
echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_VERSION.x nodistro main" > /etc/apt/sources.list.d/nodesource.list
apt-get update
apt-get install -y nodejs
npm install -g npm

# Prompt for all credentials upfront
print_status "Setting up credentials"

# Database credentials
print_status "Database credentials"
read -p "Enter database name [speech_to_text]: " DB_NAME
DB_NAME=${DB_NAME:-speech_to_text}

read -p "Enter database username [laravel]: " DB_USER
DB_USER=${DB_USER:-laravel}

read -p "Enter database password [password]: " DB_PASSWORD
DB_PASSWORD=${DB_PASSWORD:-password}

# Redis credentials
print_status "Redis credentials"
read -p "Enter Redis host [127.0.0.1]: " REDIS_HOST
REDIS_HOST=${REDIS_HOST:-127.0.0.1}

read -p "Enter Redis port [6379]: " REDIS_PORT
REDIS_PORT=${REDIS_PORT:-6379}

read -p "Enter Redis password (leave empty for none): " REDIS_PASSWORD
REDIS_PASSWORD=${REDIS_PASSWORD:-null}

# Install MySQL
print_status "Installing MySQL"
apt-get install -y mysql-server

# Start MySQL service
print_status "Starting MySQL service"
systemctl start mysql
systemctl enable mysql

# Create database
print_status "Creating MySQL database"
mysql -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\`;"
mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASSWORD';"
mysql -e "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';"
mysql -e "FLUSH PRIVILEGES;"

# Install Redis
print_status "Installing Redis"
apt-get install -y redis-server

# Configure Redis if password is provided
if [ "$REDIS_PASSWORD" != "null" ]; then
    print_status "Configuring Redis with password"
    sed -i "s/# requirepass foobared/requirepass $REDIS_PASSWORD/" /etc/redis/redis.conf
fi

# Configure Redis port if not default
if [ "$REDIS_PORT" != "6379" ]; then
    print_status "Configuring Redis port to $REDIS_PORT"
    sed -i "s/port 6379/port $REDIS_PORT/" /etc/redis/redis.conf
fi

# Start Redis service
print_status "Starting Redis service"
systemctl restart redis-server
systemctl enable redis-server

# Set up application
cd "$APP_DIR"

# Set permissions
print_status "Setting permissions"
chown -R app_user:www-data "$APP_DIR"
find "$APP_DIR" -type f -exec chmod 644 {} \;
find "$APP_DIR" -type d -exec chmod 755 {} \;

# Set special permissions for Laravel directories that need write access
print_status "Setting special permissions for Laravel directories"
chmod -R 775 "$APP_DIR/storage"
chmod -R 775 "$APP_DIR/bootstrap/cache"

# Install Composer dependencies
print_status "Installing Composer dependencies"
sudo -u app_user composer install --no-interaction --prefer-dist --optimize-autoloader

# Set up environment file
print_status "Setting up environment file"
cp .env.example .env
sed -i "s/DB_DATABASE=speech_to_text/DB_DATABASE=$DB_NAME/" .env
sed -i "s/DB_USERNAME=root/DB_USERNAME=$DB_USER/" .env
sed -i "s/DB_PASSWORD=/DB_PASSWORD=$DB_PASSWORD/" .env
sed -i "s/REDIS_HOST=127.0.0.1/REDIS_HOST=$REDIS_HOST/" .env
sed -i "s/REDIS_PORT=6379/REDIS_PORT=$REDIS_PORT/" .env
sed -i "s/REDIS_PASSWORD=null/REDIS_PASSWORD=$REDIS_PASSWORD/" .env
chown app_user:www-data .env

# Reminder to update other important values in .env file
print_warning "IMPORTANT: Please review and update other necessary values in the .env file, such as:"
print_warning "- APP_NAME, APP_ENV, APP_URL (Application settings)"
print_warning "- OPEN_AI_WHISPER_API_KEY (Required for speech-to-text functionality)"
print_warning "- REVERB_APP_ID, REVERB_APP_KEY, REVERB_APP_SECRET, REVERB_HOST (WebSocket settings)"
print_warning "- AWS credentials (If using AWS services)"
print_warning "- Mail configuration (If email functionality is needed)"

# Generate application key
print_status "Generating application key"
sudo -u app_user php artisan key:generate

# Run migrations
print_status "Running database migrations"
sudo -u app_user php artisan migrate --force

# Install Node.js dependencies
print_status "Installing Node.js dependencies"
sudo -u app_user npm install

# Build assets
print_status "Building assets"
sudo -u app_user npm run build

# Create storage link
print_status "Creating storage link"
sudo -u app_user php artisan storage:link

# Install and configure Laravel Octane
print_status "Installing and configuring Laravel Octane"
sudo -u app_user php artisan octane:install --server=frankenphp

# Install and configure Laravel Reverb
print_status "Installing and configuring Laravel Reverb"
sudo -u app_user php artisan reverb:install

# Set up Supervisor configuration
print_status "Setting up Supervisor configuration"

# Main application (Laravel Octane with FrankenPHP)
cat > /etc/supervisor/conf.d/laravel-octane.conf << EOF
[program:laravel-octane]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php -d variables_order=EGPCS $APP_DIR/artisan octane:start --server=frankenphp --host=0.0.0.0 --port=80
autostart=true
autorestart=true
user=app_user
redirect_stderr=true
stdout_logfile=/var/log/supervisor/laravel-octane.log
stopwaitsecs=3600
EOF

# Queue worker
cat > /etc/supervisor/conf.d/laravel-queue.conf << EOF
[program:laravel-queue]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php $APP_DIR/artisan queue:listen --tries=3 --backoff=3 --queue=default
autostart=true
autorestart=true
user=app_user
redirect_stderr=true
stdout_logfile=/var/log/supervisor/laravel-queue.log
stopwaitsecs=3600
EOF

# Reverb WebSocket server
cat > /etc/supervisor/conf.d/laravel-reverb.conf << EOF
[program:laravel-reverb]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php $APP_DIR/artisan reverb:start --host=0.0.0.0 --port=8080
autostart=true
autorestart=true
user=app_user
redirect_stderr=true
stdout_logfile=/var/log/supervisor/laravel-reverb.log
stopwaitsecs=3600
EOF

# Update and restart Supervisor
print_status "Updating and restarting Supervisor"
supervisorctl reread
supervisorctl update
supervisorctl restart all

print_status "Setup complete! The application should now be running."
print_status "You can access it at http://your-server-ip"
print_status "WebSocket server is running on port 8080"
