---
layout: post
title: Setup custom environment variables in my containers
published: true
category: general
---

You can setup custom environment variables to be set up in your containers at build and run time.

### Configuring environment variables through the web UI

Setting up custom environment variables is supported via the project's **Admin** tab:

![Administration tab then environment variables form](/assets/screenshots/project-env.png)

You can add as many variables as you want, following these rules:

* one variable per-line,
* in the `NAME=VALUE` format
* no quoting

Example:

    SYMFONY_ENV=prod
    FOO_API_KEY=7BMnTzAIoTeVg
    FOO=some value with spaces

### Configuring environment variables in the `.build.yml` file

You can also configure custom environment variables in your `.build.yml` file, using the `env` configuration key:

    env:
        - SYMFONY_ENV=prod
        - FOO_API_KEY=7BMnTzAIoTeVg
        - FOO=some value with spaces