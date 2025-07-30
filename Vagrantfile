# -*- mode: ruby -*-
# vi: set ft=ruby :

# All Vagrant configuration is done below. The "2" in Vagrant.configure
# configures the configuration version (we support older styles for
# backwards compatibility). Please don't change it unless you know what
# you're doing.
Vagrant.configure("2") do |config|
    config.vm.box = "bento/ubuntu-24.04"
    config.vm.hostname = "ubuntu-vm"

    config.vm.provider "parallels" do |p|
        # Customize the amount of memory on the VM:
        p.memory = "2048"
        p.cpus = 2
    end

    config.vm.network "forwarded_port", guest: 80, host: 80, host_ip: "127.0.0.1"

    config.vm.provision "shell", inline: <<-SHELL
        # Create the user if it doesn't exist
        if ! id "app_user" &>/dev/null; then
            echo "Creating user app_user..."
            useradd -m -s /bin/bash app_user
        fi

        # Create .ssh directory if it doesn't exist
        mkdir -p /home/app_user/.ssh
        chown app_user:app_user /home/app_user/.ssh
        chmod 700 /home/app_user/.ssh

        # Generate ed25519 SSH key with email comment
        if [ ! -f /home/app_user/.ssh/id_ed25519 ]; then
            echo "Generating SSH key for app_user with comment..."
            sudo -u app_user ssh-keygen -t ed25519 -C "brytey2k@gmail.com" -N "" -f /home/app_user/.ssh/id_ed25519
        else
            echo "SSH key for app_user already exists. Skipping keygen."
        fi

        # Set permissions
        chown app_user:app_user /home/app_user/.ssh/id_ed25519*
        chmod 600 /home/app_user/.ssh/id_ed25519
        chmod 644 /home/app_user/.ssh/id_ed25519.pub
    SHELL
end
