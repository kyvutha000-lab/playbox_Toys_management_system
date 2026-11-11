FROM php:8.2-cli
RUN docker-php-ext-install pdo_mysql
WORKDIR /app
COPY . /app
RUN mkdir -p /app/uploads/toys && chmod -R 775 /app/uploads
CMD ["sh", "-c", "php -d upload_max_filesize=10M -d post_max_size=12M -S 0.0.0.0:${PORT:-8080} -t /app"]
