---
layout: post
title: Using my own Dockerfile
published: true
category: general
---

If you already have a working `Dockerfile` that builds a working container for your app, good news! Stage1 will automatically use it to build your staging instances.

There are 3 things you need to be aware of when using a `Dockerfile`:

1. Your Dockerfile must be at the root of your project.
2. Your container is expected to listen on port `80`.
3. You need to set an `ENTRYPOINT` or a `CMD` in your `Dockerfile`.

You can see an example of a `Dockerfile` that builds and works on Stage1 in [M6Web's BabitchClient repository](https://github.com/M6Web/BabitchClient/blob/master/Dockerfile).

> Read more about [the Dockerfile format](http://docs.docker.io/reference/builder/).
{:.note.oneline}