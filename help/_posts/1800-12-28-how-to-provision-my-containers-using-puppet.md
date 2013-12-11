---
layout: post
title: How to provision my containers using Puppet
published: true
category: provisioning
---

To provision your containers using a Puppet manifest, you need to install it and run it manually using the `.build.yml` configuration file:

    build:
      - apt-get install -y puppet
      - puppet apply path/to/your/manifest.pp

> The `path/to/your/manifest.pp` is relative to your project's root directory.
{:.note.oneline}

See the [stage1/masterless-puppet-example repository](https://github.com/stage1/masterless-puppet-example) for a very basic but working example.