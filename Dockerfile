FROM php:8.2-cli

# Instalar dependencias del sistema y extensiones de PHP necesarias
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Instalar extensiones de PHP para Base de Datos
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Conseguir la última versión de Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar el directorio de trabajo dentro del servidor
WORKDIR /app
COPY . /app

# Instalar las dependencias de Laravel
RUN composer install --no-dev --optimize-autoloader

# Comando para arrancar la aplicación (el que usábamos antes)
CMD php artisan serve --host 0.0.0.0 --port $PORT