---
layout: post
title: Launching arbitrary services in the app container
published: true
category: general
---

Launching arbitrary services in your app container is supported through the `.stage1.yml` configuration file with the `run` section. Much like the `build` section, you can specify any number of arbitrary commands. These commands can daemonize or not, it will work the same.

> Do not forget though that specifying a `build` section will completely bypass the default build, so you still need to include, for example, dependencies installation.
{:.note}

Example of installing **elasticsearch** and running it, with dependencies installation using **composer**:

    build:
      - composer --no-interaction --ansi --no-progress
      - apt-get install -q -y openjdk-7-jre-headless
      - wget https://download.elasticsearch.org/elasticsearch/elasticsearch/elasticsearch-0.90.7.deb
      - dpkg -i elasticsearch-*
    
    run:
      - service elasticsearch start
