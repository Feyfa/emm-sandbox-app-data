#!/bin/bash

echo "🔧 Fix CRLF untuk docker-entrypoint (frontend)..."
cd ../front || exit
dos2unix docker-entrypoint.sh 2>/dev/null
cd ../data || exit

echo "🔧 Fix permission WSL host (Laravel)..."
sudo chown -R $USER:$USER .
chmod -R u+rwX,g+rwX .
chmod -R 2775 storage bootstrap/cache

echo "🚀 Fix permission di container Laravel..."
docker exec -it emm_sandbox_app_data_app bash -c "
chown -R www-data:www-data storage bootstrap/cache &&
chmod -R 775 storage bootstrap/cache &&
chmod 666 storage/logs/laravel.log 2>/dev/null &&
chmod -R 777 storage/logs
"

echo "✅ Setup selesai — environment siap dipakai 🔥"