---
layout: post
title: How to provision my containers using Chef
published: true
category: provisioning
---

To provision your containers using a Chef, you need to install it and run it manually using the `.stage1.yml` configuration file:

    build:
      - curl -L https://www.opscode.com/chef/install.sh | sudo bash
      - chef-solo -c path/to/your/solo.rb

> The `path/to/your/solo.rb` is relative to your project’s root directory.
{:.note.oneline}

Your `solo.rb` should look like:

    cookbook_path ['/var/www/cookbooks']
    json_attribs  '/var/www/solo-staging.json'

> `/var/www/cookbooks` and `/var/www/solo-staging.json` are absolute paths as Chef seems to not work with relative ones.
{:.note}

## Using Berkshelf

If you are managing your cookbook dependencies with Berkshelf, you will have to install it manually. You can directly use the gem command to do that:

    build:
      - sudo apt-get update
      - sudo apt-get install -y ruby-dev
      
      - curl -L https://www.opscode.com/chef/install.sh | sudo bash  
      
      - sudo gem install berkshelf --no-ri --no-rdoc
      - berks install -p cookbooks
      
      - chef-solo -c path/to/your/solo.rb

> The `path/to/your/solo.rb` is relative to your project’s root directory.
{:.note.oneline}

---

This help page has been kindly contributed by [Julien Bianchi](https://github.com/jubianchi).
{:.center}
