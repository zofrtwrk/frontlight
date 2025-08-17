# Use the official PHP image with Apache
FROM php:8.2-apache

# Copy all files to the container
COPY . /var/www/html/

# Enable Apache rewrite mod if needed
RUN a2enmod rewrite
