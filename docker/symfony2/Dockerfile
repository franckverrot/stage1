FROM php
MAINTAINER Geoffrey Bachelet <geoffrey.bachelet@gmail.com>

ADD ./support/bin/buildapp.sh /usr/local/bin/buildapp
ADD ./support/bin/runapp.sh /usr/local/bin/runapp
ADD ./support/bin/stage1_build_common.sh /usr/local/bin/stage1_build_common

ADD ./support/lib/stage1.sh /usr/local/lib/stage1.sh
ADD ./support/lib/builder/ /usr/local/lib/builder/

RUN chmod -R +x /usr/local/bin/buildapp /usr/local/bin/runapp /usr/local/bin/stage1_build_common /usr/local/lib/builder/

# RUN apt-get autoremove -qy vim curl

# EXPOSE 80
# EXPOSE 80 22