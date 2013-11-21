#!/bin/bash

export DEBIAN_FRONTEND=noninteractive

if [ -f /tmp/grub-default ]; then
    mv /tmp/grub-default /etc/default/grub
    update-grub
fi

redis-cli RPUSH frontend:stage1.dev stage1 http://127.0.0.1:8080/

apt-get -qy install \
    build-essential \
    python2.7-dev \
    python-pip

pip install httpie
pip install fabric

rabbitmq-plugins enable rabbitmq_management

apt-get install -q -y ruby rubygems
gem install --no-ri --no-rdoc bundler

# prepare a few thing that we won't have to do during vagrant up
git clone git@bitbucket.org:ubermuda/stage1.git --branch feature/procfile /vagrant

cd /vagrant

bundle install
foreman export upstart /etc/init -u root -a stage1

docker build -t symfony2 docker/symfony2

rm -rf /vagrant

echo "STAGE1_ENV=dev" >> /etc/environment
echo "SYMFONY_ENV=dev" >> /etc/environment

# sleep 1d; true

# phantomjs + casperjs

# apt-get install -yq \
#     libfontconfig1

# wget https://phantomjs.googlecode.com/files/phantomjs-1.9.1-linux-x86_64.tar.bz2 -O- | tar xzm
# wget https://github.com/n1k0/casperjs/tarball/master -O- | tar xzm

# $(cd *phantomjs*; ln -sf $(pwd)/bin/phantomjs /usr/local/bin/phantomjs)
# $(cd *casperjs*; ln -s $(pwd)/bin/casperjs /usr/local/bin/casperjs)