---
layout: post
title: Configuring custom URLs for my staging environment
published: true
category: general
---

Some applications are multi-domain and load different configurations or data sets depending on the domain through which they are accessed. Stage1 supports this kind of behavior by allowing easy configuration of arbitrary subdomains for staging instances.

### Configuring domains from the web UI

Head to the project's **Admin** tab, and scroll to the **Project's URLs** section:

![Administration tab project's URLs form](/assets/screenshots/project-urls.png)

You may add as many domains as you want, one per line. URLs will be constructed by prepending subdomains to the original instance's URL. For example, if you're adding subdomains `foo` and `bar` to project `AcmeMuda/AcmeShop`, you will get. in addition to the original URL, these two URLs for each branch:

* `foo.branch.acmemuda-acmeshop.stage1.io`
* `bar.branch.acmemuda-acmeshop.stage1.io`

With _branch_ being the name of the branch, as usual.

### Configuring domains in the `.build.yml` file

You can also configure custom domains in your `.build.yml` file, using the `urls` configuration key:

    urls:
      - foo
      - bar