mkdir -pm 700 /home/vagrant/.ssh

cp /tmp/keys-stage1-vm /home/vagrant/.ssh/id_rsa
cp /tmp/keys-stage1-vm.pub /home/vagrant/.ssh/id_rsa.pub

cat /tmp/keys-stage1-vm.pub >> /home/vagrant/.ssh/authorized_keys

chmod 0600 /home/vagrant/.ssh/id_rsa
chmod 0644 /home/vagrant/.ssh/id_rsa.pub
chmod 0600 /home/vagrant/.ssh/authorized_keys

cp /tmp/ssh-config /home/vagrant/.ssh/config

chmod 0600 /home/vagrant/.ssh/config

chown -R vagrant /home/vagrant