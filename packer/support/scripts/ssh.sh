mkdir -pm 700 /home/vagrant/.ssh

cp /tmp/keys-stage1-vm /home/vagrant/.ssh/id_rsa
cp /tmp/keys-stage1-vm.pub /home/vagrant/.ssh/id_rsa.pub

cat /tmp/keys-stage1-vm.pub >> /home/vagrant/.ssh/authorized_keys

chmod 0600 /home/vagrant/.ssh/id_rsa
chmod 0644 /home/vagrant/.ssh/id_rsa.pub
chmod 0600 /home/vagrant/.ssh/authorized_keys

chown -R vagrant /home/vagrant

cp /tmp/ssh-config /etc/ssh/ssh_config

chmod 0644 /etc/ssh/ssh_config
chown root:root /etc/ssh/ssh_config