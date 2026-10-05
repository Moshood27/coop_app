# 1. Copy the script into the backend folder where Docker can see it
cp refund_charges.php backend/

# 2. Run the script inside the container for October (Execute)
docker compose exec -T app php refund_charges.php --month=2026-10 --execute

# 3. Run for September (Execute)
docker compose exec -T app php refund_charges.php --month=2026-09 --execute

# 4. Run for August (Execute)
docker compose exec -T app php refund_charges.php --month=2026-08 --execute

# 5. Run for July (Execute)
docker compose exec -T app php refund_charges.php --month=2026-07 --execute
