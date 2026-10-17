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
# Check if jail.local already exists and has the nginx-probes section
if [ -f /etc/fail2ban/jail.local ] && grep -q "\[nginx-probes\]" /etc/fail2ban/jail.local; then
    echo "nginx-probes jail already configured in /etc/fail2ban/jail.local"
else
    echo "Appending nginx-probes configuration to /etc/fail2ban/jail.local"
    cat security/fail2ban/jail.local >> /etc/fail2ban/jail.local
fi

echo "--- 4. Instructions for Nginx Log Mounting ---"
echo "To allow Fail2Ban to see Nginx logs, you must mount the Nginx logs to the host."
echo "Ensure your docker-compose.pro.yml includes:"
echo ""
echo "    volumes:"
echo "      - ./logs/nginx:/var/log/nginx"
echo ""
APP_PATH=$(pwd)
echo "Updating logpath in /etc/fail2ban/jail.local to: $APP_PATH/logs/nginx/access.log"
# Use a more specific sed to only update the nginx-probes logpath
sed -i "/\[nginx-probes\]/,/logpath/ s|logpath = .*|logpath = $APP_PATH/logs/nginx/access.log|" /etc/fail2ban/jail.local

echo "--- 5. Restarting Fail2Ban ---"
systemctl restart fail2ban

# Wait for Fail2Ban socket to be available (up to 20 seconds)
echo "Waiting for Fail2Ban socket to become available..."
for i in {1..20}; do
    if [ -S /var/run/fail2ban/fail2ban.sock ]; then
        break
    fi
    sleep 1
done

fail2ban-client status nginx-probes

echo ""
echo "--- IMPORTANT NEXT STEPS ---"
echo "1. Rebuild and restart your Docker containers to apply Nginx security rules:"
echo "   docker compose -f docker-compose.pro.yml up -d --build"
echo "2. Check Nginx logs are being created on the host:"
echo "   ls -l $APP_PATH/logs/nginx/access.log"
echo "3. Verify Fail2Ban can read the logs:"
echo "   fail2ban-client status nginx-probes"
echo ""
echo "--- Done! ---"
