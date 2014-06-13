# STAGE1, the continuous staging platform

STAGE1 is a continuous platform for the web, that is, something that continuously builds staging for your web projects.

## Installation

Installing STAGE1 can be a bit tricky since there is no provisioning configuration yet. This document will guide through this process. The stack is quite complete, so prepare to mess with a good lot of technologies, including:

* nginx
* php-fpm
* MySQL
* RabbitMQ
* nodejs
* docker
* daemontools

### Server roles

First thing to know is STAGE1 is divided in a number of component that each fit inside a particular server role. We can distinguish three of these roles:

1. The web server
2. The builder(s)
3. The router

The *web server* contains everything that is directly related to accessing the STAGE1 UI from the web – the main application server and the websockets – plus everything that does not fit in the other roles. The *builder* is where the real work happens and has everything needed to build your containers: docker and a few rabbitmq consumers. Finally, the *router* is responsible for routing build URLs to their respective containers.

Of course, a single host could manage all the roles, but we highly recommend to at least have a separate host for the builds, since it can get quiet resource intensive and you don't want the main UI to be dependant on the performance of the builder.

All provisioning is done using Ansible.

#### Provisioning for the web server

#### Provisioning for the builder

#### Provisioning for the router

### Websockets

### RabbitMQ consumers

### Docker base images

### Forwarding Docker's output

### Deploying with fabric