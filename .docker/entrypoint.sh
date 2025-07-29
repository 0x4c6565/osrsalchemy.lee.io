#!/bin/bash
set -e

role=${1:-"app"}

tail -F /var/www/html/storage/logs/laravel.log &
php artisan migrate:fresh

if [ "$role" = "app" ]; then
    exec apache2-foreground
elif [ "$role" = "scheduler" ]; then
    php artisan schedule:work
else
    echo "Unknown role: $role"
    exit 1
fi