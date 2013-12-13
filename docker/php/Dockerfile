FROM ubuntu:precise
MAINTAINER Geoffrey Bachelet <geoffrey.bachelet@gmail.com>

ENV DEBIAN_FRONTEND noninteractive

RUN echo "force-unsafe-io" > /etc/dpkg/dpkg.cfg.d/02apt-speedup
RUN echo "Acquire::http {No-Cache=True;};" > /etc/apt/apt.conf.d/no-cache

#####################################################
# see https://github.com/dotcloud/docker/issues/1024
# RUN dpkg-divert --local --rename --add /sbin/initctl
# RUN ln -s /bin/true /sbin/initctl

RUN dpkg-divert --local --rename --add /sbin/initctl; ln -s /bin/true /sbin/initctl
#####################################################

# trick to avoid df error messages
RUN grep -v rootfs /proc/mounts > /etc/mtab

ADD ./support/sources.list /etc/apt/sources.list

#####################################################
# RUN apt-get update -y
# RUN apt-get install -qy realpath vim curl git openssh-server vim

RUN apt-get update -y; apt-get install -qy realpath vim curl git openssh-server vim ruby1.9.1
#####################################################

# ruby is needed to read yaml
# RUN apt-get install ruby1.9.1
# RUN gem install --no-ri --no-rdoc foreman

ADD ./support/authorized_keys /root/.ssh/authorized_keys
ADD ./support/ssh_config /root/.ssh/config

#####################################################
# RUN chmod -R 600 /root/.ssh
# RUN mkdir /var/run/sshd

RUN chmod -R 600 /root/.ssh; mkdir /var/run/sshd
#####################################################

RUN curl -sS http://www.dotdeb.org/dotdeb.gpg | apt-key add -

ADD ./support/dotdeb.list /etc/apt/sources.list.d/dotdeb.list

RUN apt-get update -y

#####################################################
# needed for php5-intl
# RUN curl -O http://launchpadlibrarian.net/59651391/libicu44_4.4.2-2_amd64.deb
# RUN dpkg --install libicu44_4.4.2-2_amd64.deb

RUN curl -O http://launchpadlibrarian.net/59651391/libicu44_4.4.2-2_amd64.deb; dpkg --install libicu44_4.4.2-2_amd64.deb
#####################################################

# php5-sqlite is mainly for prestacms
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
    zip

# needed to build phpupet correctly
# RUN apt-get install -qy zip

ADD ./support/nginx.conf /etc/nginx/sites-available/default
ADD ./support/php.ini /etc/php5/fpm/php.ini
ADD ./support/php.ini /etc/php5/cli/php.ini
ADD ./support/parameters.yml.dist /etc/symfony/parameters.yml.dist
ADD ./support/my.cnf /etc/mysql/my.cnf

# just in case something's wrong with innodb
RUN rm -f /var/lib/mysql/ib_logfile*

#####################################################
# RUN curl -sS https://getcomposer.org/installer | php
# RUN mv composer.phar /usr/local/bin/composer

RUN curl -sS https://getcomposer.org/installer | php;  mv composer.phar /usr/local/bin/composer
#####################################################