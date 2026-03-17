FROM php:8.2-cli

ENV TZ=America/La_Paz

WORKDIR /var/www/html

RUN apt-get update \
    && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends cron tzdata \
    && rm -rf /var/lib/apt/lists/*

COPY . /var/www/html

RUN chmod +x /var/www/html/docker/entrypoint.sh \
    && mkdir -p /var/www/html/storage/cache /var/www/html/storage/logs \
    && touch /var/www/html/storage/logs/update.log \
    && printf "[]\n" > /var/www/html/storage/news.json

EXPOSE 3003

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]