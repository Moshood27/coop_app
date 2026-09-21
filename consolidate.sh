#!/bin/bash
# Savings Scheme Consolidation Script
# Run this from the project root on the VPS

echo "--------------------------------------------------------"
echo "Attaqwa Coop - Savings Consolidation Tool"
echo "--------------------------------------------------------"

# Run the consolidation command
docker compose exec app php artisan app:consolidate-savings --force

echo "--------------------------------------------------------"
echo "Consolidation and Reconciliation Finished."
echo "--------------------------------------------------------"
