FROM php:8.4-apache-bookworm

ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1

RUN apt-get update && apt-get install -y --no-install-recommends \
      antiword \
      apache2-utils \
      ca-certificates \
      curl \
      git \
      html2text \
      libfreetype6-dev \
      libjpeg62-turbo-dev \
      libldap2-dev \
      libmcrypt-dev \
      libonig-dev \
      libpng-dev \
      libxml2-dev \
      libzip-dev \
      mariadb-client \
      poppler-utils \
      unzip \
      unrtf \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure ldap \
    && docker-php-ext-install -j"$(nproc)" gd ldap mbstring mysqli soap zip \
    && curl -fsSL -o /tmp/mcrypt-1.0.9.tgz https://pecl.php.net/get/mcrypt-1.0.9.tgz \
    && pecl install /tmp/mcrypt-1.0.9.tgz \
    && docker-php-ext-enable mcrypt \
    && a2enmod headers rewrite \
    && rm -rf /var/lib/apt/lists/* /tmp/pear /tmp/mcrypt-1.0.9.tgz

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html
COPY . /var/www/html

RUN composer install \
      --no-dev \
      --no-interaction \
      --no-progress \
      --prefer-dist \
      --optimize-autoloader \
    && mkdir -p /var/data \
    && printf 'ok\n' > /var/www/html/render-health.txt \
    && chown -R www-data:www-data /var/www/html /var/data

COPY docker/render/apache-vhost.conf.template /etc/apache2/sites-available/000-default.conf.template
COPY docker/render/entrypoint.sh /usr/local/bin/nesp-render-entrypoint
COPY docker/render/backup.sh /usr/local/bin/nesp-render-backup
COPY docker/php/production.ini /usr/local/etc/php/conf.d/zz-nesp-production.ini

RUN chmod 0755 \
      /usr/local/bin/nesp-render-entrypoint \
      /usr/local/bin/nesp-render-backup

EXPOSE 10000
ENTRYPOINT ["/usr/local/bin/nesp-render-entrypoint"]
CMD ["apache2-foreground"]
