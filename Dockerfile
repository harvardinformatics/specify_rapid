FROM harvardinformatics/wheezy-php55

ENV DEBIAN_FRONTEND noninteractive

RUN echo 'America/New_York' > /etc/timezone && dpkg-reconfigure -f noninteractive tzdata && apt-get update && apt-get install default-jre git unzip -y

RUN groupadd -g 402623 huh && useradd -u 12807 huhimageproc -g huh
RUN a2enmod php5 && \
    sed -i -e 's?ErrorLog.*?ErrorLog /dev/stderr?' /etc/apache2/apache2.conf && \
    sed -i -e 's?export APACHE_RUN_USER=.*?export APACHE_RUN_USER=huhimageproc?' /etc/apache2/envvars && \
    sed -i -e 's?export APACHE_RUN_GROUP=.*?export APACHE_RUN_GROUP=huh?' /etc/apache2/envvars && \
    printf "display_error = stderr\nerror_log = /dev/stderr\n" > /etc/php5/apache2/conf.d/20-logging.ini && \
    sed -i -e 's?;include_path = ".:/usr/share/php"?include_path = ".:/var/php/includes:/var/php/includes/specify_web:/usr/share/php"?' /etc/php5/apache2/php.ini && \
    printf "date.timezone = \"America/New_York\"\n" >> /etc/php5/apache2/php.ini && \
    rm -f /var/www/index.html

ADD etc/000-default /etc/apache2/sites-enabled/000-default
ADD dojo /var/www/dojo
ADD jquery /var/www/jquery

EXPOSE 80
ENV PHPINCPATH=/var/php/includes \
    JAVA_EXE=/usr/bin/java \
    ENCRYPTION_JAR=/var/php/includes/Encryption.jar \
    BASE_IMAGE_PATH=/var/www/images/ \
    BASE_IMAGE_URI=/images/ \
    BATCHPATH=''

CMD ["apachectl", "-DFOREGROUND"]
