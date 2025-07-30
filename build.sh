#!/bin/bash

# Speech-to-Text Application Setup Script
# This script sets up the Speech-to-Text application on an Ubuntu server
# without using Docker, but following a similar setup to the Docker configuration.
#
# Usage: sudo ./build.sh -u <username> -e <environment> [-d <domain>]
#   where <username> is the normal user that will own the application files
#   and run composer, npm, and php artisan commands.
#   <environment> is either 'staging' or 'production'
#   <domain> is required when environment is 'production'

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

# Function to print usage information
print_usage() {
    echo "Usage: sudo $0 -u <username> -e <environment> [-d <domain>]"
    echo "  -u, --user         Username of the normal user that will own the application"
    echo "  -e, --environment  Environment (staging or production)"
    echo "  -d, --domain       Domain name (required for production environment)"
    echo "  -h, --help         Display this help message"
    echo ""
    echo "The specified user must already exist on the system."
    echo "This user will own the application files and run composer, npm, and php artisan commands."
    echo "Admin commands like installing packages will be run by the sudo user (the one running this script)."
    echo ""
    echo "For staging environment, mkcert will be used to generate SSL certificates."
    echo "For production environment, Let's Encrypt will be used to generate SSL certificates for the specified domain."
}

# Parse command line arguments
USERNAME=""
ENVIRONMENT=""
DOMAIN=""
while [[ $# -gt 0 ]]; do
    case $1 in
        -u|--user)
            USERNAME="$2"
            shift 2
            ;;
        -e|--environment)
            ENVIRONMENT="$2"
            shift 2
            ;;
        -d|--domain)
            DOMAIN="$2"
            shift 2
            ;;
        -h|--help)
            print_usage
            exit 0
            ;;
        *)
            print_error "Unknown option: $1"
            print_usage
            exit 1
            ;;
    esac
done

# Check if username is provided
if [ -z "$USERNAME" ]; then
    print_error "Username is required"
    print_usage
    exit 1
fi

# Check if environment is provided and valid
if [ -z "$ENVIRONMENT" ]; then
    print_error "Environment is required"
    print_usage
    exit 1
fi

if [ "$ENVIRONMENT" != "staging" ] && [ "$ENVIRONMENT" != "production" ]; then
    print_error "Environment must be either 'staging' or 'production'"
    print_usage
    exit 1
fi

# Check if domain is provided for production environment
if [ "$ENVIRONMENT" = "production" ] && [ -z "$DOMAIN" ]; then
    print_error "Domain is required for production environment"
    print_usage
    exit 1
fi

# Check if script is run as root
if [ "$(id -u)" -ne 0 ]; then
    print_error "This script must be run as root"
    exit 1
fi

# Check if the specified user exists
if ! id -u "$USERNAME" &>/dev/null; then
    print_error "User '$USERNAME' does not exist. Please create this user first."
    exit 1
fi

# Ensure the user is in the www-data group
if ! groups "$USERNAME" | grep -q "www-data"; then
    print_status "Adding user '$USERNAME' to www-data group"
    usermod -aG www-data "$USERNAME"
fi

# Get the directory of the script
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" &> /dev/null && pwd )"
APP_DIR="$SCRIPT_DIR"

print_status "Setting up Speech-to-Text application in $APP_DIR"

# Set timezone
print_status "Setting timezone to UTC"
ln -snf /usr/share/zoneinfo/UTC /etc/localtime
echo "UTC" > /etc/timezone

# Ensure user's home directory has correct permissions
USER_HOME=$(eval echo ~$USERNAME)
print_status "Ensuring home directory for $USERNAME has correct permissions"
if [ ! -d "$USER_HOME" ]; then
    print_error "Home directory for $USERNAME does not exist. Please check the user setup."
    exit 1
fi
chmod 755 "$USER_HOME"

# Update package lists
print_status "Updating package lists"
apt-get update

# Install system dependencies
print_status "Installing system dependencies"
apt-get install -y gnupg curl ca-certificates zip unzip git supervisor sqlite3 libcap2-bin \
    libpng-dev python3 dnsutils librsvg2-bin fswatch ffmpeg nano nginx

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
print_status "Setting permissions for $USERNAME as owner and www-data as group"
chown -R $USERNAME:www-data "$APP_DIR"
find "$APP_DIR" -type f -exec chmod 644 {} \;
find "$APP_DIR" -type d -exec chmod 755 {} \;

