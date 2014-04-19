FROM stackbrew/ubuntu:saucy
MAINTAINER Geoffrey Bachelet <geoffrey.bachelet@gmail.com>

ENV DEBIAN_FRONTEND noninteractive

RUN echo "force-unsafe-io" > /etc/dpkg/dpkg.cfg.d/02apt-speedup
RUN echo "Acquire::http {No-Cache=True;};" > /etc/apt/apt.conf.d/no-cache
RUN echo "Europe/Paris" > /etc/timezone; dpkg-reconfigure tzdata
# RUN grep -v rootfs /proc/mounts > /etc/mtab

RUN apt-get update -y
RUN apt-get install -q -y git curl openssh-server vim php5-cli php5-json ruby1.9.3

ADD ./support/authorized_keys /root/.ssh/authorized_keys
ADD ./support/ssh_config /etc/ssh/ssh_config

RUN chmod -R 600 /root/.ssh
RUN mkdir /var/run/sshd

ADD ./support/bin/buildapp.sh /usr/local/bin/buildapp
ADD ./support/bin/runapp.sh /usr/local/bin/runapp

ADD ./support/lib/stage1.sh /usr/local/lib/stage1.sh

# RUN chmod -R +x /usr/local/bin