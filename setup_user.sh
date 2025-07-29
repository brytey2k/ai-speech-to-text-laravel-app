#!/bin/bash

# setup_user.sh
# This script creates a normal user and sets up SSH with a specified email as the identity
# It then prints out the SSH public key

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
    echo "Usage: sudo $0 <username>"
    echo "  <username>    The username for the new user to create"
    echo ""
    echo "This script will:"
    echo "  1. Create a new user with the specified username"
    echo "  2. Set up SSH with brytey2k@gmail.com as the identity"
    echo "  3. Print out the SSH public key"
}

# Check if script is run as root
if [ "$(id -u)" -ne 0 ]; then
    print_error "This script must be run as root"
    exit 1
fi

# Check if username is provided
if [ $# -lt 1 ]; then
    print_error "Username is required"
    print_usage
    exit 1
fi

USERNAME="$1"

# Check if the user already exists
if id -u "$USERNAME" &>/dev/null; then
    print_warning "User '$USERNAME' already exists"
else
    # Create the user
    print_status "Creating user '$USERNAME'"
    useradd -m -s /bin/bash "$USERNAME"

    # Set a password for the user (optional - can be removed if not needed)
    # print_status "Setting password for user '$USERNAME'"
    # passwd "$USERNAME"
fi

# Ensure the user's home directory exists and has correct permissions
USER_HOME=$(eval echo ~$USERNAME)
if [ ! -d "$USER_HOME" ]; then
    print_error "Home directory for '$USERNAME' does not exist"
    exit 1
fi

# Create .ssh directory if it doesn't exist
SSH_DIR="$USER_HOME/.ssh"
if [ ! -d "$SSH_DIR" ]; then
    print_status "Creating .ssh directory"
    mkdir -p "$SSH_DIR"
fi

# Set proper permissions for .ssh directory
chmod 700 "$SSH_DIR"
chown "$USERNAME:$USERNAME" "$SSH_DIR"

# Generate SSH key if it doesn't exist
SSH_KEY="$SSH_DIR/id_rsa"
if [ ! -f "$SSH_KEY" ]; then
    print_status "Generating SSH key with identity 'brytey2k@gmail.com'"
    sudo -u "$USERNAME" ssh-keygen -t rsa -b 4096 -C "brytey2k@gmail.com" -f "$SSH_KEY" -N ""
else
    print_warning "SSH key already exists at $SSH_KEY"
fi

# Print the public key
print_status "SSH public key:"
echo ""
cat "$SSH_DIR/id_rsa.pub"
echo ""

# Set proper permissions for SSH files
chmod 600 "$SSH_DIR/id_rsa"
chmod 644 "$SSH_DIR/id_rsa.pub"
chown "$USERNAME:$USERNAME" "$SSH_DIR/id_rsa" "$SSH_DIR/id_rsa.pub"

print_status "User setup complete!"
print_status "Username: $USERNAME"
print_status "SSH key generated with identity: brytey2k@gmail.com"
print_status "SSH public key has been printed above"