# Set special permissions for Laravel directories that need write access
print_status "Setting special permissions for Laravel directories"
chmod -R 775 "$APP_DIR/storage"
chmod -R 775 "$APP_DIR/bootstrap/cache"

# Install Composer dependencies
print_status "Installing Composer dependencies as $USERNAME"
sudo -u $USERNAME composer install --no-interaction --prefer-dist --optimize-autoloader

# Set up environment file
print_status "Setting up environment file"
cp .env.example .env
sed -i "s/DB_DATABASE=speech_to_text/DB_DATABASE=$DB_NAME/" .env
sed -i "s/DB_USERNAME=root/DB_USERNAME=$DB_USER/" .env
sed -i "s/DB_PASSWORD=/DB_PASSWORD=$DB_PASSWORD/" .env
sed -i "s/REDIS_HOST=127.0.0.1/REDIS_HOST=$REDIS_HOST/" .env
sed -i "s/REDIS_PORT=6379/REDIS_PORT=$REDIS_PORT/" .env
sed -i "s/REDIS_PASSWORD=null/REDIS_PASSWORD=$REDIS_PASSWORD/" .env
chown $USERNAME:www-data .env

# Reminder to update other important values in .env file
print_warning "IMPORTANT: Please review and update other necessary values in the .env file, such as:"
print_warning "- APP_NAME, APP_ENV, APP_URL (Application settings)"
print_warning "- OPEN_AI_WHISPER_API_KEY (Required for speech-to-text functionality)"
print_warning "- REVERB_APP_ID, REVERB_APP_KEY, REVERB_APP_SECRET, REVERB_HOST (WebSocket settings)"
print_warning "- AWS credentials (If using AWS services)"
print_warning "- Mail configuration (If email functionality is needed)"

# Generate application key
print_status "Generating application key as $USERNAME"
sudo -u $USERNAME php artisan key:generate

# Run migrations
print_status "Running database migrations as $USERNAME"
sudo -u $USERNAME php artisan migrate --force

# Install Node.js dependencies
print_status "Installing Node.js dependencies as $USERNAME"
sudo -u $USERNAME npm install

# Build assets
print_status "Building assets as $USERNAME"
sudo -u $USERNAME npm run build

# Create storage link
print_status "Creating storage link as $USERNAME"
sudo -u $USERNAME php artisan storage:link

# Install and configure Laravel Octane
print_status "Installing and configuring Laravel Octane as $USERNAME"
sudo -u $USERNAME php artisan octane:install --server=frankenphp

# Install and configure Laravel Reverb
print_status "Installing and configuring Laravel Reverb as $USERNAME"
sudo -u $USERNAME php artisan reverb:install

# Set up SSL certificates based on environment
if [ "$ENVIRONMENT" = "staging" ]; then
    print_status "Setting up SSL certificates for staging environment using mkcert"

    # Install mkcert dependencies
    apt-get install -y libnss3-tools

    # Install mkcert
    print_status "Installing mkcert"
    MKCERT_VERSION="v1.4.4"

    # Detect system architecture
    ARCH=$(uname -m)
    print_status "Detected system architecture: $ARCH"

    if [ "$ARCH" = "x86_64" ]; then
        print_status "Downloading mkcert for x86_64 architecture"
        wget -O /usr/local/bin/mkcert "https://github.com/FiloSottile/mkcert/releases/download/${MKCERT_VERSION}/mkcert-${MKCERT_VERSION}-linux-amd64"
    elif [ "$ARCH" = "aarch64" ] || [ "$ARCH" = "arm64" ]; then
        print_status "Downloading mkcert for ARM64 architecture"
        wget -O /usr/local/bin/mkcert "https://github.com/FiloSottile/mkcert/releases/download/${MKCERT_VERSION}/mkcert-${MKCERT_VERSION}-linux-arm64"
    elif [ "$ARCH" = "armv7l" ] || [ "$ARCH" = "armhf" ] || [ "$ARCH" = "arm" ]; then
        print_status "Downloading mkcert for 32-bit ARM architecture"
        wget -O /usr/local/bin/mkcert "https://github.com/FiloSottile/mkcert/releases/download/${MKCERT_VERSION}/mkcert-${MKCERT_VERSION}-linux-arm"
    else
        print_warning "Unsupported architecture: $ARCH. Attempting to use amd64 version as fallback."
        print_warning "If this fails, please manually download the appropriate mkcert binary for your architecture from:"
        print_warning "https://github.com/FiloSottile/mkcert/releases/tag/${MKCERT_VERSION}"
        wget -O /usr/local/bin/mkcert "https://github.com/FiloSottile/mkcert/releases/download/${MKCERT_VERSION}/mkcert-${MKCERT_VERSION}-linux-amd64"
    fi

    chmod +x /usr/local/bin/mkcert

    # Create directory for certificates
    mkdir -p /etc/nginx/ssl

    # Generate certificates
    print_status "Generating SSL certificates for localhost"
    mkcert -install
    mkcert -cert-file /etc/nginx/ssl/localhost.crt -key-file /etc/nginx/ssl/localhost.key localhost 127.0.0.1 ::1

    # Configure Nginx with SSL for staging
    print_status "Configuring Nginx with SSL for staging environment"
    cat > /etc/nginx/sites-available/laravel-app << EOF
