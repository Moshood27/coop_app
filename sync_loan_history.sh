#!/bin/bash
# Script to sync loan repayment history with passbook via Docker
# Run this from the project root

echo "Running loan repayment sync inside Docker container..."
docker compose exec app php artisan app:sync-loan-repayments
