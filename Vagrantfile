# -*- mode: ruby -*-
# vim:set ft=ruby:

$script = <<EOF
cd /var/www/stage1
composer self-update
composer install

app/console doctrine:database:drop --force
app/console doctrine:database:create
app/console doctrine:schema:update --force
app/console assetic:dump

bundle install
$(cd node/ && npm install)

sudo fab service.export

if [ ! -d /var/www/yuhao ]; then
    git clone https://github.com/stage1/yuhao.git /var/www/yuhao
fi

if ! docker images | grep stage1 > /dev/null; then
    bin/docker/update.sh
    bin/yuhao/update.sh
fi
EOF

Vagrant.configure("2") do |config|

    config.hostmanager.enabled = true
    config.hostmanager.manage_host = true

    config.vm.box = "ubermuda/stage1-dev"

    config.vm.hostname = 'stage1.dev'
    config.vm.network :private_network, ip: '192.168.215.42'
    
    config.vm.synced_folder '.', '/var/www/stage1', type: 'nfs'
    config.vm.synced_folder '/Users/ash/Projects', '/projects', type: 'nfs'

    config.hostmanager.aliases = %w(stage1.dev www.stage1.dev help.stage1.dev)

    config.vm.provision :shell, :inline => $script

    config.vm.provider 'vmware_fusion' do |v|
        v.vmx['memsize'] = 1024
        v.vmx['numvcpus'] = 1
    end

    config.vm.provider 'virtualbox' do |v|
        v.customize [ "modifyvm", :id, "--memory", "1024" ]
    end
end
