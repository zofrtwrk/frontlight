# PHP + Apache (serves HTML & PHP)
FROM php:8.2-apache

# Enable useful modules
RUN a2enmod rewrite headers

# Allow .htaccess overrides in the web root
RUN printf '<Directory "/var/www/html">\n  AllowOverride All\n  Require all granted\n</Directory>\n' \
    > /etc/apache2/conf-available/allow-htaccess.conf \
 && a2enconf allow-htaccess

# Use production PHP settings (optional but recommended)
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Copy your app (adjust path if you keep a /public folder)
WORKDIR /var/www/html
COPY --chown=www-data:www-data . /var/www/html

# Runtime entrypoint: make Apache listen on $PORT (Railway requirement)
# Falls back to 80 if PORT is not set (local dev)
RUN printf '#!/bin/sh\n: ${PORT:=80}\nsed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf\nexec apache2-foreground\n' \
    > /usr/local/bin/run-apache.sh \
 && chmod +x /usr/local/bin/run-apache.sh

EXPOSE 80
CMD ["run-apache.sh"]
