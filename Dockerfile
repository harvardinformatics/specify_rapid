FROM php:8.3-apache

ENV DEBIAN_FRONTEND=noninteractive

RUN ln -snf /usr/share/zoneinfo/America/New_York /etc/localtime && echo 'America/New_York' > /etc/timezone && \
    apt-get update && apt-get install -y --no-install-recommends default-jre-headless git unzip && \
    rm -rf /var/lib/apt/lists/*

# mysqli: database access.  exif: exif_read_data() and exif_imagetype() in class_lib.php.
RUN docker-php-ext-install mysqli exif

RUN groupadd -g 402623 huh && useradd -u 12807 huhimageproc -g huh

# The official image reads the user and group Apache runs as from these variables,
# and already sends the Apache error log to stderr.
ENV APACHE_RUN_USER=huhimageproc \
    APACHE_RUN_GROUP=huh

# PHP errors go to the container log, not to the browser.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    printf 'display_errors = Off\nlog_errors = On\nerror_log = /dev/stderr\n' > "$PHP_INI_DIR/conf.d/20-logging.ini" && \
    printf 'include_path = ".:/var/php/includes:/var/php/includes/specify_web:/usr/local/lib/php"\ndate.timezone = "America/New_York"\n' > "$PHP_INI_DIR/conf.d/30-specify-rapid.ini"

ADD etc/000-default /etc/apache2/sites-enabled/000-default.conf
ADD dojo /var/www/dojo
ADD jquery /var/www/jquery

EXPOSE 80
ENV PHPINCPATH=/var/php/includes/ \
    JAVA_EXE=/usr/bin/java \
    ENCRYPTION_JAR=/var/php/includes/Encryption.jar \
    BASE_IMAGE_PATH=/var/www/images/ \
    BASE_IMAGE_URI=/images/ \
    BATCHPATH=''

CMD ["apache2-foreground"]
