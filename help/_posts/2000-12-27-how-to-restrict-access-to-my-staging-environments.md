---
layout: post
title: How to restrict access to my staging environments
published: true
category: general
---

You can easily restrict access to your staging environments in the **Access** tab:

![The project's Access tab](/assets/screenshots/access-tab.png)

In this tab, you can set a **master password**, which will permit using anyone to grant themselves access to the environments. With a master password set, non-allowed people trying to access your staging environments will be prompted to enter a password in order to access the environment.

![A typical password prompt](/assets/screenshots/access-password-prompt.png)

> When you unset the **master password**, non-allowed people will not be able to see your project. This is done to avoid leaking a project's existence when you want to keep it secret.
{:.note}

When a person grants themselves access to a staging environment, a token is created and added to the access list to the right of the page, that you can revoke at any time.

![The revokable tokens list](/assets/screenshots/access-tokens-revoke.png)