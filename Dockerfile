FROM harvardinformatics/wheezy-php55

ENV DEBIAN_FRONTEND noninteractive

RUN echo 'America/New_York' > /etc/timezone && dpkg-reconfigure -f noninteractive tzdata && apt-get update && apt-get install default-jre git unzip -y

RUN a2enmod php5 && \
    sed -i -e 's?ErrorLog.*?ErrorLog /dev/stderr?' /etc/apache2/apache2.conf && \
    printf "display_error = stderr\nerror_log = /dev/stderr\n" > /etc/php5/apache2/conf.d/20-logging.ini && \
    sed -i -e 's?;include_path = ".:/usr/share/php"?include_path = ".:/var/php/includes:/var/php/includes/specify_web:/var/php/includes:/usr/share/php"?' /etc/php5/apache2/php.ini && \
    printf "date.timezone = \"America/New_York\"\n" >> /etc/php5/apache2/php.ini && \
    rm -f /var/www/index.html

ADD etc/000-default /etc/apache2/sites-enabled/000-default

ADD dojo /var/www/dojo
RUN mkdir /var/www/jquery && \
    cd /var/www/jquery && \
    wget https://code.jquery.com/jquery-3.2.1.js && \
    wget https://jqueryui.com/resources/download/jquery-ui-1.12.1.zip && \
    unzip jquery-ui-1.12.1.zip

EXPOSE 80
ENV PHPINCPATH=/var/php/includes/
ENV JAVA_EXE='/usr/bin/java'
ENV ENCRYPTION_JAR='/var/php/includes/Encryption.jar'

CMD ["apachectl", "-DFOREGROUND"]
