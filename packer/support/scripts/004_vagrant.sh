#!/bin/bash

mkdir -pm 700 /home/vagrant/.ssh

cp /tmp/keys-stage1-vm /home/vagrant/.ssh/id_rsa
cp /tmp/keys-stage1-vm.pub /home/vagrant/.ssh/id_rsa.pub

cat /tmp/keys-stage1-vm.pub >> /home/vagrant/.ssh/authorized_keys

export VAGRANT_PUBKEY="ssh-rsa AAAAB3NzaC1yc2EAAAABIwAAAQEA6NF8iallvQVp22WDkTkyrtvp9eWW6A8YVr+kz4TjGYe7gHzIw+niNltGEFHzD8+v1I2YJ6oXevct1YeS0o9HZyN1Q9qgCgzUFtdOKLv6IedplqoPkcmF0aYet2PkEDo3MlTBckFXPITAMzF8dJSIFo9D8HfdOV0IAdx4O7PtixWKn5y2hMNG0zQPyUecp4pzC6kivAIhyfHilFR61RGL+GPXQ2MWZWFYbAGjyiYJnAmCP3NOTd0jMZEnDkbUvxhMmBYSdETk1rRgm+R4LOzFUGaHqHDLKLX+FIPKcF96hrucXzcWyLbIbEgE98OHlnVYCzRdK8jlqm8tehUc9c9WhQ== vagrant insecure public key"

echo $VAGRANT_PUBKEY >> /home/vagrant/.ssh/authorized_keys

chmod 0600 /home/vagrant/.ssh/id_rsa
chmod 0644 /home/vagrant/.ssh/id_rsa.pub
chmod 0600 /home/vagrant/.ssh/authorized_keys

chown -R vagrant /home/vagrant

date > /etc/vagrant_box_build_time