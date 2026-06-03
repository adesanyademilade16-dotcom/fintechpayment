# Use an official PHP image with Apache pre-installed
FROM php:8.2-apache

# Enable Apache mod_rewrite (this replaces your .htaccess rules perfectly)
RUN a2enmod rewrite

# Install MySQL extension in case your db.php needs to run queries later
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Copy all your project files from GitHub straight into the web server directory
COPY . /var/www/html/

# Set the correct permissions so the server can read your files securely
RUN chown -R www-data:www-data /var/www/html/

# Expose port 80 for web traffic
EXPOSE 80
