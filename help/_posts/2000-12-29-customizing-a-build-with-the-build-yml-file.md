---
layout: post
title: Customizing a build with the .build.yml file
published: true
category: general
---

The `.build.yml` file is a configuration file specific to [Stage1](http://stage1.io/) that allows full customization of your build process. It must be placed at the root of your project and contain valid YAML with a `build` key.

Here is an example build installing dependencies through **composer** and then publishing assets with **assetic**:

    build:
      - composer install --no-interaction --ansi --no-progress
      - php app/console assetic:dump

> Build containers have the `SYMFONY_ENV` environment variable set to `prod`, so you don't have to worry about the `--env` option.
{:.note}

Right now, Stage1's web console supports a limited set of ansi escape codes, so while you can (and should) use ansi coloring (with symfony's `--ansi` option), we recommend you use the `--no-progress` option or equivalent whenever possible for more readable results.

### Configuring the base image

You can configure the base image used for your builds using the `image` key:

    image: symfony2

> [Learn more about base images]({% post_url 1900-12-31-how-to-change-my-project-s-base-image %}/).
{:.note.oneline}

### Configuring environment variables

You can configure environment variables right from your `.build.yml` file, with the `env` key:

    env:
      - SYMFONY_ENV=prod
      - FOO_API_KEY=7BMnTzAIoTeVg
      - FOO=some value with spaces

> [Learn more about environment variables]({% post_url 2000-12-26-setup-custom-environment-variables-in-my-containers %}/).
{:.note.oneline}

### Configuring additionnal custom domains

You can configure additionnal custom domains with the `urls` key:

    urls
      - foo
      - bar

> [Learn more about custom domains]({% post_url 2000-12-25-configuring-custom-urls-for-my-staging-environment %}/).
{:.note.oneline}

### Configuring writable files and folders

Most web applications need to write files at some points, be it for sessions, cache, assets uploading, whatever. Stage1 makes it easy to specify writable files and folders using the `writable` configuration section. For example, the following snippet makes the `app/logs` and `app/cache` folders writable for everyone:

    writable:
        - app/logs
        - app/cache

As usual, paths are relative to your project's root directory.