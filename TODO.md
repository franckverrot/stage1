TODO
====

Screencasts
-----------

1. plan sur le desktop du developpeur qui bosse
2. notif IM du client "there's a bug on <url>, <description du bug>"
3. plan sur developpeur qui cree une branche, fix le bug et push sur github
4. plan sur stage1 qui build automatiquement la branche sus-citee
5. plan sur l'IM du dev qui envoie l'url au client
6. plan sur le desktop du client qui clique sur le lien et navigue sur sa preprod
7. plan sur l'IM du client qui dit "bug is fixed, thanks!"

Gustave Integration
-------------------

* Detecter que la branche en cours (ou page) est liee a une issue/PR,
    * avoir un bouton "confirm bug fix"
        * passer l'issue en "fixed" ou "ready to merge" ou whatever
        * laisser un commentaire sur l'issue "ready to merge"
        * auto-merger la PR si possible


GUIDELINES
----------

* Whenever (if) we need to add code to a repo, propose to create a PR, not to commit it directly

SCALING
=======

* feature switches for (almost) everything
* read-only mode
* shutdown container after X minutes of idle, re-start them on-demand

THIRD PARTY INTEGRATION
=======================

* https://github.com/marmelab/gaudi
* http://wercker.com/
* GitHub statuses API
* Gitlab
* Bitbucket
* Kiln
* Pagodabox Boxfile
* http://voicechatapi.com/
* phpci
* phabricator
* mailcatcher
* gor (https://github.com/buger/gor)

TODO
====

* Number of builds: add a tooltip explaining exactly what builds are counted

* Github
    * periodically check for access_token validity and warn user if it expired

* Commands
    * commands to empty redis

* REST API / webhooks

* help
    * how to use sass/compass
    * how to provision my containers using...
        * puppet
        * chef
        * cfengine
        * ansible
        * salt
        * an heroku buildpack

* logs
    * application logs

* dashboard
    * list of running instances grouped by project and order by project.last_build_id

* production
    * everything needs to transit through SSL
    * needs to have working error reporting
    * needs to have proper monitoring

* instances
    * auth: have the reverse proxy render the auth page instead of redirecting to the php app (for custom domains)
    * allow php.ini customization and set error_reporting back to E_ALL | E_STRICT

* builds
    * switch runtime to supervisor or something to make sure everything runs as it should
    * check that the runtime container was successfuly built
    * check the desired branch is available
    * running and building status are confusing
    * the build process must be completely error/fool proof
    * make the symfony builder a bit more clever
        * things that should work ootb
            * doctrine orm/odm
            * doctrine/alice fixtures
            * mysql/pgsql
            * mongodb/couchdb
            * redis/memcached
    * disallow scheduling a build if a running instance for the same hash exists
    * log trigger source (manual / push)
    * before starting a build, check that the required docker image is present
    * rewrite the build system, with extensibility in mind
        * build steps could be infered at import time (or pre-build time)
        * also, if a specific step fail, we know what it exactly is, so we can try and guess why it did fail and
          tell the user steps to take to fix it (or maybe fix it automatically, I'm looking at you, missing deploy key)
    * container ports should be bound on local private IPs instead of stage1.io public IP
    * alerts
        * push notif
        * mail
        * webhooks
    * support Procfiles (?)

* projects
    * support dependencies in a gitlab instance
    * update project access when an user joins the project
    * last_build_at + last_build_ref (last_build_id ?)
    * access (?)
        * implement "one-time-access-granting" URLs
        * implement "one-time" passwords
        * implement auto-expiring accesses
        * implement "access-granting-referers" (automatically gain access if you come from the project's github for example)
    * on remove
        * remove deploy key
        * remove hook

* public admin
    * public dashboard
        * global number of builders
        * number of availables builders
        * number of running builders
        * number of running instances

* admin
    * bundle to override container parameters per user

* misc UI
    * show useful indicators of what's happening in the title bar when the window is out of focus
    * all "stateful buttons" should present an option to "retry" when failed
    * timeout long running ajax queries with a message like "uhoh, this is taking longer than expected"
    * pre-disabled buttons should change to enabled state when needed (through websockets events eg)

* websockets
    * update project access page when an ip is added to the access list
    * update project access page when a master password is set/unset
    * buffered channels implementation is a terrible mess
    * do not subscribe a user to all sub-channels he's allowed to, but rather
      check if an incoming message is going through a channel that's a sub-channel
      of a currently subscribed channel

* VM / provisioning
    * use GNU parallel to speed up VM packing and provisioning

GITHUB ENTERPRISE
=================

A problem will be github's API rate limit and composer, because the access token we have is not a valid github.com access token (it's a github enterprise token). So we have two, maybe three, solutions:

1. acquire a github.com application token specific to this stage1 instance
2. acquire a github.com through the user
3. bundle the VM with an installation of satis

THE DISTANT FUTURE
==================

* builds
    * log queue time
    * separate builds and jobs
    * "build now", premium option to bypass all queuing rules and have your project built now, as in, NOW.
    * Support for build systems like make, ant, phing, etc

* instances
    * add a (disablable) top frame indicating what project / branch you're currently browsing
    * allow shutting down / restarting a running instance
    * http://mailcatcher.me/
    * reverse proxy: remove auth cookie in proxied request and reput it in response

* projects
    * allow project creation through build poke (?)
    * autodetect new projects 
        run periodic background checks to the user's github account for new importable projects
    * symfony
        * allow env selection (dev / prod)
    * allow non-github projects (?)

* bi-directionnal communication
    * update web ui accordingly for the following events
        * new project (detected through a build poke)

* runtimes
    * allow a user to build his own docker image and then assign it to his projects
    * internal docker registry where users can push their own runtimes
    * support buildpacks, packer, chef, puppet

* misc
    * pusher private channels to receive push notif about your projects' builds

DEMO
----

* add the place of the build in the builds queue (?)

DEMO PROJECTS
-------------

* symfony cmf / sulu cmf / sonata sandbox
* orocrm
* sylius
* puphpet (to remove, too confusing)
* akeneo
* ekino cms ?