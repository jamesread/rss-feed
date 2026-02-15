# NPM asset build
FROM --platform=$BUILDPLATFORM node:22
COPY . /app
WORKDIR /app
RUN npm ci && npm run build

# Composer dependancies build
FROM --platform=$BUILDPLATFORM composer:2
COPY . /app
WORKDIR /app
RUN composer install --no-dev

######################
# Main app container #
######################

FROM ubuntu:24.04
ARG DEBIAN_FRONTEND=noninteractive

# Copy our app files
COPY . /app
WORKDIR /app

# Copy requirements from other containers
COPY --from=0 /app/public/build /app/public/build
COPY --from=1 /app/vendor /app/vendor

# Install dependencies
# Then cleanup apt package files
# Then make required files changes using passed-though files
# Then create directory for PHP-FPM socket
# Then setup crontab
# Then run any app-side commands
RUN set -xe && \
        apt-get update -yqq && \
        apt-get install curl supervisor nginx-light cron -yqq  && \
        apt-get install php8.3-cli php8.3-fpm php8.3-common php8.3-curl php8.3-mbstring \
                        php8.3-xml php8.3-zip php8.3-gd php8.3-sqlite3 php8.3-bcmath -yqq && \
    rm -rf /var/lib/apt/lists/* && \
    cp docker/.env.container /app/.env && \
    cp docker/nginx.conf /etc/nginx/sites-enabled/rss.conf && \
    rm /etc/nginx/sites-enabled/default && \
    mkdir -p /run/php && \
    chmod +x /app/docker/run.sh && \
    crontab -u www-data /app/docker/cron && \
    php artisan key:generate && \
    php artisan route:cache

# Run our process wrapper script
EXPOSE 80/tcp
CMD /app/docker/run.sh
