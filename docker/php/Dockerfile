FROM stage1
MAINTAINER Geoffrey Bachelet <geoffrey.bachelet@gmail.com>

RUN apt-get install -qy realpath ca-certificates-java openjdk-7-jre-headless || true

# php5-sqlite is mainly for prestacms
# php5-json is for composer
# php5-mcrypt is for laravel
# zip is mainly for puphpet

RUN apt-get install -qy \
    make \
    nginx \
    mysql-server \
    php5-cli \
    php5-fpm \
    php5-mysqlnd \
    php5-intl \
    php5-curl \
    php5-gd \
    php5-sqlite \
    php5-json \
    php5-mcrypt \
    zip

RUN echo 'extension=mcrypt.so' > /etc/php5/cli/conf.d/20-mcrypt.ini
RUN echo 'extension=mcrypt.so' > /etc/php5/fpm/conf.d/20-mcrypt.ini

RUN curl -sS https://getcomposer.org/installer | php
RUN mv composer.phar /usr/local/bin/composer

# just in case something's wrong with innodb
RUN rm -f /var/lib/mysql/ib_logfile*

RUN gem install --no-ri --no-rdoc compass sass; ln -s /usr/local/bin/compass /usr/bin/compass; ln -s /usr/local/bin/sass /usr/bin/sass

ADD ./support/nginx.conf /etc/nginx/sites-available/default
ADD ./support/php.ini /etc/php5/fpm/php.ini
ADD ./support/php.ini /etc/php5/cli/php.ini
ADD ./support/my.cnf /etc/mysql/my.cnf