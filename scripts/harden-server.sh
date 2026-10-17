#!/bin/bash
# Server Hardening Script for Cooperative Application
# This script helps set up Fail2Ban on the host machine to monitor Nginx logs from the Docker container.

# Check for root
if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root"
   exit 1
fi

echo "--- 1. Installing Fail2Ban ---"
apt-get update && apt-get install -y fail2ban

echo "--- 2. Configuring Nginx Probes Filter ---"
cp security/fail2ban/nginx-probes.conf /etc/fail2ban/filter.d/

echo "--- 3. Configuring Jail ---"
# Check if jail.local already exists and append or create
if [ -f /etc/fail2ban/jail.local ]; then
    cat security/fail2ban/jail.local >> /etc/fail2ban/jail.local
else
    cp security/fail2ban/jail.local /etc/fail2ban/jail.local
fi

echo "--- 4. Instructions for Nginx Log Mounting ---"
echo "To allow Fail2Ban to see Nginx logs, you must mount the Nginx logs to the host."
echo "Your docker-compose.pro.yml has been updated to include:"
echo ""
echo "    volumes:"
echo "      - ./logs/nginx:/var/log/nginx"
echo ""
echo "Please verify the absolute path to your application directory."
APP_PATH=$(pwd)
echo "Setting logpath in /etc/fail2ban/jail.local to: $APP_PATH/logs/nginx/access.log"
sed -i "s|logpath = /var/log/nginx/access.log|logpath = $APP_PATH/logs/nginx/access.log|" /etc/fail2ban/jail.local

echo "--- 5. Restarting Fail2Ban ---"
systemctl restart fail2ban
fail2ban-client status nginx-probes

echo "--- Done! ---"
