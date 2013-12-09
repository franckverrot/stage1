---
layout: post
title: Setup custom environment variables in my containers
published: true
category: general
---

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

> Environment variables are exported both in the build and the app containers, so you can use them anytime!
{:.note}