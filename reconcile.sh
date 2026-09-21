#!/bin/bash
# Financial Reconciliation Script for Attaqwa Coop
# Run this from the project root on the VPS

echo "--------------------------------------------------------"
echo "Attaqwa Coop - Financial Reconciliation Tool"
echo "--------------------------------------------------------"

# Default values
COMMAND="php artisan financials:reconcile"
BRANCH=""
USER=""
FIX=""
ROLLBACK=""

# Parse arguments
while [[ "$#" -gt 0 ]]; do
    case $1 in
        --branch) BRANCH="--branch=$2"; shift ;;
        --user) USER="--user=$2"; shift ;;
        --fix) FIX="--fix" ;;
        --rollback) ROLLBACK="--rollback" ;;
        *) echo "Unknown parameter passed: $1"; exit 1 ;;
    esac
    shift
done

FULL_COMMAND="$COMMAND $FIX $USER $BRANCH $ROLLBACK"

echo "Executing: $FULL_COMMAND inside Docker..."
docker compose exec app $FULL_COMMAND

echo "--------------------------------------------------------"
echo "Done."
