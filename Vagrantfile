# -*- mode: ruby -*-
# vi: set ft=ruby :

Vagrant.configure("2") do |config|
    config.vm.box = "stage1"
    config.vm.box_url = "/Users/ash/Projects/packer-templates/stage1/vmware.box"

    config.vm.hostname = 'stage1'
    config.vm.network :private_network, ip: '192.168.215.42'
    config.vm.synced_folder ".", "/vagrant", :nfs => true

    config.hostmanager.enabled = true
    config.hostmanager.manage_host = true

    config.vm.provision :shell,
        :inline => "cd /vagrant; composer install; php app/console d:d:c; php app/console d:s:u --force"
end
