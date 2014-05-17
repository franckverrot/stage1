STAGE1
======

Using [Vagrant](http://vagrantup.com/):

    $ vagrant plugin install vagrant-hostmanager
    $ vagrant up

Then head to http://stage1.dev/.

Disclaimer
----------

Current code for STAGE1 is __prototype quality__. It's dirty, and I'm really not proud of it. Actually, if someone working for me produced code like this, I'd fire him. Yeah.

And please don't look at the commit history.

The whole codebase is undergoing drastic refactoring to bring its quality up to an acceptable level.

Repacking the VM
----------------

Using [Packer](http://packer.io/):

**VMware users**:

    $ packer build -only=vmware-iso packer/dev.json
    $ vagrant box add \
        --name ubermuda/stage1-dev \
        --box-version 9999 \
        --provider vmware_desktop \
        packer/boxes/dev.box

**VirtualBox users**:

    $ packer build -only=virtualbox-iso packer/dev.json
    $ vagrant box add \
        --name ubermuda/stage1-dev \
        --box-version 9999 \
        packer/boxes/dev.box

We use a box version of `9999` to trick Vagrant into using our version rather than the "official" one.