---
layout: post
title: Configuring Symfony2 using environment variables
category: Symfony
published: true
---

One less known feature of Symfony2 is that you can [inject kernel parameters using environment variables](http://symfony.com/doc/current/cookbook/configuration/external_parameters.html). This makes things incredibly easier when you want to configure your build.

For example, say your application relies on a third party API to which you authenticate using a secret token. You certainly don't want to commit your secret token in your repository just so that your application can work on Stage1. That's where environment variables come in handy. Declaring a `SYMFONY__SECRET_TOKEN` environment variable will inject a `secret_token` parameter in your application, so you just have to add the following to your Stage1 project's environment variables:

    SYMFONY__SECRET_TOKEN=SuchTokenWowManySecret

And you're all set!

> See the [setup custom environment variables in my containers]({% post_url 2000-12-26-setup-custom-environment-variables-in-my-containers %}/) help page to learn how to setup environment variables in your Stage1 projects.
{:.note}

Note that if you use [Incentive/ParameterHandler](https://github.com/Incenteev/ParameterHandler) to [manage your `parameters.yml` using environment variables](https://github.com/Incenteev/ParameterHandler#using-environment-variables-to-set-the-parameters), this will work just fine too.