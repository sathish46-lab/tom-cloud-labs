FROM adminer:latest

USER root

# PHP mongodb extension — use the prebuilt Alpine package (same PHP 8.4 NTS
# ABI) instead of compiling via pecl (10+ min). Copy the .so into docker-php's
# extension dir, enable it, drop the package but KEEP its runtime libs
# (snappy/zstd/libsasl) which the .so links against.
RUN apk add --no-cache php84-pecl-mongodb snappy zstd libsasl && \
    cp /usr/lib/php84/modules/mongodb.so $(php -r 'echo ini_get("extension_dir");')/mongodb.so && \
    docker-php-ext-enable mongodb && \
    apk del php84-pecl-mongodb && \
    php -m | grep -q mongodb

# Register the official compiled mongo driver (Adminer 5 loads drivers from
# adminer-plugins/). Pinned to the adminer version shipped in the base image;
# the compiled release is compatible with adminer.php as-is (the bundled
# plugins/drivers/mongo.php is the "dev" source with incompatible type
# declarations).
RUN mkdir -p adminer-plugins && \
    wget -q -O adminer-plugins/mongo.php \
        https://www.adminer.org/static/download/6.3.0/drivers/mongo.php && \
    php -l adminer-plugins/mongo.php

# index.php variant that routes driver loading through adminer-plugins/
COPY index.php /var/www/html/index.php

USER adminer
