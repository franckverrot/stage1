#!/bin/bash -ex
vagrant box remove stage1-dev vmware_desktop
packer build app/Resources/packer/dev.json
vagrant box add stage1-dev app/Resources/boxes/dev.box --provider=vmware_desktop
vagrant destroy -f dev
