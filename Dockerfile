# PHP + Apache
FROM php:8.2-apache

# Enable rewrites + allow .htaccess
RUN a2enmod rewrite headers \
 && printf '<Directory "/var/www/html">\n  AllowOverride All\n  Require all granted\n</Directory>\n' \
      > /etc/apache2/conf-available/allow-htaccess.conf \
 && a2enconf allow-htaccess

# Use production php.ini (optional)
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# App files
WORKDIR /var/www/html
COPY --chown=www-data:www-data . /var/www/html

# Start script: bind Apache to $PORT and update vhost
RUN printf '#!/bin/sh\nset -e\n: ${PORT:=80}\n'\
'sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf\n'\
'sed -ri "s#\\*:80#*:${PORT}#g" /etc/apache2/sites-available/000-default.conf\n'\
'if [ -f /etc/apache2/sites-available/default-ssl.conf ]; then sed -ri "s#\\*:443#*:${PORT}#g" /etc/apache2/sites-available/default-ssl.conf; fi\n'\
'exec apache2-foreground\n' > /usr/local/bin/run-apache.sh \
 && chmod +x /usr/local/bin/run-apache.sh

EXPOSE 80
CMD ["run-apache.sh"]
