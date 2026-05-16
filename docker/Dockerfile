# NPM asset build
FROM --platform=$BUILDPLATFORM node:24
COPY --exclude=docker/ . /app
WORKDIR /app
RUN npm ci && npm run build

# Composer dependancies build
FROM --platform=$BUILDPLATFORM composer:2
COPY --exclude=docker/ . /app
WORKDIR /app
RUN composer install --no-dev

######################
# Main app container #
######################

FROM alpine:3.23

# Copy our app files
# Targeted to ensure we're only copying over what's needed
COPY app /app/app
COPY bootstrap /app/bootstrap
COPY config /app/config
COPY database/migrations /app/database/migrations
COPY public /app/public
COPY resources /app/resources
COPY routes /app/routes
COPY storage /app/storage
COPY artisan /app/artisan
COPY docker /app/docker
COPY composer.json /app/composer.json
COPY composer.lock /app/composer.lock
WORKDIR /app

# Copy requirements from other containers
COPY --from=0 /app/public/build /app/public/build
COPY --from=1 /app/vendor /app/vendor

# Install dependencies
# Then make required files changes using passed-though files
# Then create a user to run things as
# Then setup crontab
# Then run any app-side commands
RUN set -xe && \
        apk add --no-cache supervisor caddy \
                php85 php85-common php85-curl php85-dom php85-fileinfo php85-fpm \
                php85-mbstring php85-openssl php85-pdo php85-pdo_sqlite  \
                php85-session  php85-simplexml  php85-sqlite3 php85-tokenizer php85-xml && \
    cp docker/.env.container /app/.env && \
    chmod +x /app/docker/run.sh && \
    adduser -S www -h /var/lib/www && \
    crontab -u www /app/docker/cron && \
    chown -R www /var/log/php85 && \
    php85 artisan key:generate && \
    php85 artisan route:cache

# Run our process wrapper script
EXPOSE 80/tcp
CMD /app/docker/run.sh
