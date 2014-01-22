---
layout: post
title: Customizing a build with the .build.yml file
published: true
category: general
---

The `.build.yml` file is a configuration file specific to Stage1 that permits you to replace the automated build with an arbitrary sequence of commands. It must be placed at the root of your project and contain valid YAML with a `build` key.

Here is an example build installing dependencies through **composer** and then publishing assets with **assetic**:

    build:
      - composer install --no-interaction --ansi --no-progress
      - php app/console assetic:dump

> Build containers have the `SYMFONY_ENV` environment variable set to `prod`, so you don't have to worry about the `--env` option.
{:.note}

Right now, Stage1's web console supports a limited set of ansi escape codes, so while you can (and should) use ansi coloring (with symfony's `--ansi` option), we recommend you use the `--no-progress` option or equivalent whenever possible for more readable results.

### Configuring writable files and folders

Most web applications need to write files at some points, be it for sessions, cache, assets uploading, whatever. Stage1 makes it easy to specify writable files and folders using the `writable` configuration section. For example, the following snippet makes the `app/logs` and `app/cache` folders writable for everyone:

    writable:
        - app/logs
        - app/cache

As usual, paths are relative to your project's root directory.