#!/bin/bash -x
vagrant box remove stage1-dev vmware_desktop
packer build packer/dev.json
vagrant box add stage1-dev packer/boxes/dev.box vmware_desktop
vagrant destroy -f dev
vagrant up
