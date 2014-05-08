STAGE1
======

Install [Packer](http://packer.io/) and [Vagrant](http://vagrantup.com/), then build the VM and run it.

**VMware users**:

    $ packer build -only=vmware-iso packer/dev.json
    $ vagrant add --name stage1/dev --provider vmware_desktop packer/boxes/dev.box
    $ vagrant up

**VirtualBox users**:

    $ packer build -only=virtualbox-iso packer/dev.json
    $ vagrant add --name stage1/dev packer/boxes/dev.box
    $ vagrant up

Open http://stage1.dev/.