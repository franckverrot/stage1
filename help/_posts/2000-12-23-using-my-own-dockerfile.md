---
layout: post
title: Using my own Dockerfile
published: true
category: general
---

If you can't (or do not want to) use a `.stage1.yml` file to configure your build, Stage1 can use a `Dockerfile` to build your staging instances.

### Constraints

Please note that using a `Dockerfile` is considered a *poweruser* feature and there a number of facilities provided by the default builders that aren't available to `Dockerfile` users, including (but not limited to):

* receiving your app logs in the web console
* easy writable folders definition
* local `Dockerfile` definition

We are working hard to bring all those features to `Dockerfile` users.

### Requirements

There are 3 things you need to be aware of when using a `Dockerfile` with Stage1:

1. your Dockerfile must be at the root of your project
2. your container is expected to listen on port `80`
3. you need to set an `ENTRYPOINT` or a `CMD` in your `Dockerfile`

More flexibility is on the way, like being able to define which port(s) your application listens to, where is your `Dockerfile`, etc.

### Benefits 

That being said, using a `Dockerfile` still has a couple of benefits. Mainly, you are entirely free of the base image you want to use, and of the build process of your project. Basically, anything you can do at home with your `Dockerfile`, you can do with Stage1.

> Read more about [the Dockerfile format](http://docs.docker.io/reference/builder/).
{:.note.oneline}

### Example

You can see an example of a `Dockerfile` that builds and works on Stage1 in [M6Web's BabitchClient repository](https://github.com/M6Web/BabitchClient/blob/master/Dockerfile).
