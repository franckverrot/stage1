STAGE1
======

* install packer (http://packer.io)
* build the VM in `packer/dev.json`
* install vagrant
* install hostmanager plugin `vagrant plugin install vagrant-hostmanager`
* customize the Vagrantfile
* run the VM with vagrant
* `vagrant ssh`
* update lxc-docker
* `sudo apt-get install daemontools daemontools-run`
* check that rabbitmq is running
* `rm -rf /etc/init/stage1*`
* `ps auxwww | grep stage1 | awk '{print $1}' | xargs kill`
* `cd /vagrant`
* `fab service.export`
* `sudo start svscan`
* `composer install`
* `app/console doctrine:database:create`
* `app/console doctrine:schema:update --force`
