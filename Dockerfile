FROM php:8.2-apache

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    libmariadb-dev \
    && docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Create startup script to handle dynamic PORT
RUN echo '#!/bin/bash\n\
    PORT=${PORT:-8080}\n\
    sed -i "s/Listen 80/Listen $PORT/g" /etc/apache2/ports.conf\n\
    sed -i "s/:80/:$PORT/g" /etc/apache2/sites-available/000-default.conf\n\
    apache2-foreground' > /usr/local/bin/start.sh && chmod +x /usr/local/bin/start.sh

# Expose port (Railway sets PORT env var dynamically)
EXPOSE 8080

CMD ["/usr/local/bin/start.sh"]

