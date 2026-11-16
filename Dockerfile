FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql

# Railway/Nixpacks can enable an event/worker MPM alongside the PHP Apache
# image's prefork MPM. Keep exactly one MPM to avoid AH00534 at container start.
RUN a2dismod mpm_event mpm_worker 2>/dev/null || true
RUN a2enmod mpm_prefork rewrite

WORKDIR /var/www/html
COPY . /var/www/html/
COPY docker-start.sh /usr/local/bin/dropit-start
RUN chmod +x /usr/local/bin/dropit-start

RUN chown -R www-data:www-data /var/www/html

CMD ["/usr/local/bin/dropit-start"]

ENV APP_ENV=production

EXPOSE 80
