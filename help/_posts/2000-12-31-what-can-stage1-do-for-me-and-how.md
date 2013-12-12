---
layout: post
title: What can Stage1 do for me, and how?
publish: true
category: general
---

**Stage1** helps you gather feedback for your web application projects. It does so by automating the production of staging environments, be it for internal reviewing purpose or for presentation to your clients or stakeholders. Under the hood, it uses [Docker](http://docker.io/) to enable a reliable and repeatable environment building workflow.

Once setup, **Stage1 requires no action on your part to produce staging environments**.

### Importing a project

You start by importing a project from Github. The import process does a variety of things, 3 of which are especially worthy of your attention right now:

1. The importer inspects the project and checks that it is of a supported type (currently, only Symfony2 is supported)
2. If the project is private, it adds a deploy key in order to be able to clone it during builds
3. In all case, it installs a webhook to notify Stage1 of new commits, that will trigger new builds

![The project importer, after a successful import](/assets/screenshots/project-import-finished.png)

Once imported, each subsequent push on the project will trigger a build.

### Anatomy of a build

There are three main steps in a build:

1. Preparing the build container
2. The build itself
3. Deploying the container

Depending on your project type or configuration, a different base container will be used. Right now, most projects use [the Symfony2 base image]({% post_url 1900-12-29-the-symfony2-base-image %}/), which contains everything you need to run a Symfony2 projects as its name suggests. Prepation of the build container includes tasks like setting up ssh keys and configuration to be able to clone the repository.

> You can also use [a bare image]({% post_url 1900-12-30-the-ubuntu-precise-12-04-base-image %}/) and easily provision your build using existing provisioning tools, like [Puppet]({% post_url 1800-12-28-how-to-provision-my-containers-using-puppet %}/) (and more provisioning tools to come!)
{:.note}

The build itself is directed either by [Stage1's automated builder]({% post_url 2000-12-30-stage-1-s-automated-builder %}/) or by your [custom `.build.yml` configuration file]({% post_url 2000-12-29-customizing-a-build-with-the-build-yml-file %}/). The build output is streamed in real-time (or whatever that means in a web application) to Stage1's web console so you can monitor its progress and see error messages if any.

![Stage1 building PuPHPet and streaming output to the web console](/assets/screenshots/build-web-console.png)

Once the build is finished and successful, the container is deployed and you can access your staging environment.

> Stage1 environments are specific to one branch of your project. That means you can have one environment per branch, removing the pain of having to merge all branches to a __staging__ branch before reviewing.
{:.note}

### Requirements

In its current state, **Stage1** has three major restrictions:

* your projects **MUST** be hosted at [Github](http://github.com/)
* you **MUST** be using the [Symfony2](http://www.symfony.com/) framework
* your dependencies **SHOULD** be hosted at Github too, or **MUST** be accessible from the internet (no internal Gitlab instance for example)

> Stage1's builder is flexible enough to build any sort of web project, but we are concentrating our efforts on making the experience seemless for users of the Symfony2 framework (because, that's what we know best).
{:.note}

A private beta will be available to non Symfony2 projects very soon, [signup now](http://stage1.io/beta) to be contacted when other technologies will be supported.

### Profit!

That's the core of Stage1 right now. Automated per-branch staging environments that you can count on. You can use it for your internal products review, or to instantly being able to show a new feature to your client. Of course, a full-featured staging platform can and should be more than just that, and we have tons of ideas and are working hard to make Stage1 a __de facto__ standard staging platform, from more easier and to the point feedback to a better integration with your deployment workflow, so don't be let behind and <a href="http://stage1.io/beta">signup now for Stage1's beta</a>!