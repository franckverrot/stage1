---
layout: post
title: The Symfony 2 base image
published: true
category: base images
---

The **Symfony 2** base image is a Docker image based on [the Ubuntu Precise (12.04) image]({% post_url 1900-12-30-the-ubuntu-precise-12-04-base-image %}/), with everything you need to run a standard Symfony 2 application pre-installed:

* [nginx](http://nginx.org/) 1.4.4
* [PHP](http://php.net/) (both FPM and CLI) 5.4.21
* [MySQL](http://mysql.com/) 5.5.31
* the latest available version of [Composer](http://getcomposer.org/)

### Configuration

* Nginx serves whether `index.php` or `app.php` in `/var/www/web` as a front controller.
* MySQL has a super-user called `root` that does not have a password.