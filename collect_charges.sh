#!/bin/bash
# Script to collect administrative charges on the VPS
docker compose exec app php backend/artisan admin-charges:collect
