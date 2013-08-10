# -*- mode: ruby -*-
# vi: set ft=ruby :

$script = <<EOF
sudo /etc/init.d/monit restart
sudo docker build -t symfony /vagrant/docker/
cd /vagrant
composer install
/vagrant/app/console doctrine:database:create
/vagrant/app/console doctrine:schema:update --force
EOF

Vagrant.configure("2") do |config|

    config.hostmanager.enabled = true
    config.hostmanager.manage_host = true

    config.vm.provider :digital_ocean do |provider, override|
        override.ssh.private_key_path = '~/.ssh/id_rsa'
        override.vm.box = 'digital_ocean'
        override.vm.box_url = "https://github.com/smdahlen/vagrant-digitalocean/raw/master/box/digital_ocean.box"

        provider.client_id = 'xlylJ0GKfaX5pXDL5JN4z'
        provider.api_key = '0988Y84XvmEIlQa11MhuuVEeo1bUsqdiyWMMA71Ur'
        provider.region = 2
        provider.size = 66
        provider.image = 'stage1.1376112242'
    end

    config.vm.define :dev do |dev|
        dev.vm.box = "stage1-dev"
        dev.vm.box_url = "app/Resources/boxes/dev.box"

        dev.vm.hostname = 'stage1-dev'
        dev.vm.network :private_network, ip: '192.168.215.42'
        dev.vm.synced_folder ".", "/vagrant", :nfs => true
        dev.hostmanager.aliases = %w(stage1)

        dev.vm.provision :shell, :inline => $script
    end

    config.vm.define :prod do |prod|
        prod.vm.box = "stage1-prod"
        prod.vm.box_url = "app/Resources/boxes/ubuntu.box"

        prod.vm.hostname = 'stage1-prod'
        prod.vm.network :private_network, ip: '192.168.215.43'
        prod.vm.synced_folder ".", "/vagrant", disabled: true
    end
end
