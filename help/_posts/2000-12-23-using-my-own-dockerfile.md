---
layout: post
title: Using my own Dockerfile
published: true
category: general
---

If you already have a working `Dockerfile` that builds a working container for your app, good news! Stage1 will automatically use it to build your staging instances.

All you have to do for Stage1 to detect it is to place your `Dockerfile` at the root of your project.

Your application is expected to listen on port `80` of your container.

> Read more about [the Dockerfile format](http://docs.docker.io/reference/builder/).
{:.note.oneline}