#!/bin/sh
set -e

# Move to app directory
cd /app || exit

# Setup
mkdir -p /app/storage/database
touch /app/storage/database/database.sqlite
touch /app/storage/feeds.txt
mkdir -p /app/storage/framework/cache
mkdir -p /app/storage/framework/sessions
mkdir -p /app/storage/framework/testing
mkdir -p /app/storage/framework/views

php85 artisan storage:link
php85 artisan migrate --force
php85 artisan config:cache
php85 artisan view:cache

# Set runtime permissions
chown -R www /app

# Run supervisord
supervisord -c /app/docker/services.conf
