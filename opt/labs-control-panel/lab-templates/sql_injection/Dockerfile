# Use the official PHP 8.2 Apache image
FROM php:8.2-apache

# Install SQLite3 packages and PHP PDO SQLite extension
RUN apt-get update \
    && apt-get install -y sqlite3 libsqlite3-dev \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Copy the challenge website files
COPY . /var/www/html/

# Create the SQLite database and a 'users' table
RUN sqlite3 /var/www/html/db.sqlite "CREATE TABLE users (id INTEGER PRIMARY KEY, username TEXT, password TEXT); \
    INSERT INTO users (username, password) VALUES ('admin', 'pA\$\$w0rd_iS_n0t_p@ssw0rd');"

# Set correct permissions for Apache
RUN chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80