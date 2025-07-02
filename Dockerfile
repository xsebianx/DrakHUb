# Dockerfile
FROM php:8.2-apache

# Instalar dependencias
RUN apt-get update && apt-get install -y \
    libicu-dev \
    && docker-php-ext-install intl \
    && rm -rf /var/lib/apt/lists/*

# Configurar Apache
RUN echo "ServerTokens Prod" >> /etc/apache2/apache2.conf && \
    echo "ServerSignature Off" >> /etc/apache2/apache2.conf && \
    a2enmod rewrite headers

# Copiar archivos
COPY index.php /var/www/html/

# Configurar logs
RUN mkdir /var/log/php && \
    chown www-data:www-data /var/log/php && \
    touch /var/log/php/php_errors.log && \
    chmod 666 /var/log/php/php_errors.log

# Configurar volumen
VOLUME /data

# Puerto expuesto
EXPOSE 8080
