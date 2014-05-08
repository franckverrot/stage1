STAGE1
======

1. Install [Packer](http://packer.io/) and [Vagrant](http://vagrantup.com/)
2. Build a VM for your provider of choice, either `vmware-iso` or `virtualbox-iso`. A pre-built VM will be available soon.

    $ packer build -only=$provider packer/dev.json

3. Add it to vagrant and run it. The `--provider` part is only needed for VMware.

    $ vagrant add --name stage1/dev --provider vmware_desktop packer/boxes/dev.box
    $ vagrant up

4. Open http://stage1.dev/