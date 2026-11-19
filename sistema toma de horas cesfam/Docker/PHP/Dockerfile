
FROM php:8.2-apache

# Instalar dependencias necesarias para PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Habilitar módulos de Apache
RUN a2enmod rewrite

# Configurar Apache
ENV APACHE_DOCUMENT_ROOT=/var/www/html

# Establecer permisos y directorio de trabajo
WORKDIR /var/www/html

# Configuración de Apache para permitir .htaccess
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/*.conf \
    /etc/apache2/apache2.conf \
    /etc/apache2/conf-available/*.conf
