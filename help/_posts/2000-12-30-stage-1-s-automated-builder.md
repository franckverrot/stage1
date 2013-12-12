---
layout: post
title: Stage1's automated builder
published: true
category: general
---

**Stage1**'s default builder for Symfony2 does a number of things for you:

1. Setting up a default `parameters.yml`
2. Installing dependencies with [Composer](http://getcomposer.org/)
3. Initializing the database using [Doctrine](http://www.doctrine-project.org/) if available
4. Loading the fixtures, using Doctrine too
5. Setting permissions on `app/cache` and `app/logs`

The `parameters.yml.dist` used is:

    parameters:
        database_driver:   pdo_mysql
        database_host:     127.0.0.1
        database_port:     ~
        database_name:     symfony
        database_user:     root
        database_password: ~

        mailer_transport:  smtp
        mailer_host:       127.0.0.1
        mailer_user:       ~
        mailer_password:   ~

        locale:            en
        secret:            ThisTokenIsNotSoSecretChangeIt

And the build script is equivalent to this `.build.yml` configuration:

    build:
      - composer install --ansi --no-progress --no-dev --prefer-source --no-interaction
      - php app/console doctrine:database:create
      - php app/console doctrine:schema:update --force
      - php app/console doctrine:fixtures:load