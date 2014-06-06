---
layout: post
published: true
title: How to change my project's base image
category: base images
---

The base image is determines what services and packages are available out of the box during your builds.

> Right now, there are only two base images: **Ubuntu Precise (12.04)** and **Symfony2**.
{:.note.oneline }

### Configuring your base image through the web UI

You can change your project's base image in the **Admin** tab of your project:

![Change your project's base image](/assets/screenshots/project-base-image.png)

### Configuring your base image in the `.stage1.yml` file

You can also configure your base image in the `.stage1.yml` file, using the `image` configuration key:

    image: symfony2