server {
    listen 80;
    server_name _;
    return 301 https://\$host\$request_uri;
}

server {
    listen 443 ssl;
    server_name _;

    ssl_certificate /etc/nginx/ssl/localhost.crt;
    ssl_certificate_key /etc/nginx/ssl/localhost.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers on;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-SHA384;
    ssl_session_timeout 1d;
    ssl_session_cache shared:SSL:10m;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}
EOF

elif [ "$ENVIRONMENT" = "production" ]; then
    print_status "Setting up SSL certificates for production environment using Let's Encrypt"

    # Install certbot
    print_status "Installing certbot"
    apt-get install -y certbot python3-certbot-nginx

    # Configure Nginx for the domain
    print_status "Configuring Nginx for domain: $DOMAIN"
    cat > /etc/nginx/sites-available/laravel-app << EOF
server {
    listen 80;
    server_name $DOMAIN;

    location / {
        proxy_pass http://127.0.0.1:8000;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}
EOF

    # Enable the Nginx site
    ln -sf /etc/nginx/sites-available/laravel-app /etc/nginx/sites-enabled/
    rm -f /etc/nginx/sites-enabled/default

    # Test Nginx configuration
    nginx -t

    # Restart Nginx
    systemctl restart nginx

    # Obtain SSL certificate
    print_status "Obtaining SSL certificate from Let's Encrypt for $DOMAIN"
    certbot --nginx -d $DOMAIN --non-interactive --agree-tos --email admin@$DOMAIN

    print_status "SSL certificate successfully installed for $DOMAIN"
else
    # This should never happen due to earlier validation
    print_error "Invalid environment: $ENVIRONMENT"
    exit 1
fi

# If not production, enable the Nginx site here (for production it's done before certbot)
if [ "$ENVIRONMENT" != "production" ]; then
    # Enable the Nginx site
    ln -sf /etc/nginx/sites-available/laravel-app /etc/nginx/sites-enabled/
    rm -f /etc/nginx/sites-enabled/default

    # Test Nginx configuration
    nginx -t

    # Restart Nginx
    systemctl restart nginx
fi
systemctl enable nginx

# Set up Supervisor configuration
print_status "Setting up Supervisor configuration"

# Main application (Laravel Octane with FrankenPHP)
cat > /etc/supervisor/conf.d/laravel-octane.conf << EOF
[program:laravel-octane]
process_name=%(program_name)s_%(process_num)02d
command=/usr/bin/php -d variables_order=EGPCS $APP_DIR/artisan octane:start --server=frankenphp --host=0.0.0.0 --admin-port=2019 --port=8000
autostart=true
autorestart=true
user=$USERNAME
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
user=$USERNAME
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
user=$USERNAME
redirect_stderr=true
stdout_logfile=/var/log/supervisor/laravel-reverb.log
stopwaitsecs=3600
EOF

# No longer need to grant PHP the capability to bind to privileged ports
# as nginx is handling port 80 and PHP is running on non-privileged port 8000
print_status "PHP will run on non-privileged port 8000, with nginx as reverse proxy on port 80"

# Update and restart Supervisor
print_status "Updating and restarting Supervisor"
supervisorctl reread
supervisorctl update
supervisorctl restart all

print_status "Setup complete! The application should now be running."
if [ "$ENVIRONMENT" = "staging" ]; then
    print_status "You can access it at https://localhost or https://127.0.0.1"
elif [ "$ENVIRONMENT" = "production" ]; then
    print_status "You can access it at https://$DOMAIN"
fi
print_status "WebSocket server is running on port 8080"
