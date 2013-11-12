export DEBIAN_FRONTEND=noninteractive

echo 'en_US.UTF-8 UTF-8' > /etc/locale.gen

dpkg-reconfigure locales

aptitude install -q -y vim curl git

curl http://get.docker.io/gpg | apt-key add -
curl http://www.rabbitmq.com/rabbitmq-signing-key-public.asc | apt-key add -
curl http://www.dotdeb.org/dotdeb.gpg | apt-key add -

echo 'deb http://ftp.fr.debian.org/debian wheezy-backports main' > /etc/apt/sources.list.d/backports.list
echo 'deb http://get.docker.io/ubuntu docker main' > /etc/apt/sources.list.d/docker.list
echo 'deb http://www.rabbitmq.com/debian/ testing main' > /etc/apt/sources.list.d/rabbitmq.list
echo 'deb http://packages.dotdeb.org wheezy all' > /etc/apt/sources.list.d/dotdeb.list
echo 'deb http://packages.dotdeb.org wheezy-php55 all' >> /etc/apt/sources.list.d/dotdeb.list

aptitude update -q -y

aptitude -q -y install \
    nginx \
    php5-fpm \
    php5-cli \
    php5-mysqlnd \
    php5-redis \
    php5-curl \
    redis-server \
    rabbitmq-server \
    mysql-client \
    mysql-server \
    monit \
    realpath \
    htop \
    acl \
    nodejs \
    nodejs-legacy \
    lxc-docker \
    sudo

curl https://npmjs.org/install.sh | sh

sed -e 's/errors=remount-ro/&,acl/' -i /etc/fstab
mount -o remount /

# @todo configuration

npm install -g coffee-script

curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

docker pull ubuntu:precise
groupadd docker
echo 'net.ipv4.ip_forwarding = 1' > /etc/sysctl.d/docker-ip-forwarding.conf

npm install -g git://github.com/ubermuda/hipache.git
mkdir -p /var/log/hipache

update-grub

echo 'export SYMFONY_ENV=prod' >> /etc/environment
echo 'export STAGE1_ENV=prod' >> /etc/environment

usermod -aG docker ash
usermod -aG sudo ash

passwd -l root

echo
echo 'Provisioning finished. Please install a recent kernel and reboot.'