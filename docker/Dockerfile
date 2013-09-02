FROM ubuntu:precise
MAINTAINER Geoffrey Bachelet <geoffrey.bachelet@gmail.com>

ENV DEBIAN_FRONTEND noninteractive

# see https://github.com/dotcloud/docker/issues/1024
RUN dpkg-divert --local --rename --add /sbin/initctl
RUN ln -s /bin/true /sbin/initctl

RUN apt-get update -y
RUN apt-get install -qy realpath vim curl git ruby1.9.1

RUN curl -sS http://www.dotdeb.org/dotdeb.gpg | apt-key add -

ADD ./support/sources.list /etc/apt/sources.list
ADD ./support/dotdeb.list /etc/apt/sources.list.d/dotdeb.list

RUN apt-get update -y

RUN curl -O http://launchpadlibrarian.net/59651391/libicu44_4.4.2-2_amd64.deb
RUN dpkg --install libicu44_4.4.2-2_amd64.deb

RUN apt-get install -qy mysql-server php5-cli php5-mysqlnd php5-intl

# ADD ./support/nginx.conf /etc/nginx/sites-available/default
# ADD ./support/php.ini /etc/php5/fpm/php.ini
ADD ./support/php.ini /etc/php5/cli/php.ini
ADD ./support/parameters.yml /etc/symfony/parameters.yml.dist

RUN curl -sS https://getcomposer.org/installer | php
RUN mv composer.phar /usr/local/bin/composer

ADD ./support/bin/buildapp.sh /usr/local/bin/buildapp
ADD ./support/bin/runapp.sh /usr/local/bin/runapp

ADD ./support/lib/stage1.sh /usr/local/lib/stage1.sh
ADD ./support/lib/builder/ /usr/local/lib/builder/

RUN chmod -R +x /usr/local/bin/buildapp /usr/local/bin/runapp /usr/local/lib/builder/

RUN apt-get autoremove -qy vim curl