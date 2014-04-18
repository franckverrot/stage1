FROM stage1/php
MAINTAINER Geoffrey Bachelet <geoffrey.bachelet@gmail.com>

ADD ./support/bin/stage1_image_init_build.sh /usr/local/bin/stage1_image_init_build
ADD ./support/bin/stage1_image_init_run.sh /usr/local/bin/stage1_image_init_run

RUN chmod +x /usr/local/bin/stage1_image_init_*