FROM ubuntu:precise
MAINTAINER Geoffrey Bachelet <geoffrey.bachelet@gmail.com>

ENV DEBIAN_FRONTEND noninteractive

# see https://github.com/dotcloud/docker/issues/1024
RUN dpkg-divert --local --rename --add /sbin/initctl
RUN ln -s /bin/true /sbin/initctl

RUN apt-get update -y
RUN apt-get install -qy vim curl git

RUN curl -sS http://www.dotdeb.org/dotdeb.gpg | apt-key add -

ADD ./sources.list /etc/apt/sources.list
ADD ./dotdeb.list /etc/apt/sources.list.d/dotdeb.list

RUN apt-get update -y

RUN apt-get install -qy nginx mysql-server php5-fpm php5-cli php5-mysqlnd

ADD ./nginx.conf /etc/nginx/sites-available/default
ADD ./php.ini /etc/php5/fpm/php.ini
ADD ./php.ini /etc/php5/cli/php.ini
ADD ./parameters.yml /etc/symfony/parameters.yml.dist

RUN curl -sS https://getcomposer.org/installer | php
RUN mv composer.phar /usr/local/bin/composer

ADD ./build.sh /usr/local/bin/build.sh

ENTRYPOINT ["/usr/local/bin/build.sh"]