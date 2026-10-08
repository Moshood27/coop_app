#!/bin/bash

# Incremental Backup Script for Cloudflare R2
# This script uses rclone to sync local uploads to R2.

# Required Env Vars:
# R2_ACCESS_KEY_ID
# R2_SECRET_ACCESS_KEY
# R2_ENDPOINT
# R2_BUCKET

if [ -z "$R2_ACCESS_KEY_ID" ] || [ -z "$R2_SECRET_ACCESS_KEY" ] || [ -z "$R2_ENDPOINT" ] || [ -z "$R2_BUCKET" ]; then
    echo "Error: R2 credentials not fully set in environment."
    exit 1
fi

export RCLONE_CONFIG_R2_TYPE=s3
export RCLONE_CONFIG_R2_PROVIDER=Cloudflare
export RCLONE_CONFIG_R2_ACCESS_KEY_ID=$R2_ACCESS_KEY_ID
export RCLONE_CONFIG_R2_SECRET_ACCESS_KEY=$R2_SECRET_ACCESS_KEY
export RCLONE_CONFIG_R2_ENDPOINT=$R2_ENDPOINT
export RCLONE_CONFIG_R2_ACL=private

echo "Starting incremental sync of storage/app/public to R2..."
rclone sync /var/www/html/storage/app/public r2:$R2_BUCKET/storage --progress

echo "Starting incremental sync of public/upload to R2..."
rclone sync /var/www/html/public/upload r2:$R2_BUCKET/upload --progress

echo "Incremental sync completed."
