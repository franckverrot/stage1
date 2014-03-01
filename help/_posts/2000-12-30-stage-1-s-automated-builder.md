---
layout: post
title: Stage1's automated builder
published: true
category: general
---

**Stage1**'s default builder for Symfony2 does a number of things for you:

1. Installing dependencies with [Composer](http://getcomposer.org/)
2. Initializing the database using [Doctrine](http://www.doctrine-project.org/) if available
3. Loading the fixtures, using Doctrine too
4. Installing assets using `assets:install`
5. Dumping assets using [Assetic](https://github.com/kriswallsmith/assetic) if available
6. Setting permissions on `app/cache` and `app/logs`

And the build script is equivalent to this `.build.yml` configuration:

    build:
      - composer install
      - php app/console doctrine:database:create
      - php app/console doctrine:schema:update --force
      - php app/console doctrine:fixtures:load
      - php app/console assets:install web/
      - php app/console assetic:dump