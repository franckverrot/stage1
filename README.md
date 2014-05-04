STAGE1
======

* install packer (http://packer.io)
* build the VM in `packer/dev.json`
* install vagrant
* `vagrant plugin install vagrant-hostmanager`
* customize the Vagrantfile
* `vagrant up`
* `vagrant ssh`
* `apt-get update && apt-get install lxc-docker`
* `sudo apt-get install daemontools daemontools-run`
* `rm -rf /etc/init/stage1*`
* `ps auxwww | grep stage1 | awk '{print $1}' | xargs kill`
* `cd /vagrant`
* `fab service.export`
* `sudo start svscan`
* `composer install`
* `app/console doctrine:database:create`
* `app/console doctrine:schema:update --force`