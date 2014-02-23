FROM stage1

ADD ./support/id_rsa /root/.ssh/id_rsa
ADD ./support/id_rsa.pub /root/.ssh/id_rsa.pub

RUN curl -sS https://getcomposer.org/installer | php
RUN mv composer.phar /usr/local/bin/composer

RUN git clone git@github.com:ubermuda/yuhao.git /root/yuhao
RUN cd /root/yuhao && composer install --ansi --no-dev --prefer-dist

ADD ./support/YuhaoDefaultBuilder.php /root/YuhaoDefaultBuilder.php
ADD ./support/authorized_keys /root/.ssh/authorized_keys
ADD ./support/bin/default_build.sh /usr/local/bin/default_build
ADD ./support/bin/default_run.sh /usr/local/bin/default_run
ADD ./support/php.ini /etc/php5/cli/php.ini
ADD ./support/bin/yuhao.sh /usr/local/bin/yuhao

RUN chmod -R +x /usr/local/bin

# ENTRYPOINT ["yuhao"]