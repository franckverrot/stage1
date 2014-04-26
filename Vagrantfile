# -*- mode: ruby -*-
# vim:set ft=ruby:

$script = <<EOF
# cd /vagrant
# composer self-update
# composer install
# /vagrant/app/console doctrine:database:drop --force
# /vagrant/app/console doctrine:database:create
# /vagrant/app/console doctrine:schema:update --force
# /vagrant/app/console assetic:dump
# /vagrant/app/console php
# bundle install
# fab upstart_export
# sudo restart stage1
# redis-cli del frontend:help.stage1.dev
# redis-cli rpush frontend:help.stage1.dev help http://127.0.0.1:8080/
EOF

Vagrant.configure("2") do |config|

    config.hostmanager.enabled = true
    config.hostmanager.manage_host = true

    config.vm.box = "stage1-dev"
    config.vm.box_url = "packer/boxes/dev.box"

    config.vm.hostname = 'stage1-dev'
    config.vm.network :private_network, ip: '192.168.215.42'
    
    config.vm.synced_folder ".", "/vagrant", :nfs => true
    config.vm.synced_folder ".", "/var/www/stage1", :nfs => true

    config.hostmanager.aliases = %w(
        stage1.dev
        www.stage1.dev
        help.stage1.dev
    )

    config.vm.provision :shell, :inline => $script

    config.vm.provider 'vmware_fusion' do |v|
        # v.vmx['memsize'] = 2048
        # v.vmx['numvcpus'] = 2
        v.vmx['memsize'] = 1024
        v.vmx['numvcpus'] = 1
    end

    config.vm.provider 'virtualbox' do |v|
        v.customize [ "modifyvm", :id, "--memory", "1024" ]
    end
end
