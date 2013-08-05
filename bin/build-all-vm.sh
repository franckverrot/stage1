#!/bin/bash
vagrant box remove stage1-dev vmware_desktop
vagrant box remove stage1-prod vmware_desktop

packer build app/Resources/packer/dev.json
packer build app/Resources/packer/prod.json

vagrant box add stage1-dev app/Resources/boxes/dev.box --provider=vmware_desktop
vagrant box add stage1-prod app/Resources/boxes/prod.box --provider=vmware_desktop