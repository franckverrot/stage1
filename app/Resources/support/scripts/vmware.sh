#!/bin/bash

[ "$PACKER_BUILDER_TYPE" == "vmware" ] || {
    echo 'not building vmware, skipping';
    exit;
}

apt-get -q -y install gcc make fuse fuse-utils linux-headers-$(uname -r)

if [ ! -d /mnt/vmware ]; then
    mkdir /mnt/vmware
    mount -o loop /tmp/vmware-linux.iso /mnt/vmware
fi

cd /tmp
tar xzf /mnt/vmware/VMwareTools-*.tar.gz
/tmp/vmware-tools-distrib/vmware-install.pl -d

umount /mnt/vmware
rmdir /mnt/vmware