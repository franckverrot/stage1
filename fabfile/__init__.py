from fabric.api import *
from time import sleep

import deploy

env.hosts = ['batcave.stage1.io', 'alpha.stage1.io']

env.roledefs = {
    'web': ['batcave.stage1.io'],
    'help': ['batcave.stage1.io'],
    'blog': ['batcave.stage1.io'],
    'worker': ['batcave.stage1.io'],
    'docker': ['alpha.stage1.io'],
}

# env.host_string = 'stage1.io'
env.user = 'root'
env.project_path = '/var/www/stage1'
env.upstart_path = '/etc/init'
env.remote_dump_path = '/root/dump'
env.local_dump_path = '~/dump'
env.use_ssh_config = True
env.rsync_exclude_from = './app/Resources/rsync-exclude.txt'

env.processes_prefix = 'stage1'
env.processes = [
    'consumer-build',
    'consumer-kill',
    'consumer-project-import',
    'consumer-docker-output',
    'websockets',
    'aldis',
    'hipache',
]

env.log_files = [
    '/var/log/nginx/*.log',
    '/var/log/stage1/*.log',
    '%s/app/logs/*.log' % env.project_path,
    '/var/log/syslog',
    '/var/log/php5-fpm.log',
    '/var/log/mysql/error.log',
]

# @task
# def backup():
#     run('mkdir -p %s' % env.remote_dump_path);
#     run('mysqldump -u root symfony | gzip > %s/stage1.sql.gz' % env.remote_dump_path)
#     run('cp /var/lib/redis/dump.rdb %s/dump.rdb' % env.remote_dump_path)
#     local('rsync --verbose --rsh=ssh --progress -crDpLt --force --delete %(user)s@%(host)s:%(remote)s/* %(local)s' % {
#         'user': env.user,
#         'host': env.host_string,
#         'remote': env.remote_dump_path,
#         'local': env.local_dump_path
#     })

# @task
# def inject():
#     if not local('test -d %s' % env.local_dump_path).succeeded:
#         info('nothing to inject')
#         exit

#     local('mysqladmin -u root -f drop symfony')
#     local('mysqladmin -u root create symfony')
#     local('gunzip -c %s/stage1.sql.gz | mysql -u root symfony' % env.local_dump_path)

#     local('sudo service redis-server stop')
#     local('sudo cp %s/dump.rdb /var/lib/redis/dump.rdb' % env.local_dump_path)
#     local('sudo service redis-server start')

#     hipache_init_redis_local()

#     local('sudo restart %s-hipache' % env.processes_prefix)
#     local('sudo restart %s-websockets' % env.processes_prefix)

# @task
# def restore():
#     if not local('test -d %s' % env.local_dump_path).succeeded:
#         info('nothing to restore')
#         exit

#     local('rsync --verbose --rsh=ssh --progress -crDpLt --force --delete %(local)s/* %(user)s@%(host)s:%(remote)s' % {
#         'user': env.user,
#         'host': env.host_string,
#         'remote': env.remote_dump_path,
#         'local': env.local_dump_path
#     })

#     run('mysqladmin -u root -f drop symfony')
#     run('mysqladmin -u root create symfony')
#     run('gunzip -c %s/stage1.sql.gz | mysql -u root symfony' % env.remote_dump_path)

#     run('service redis-server stop')
#     run('cp %s/dump.rdb /var/lib/redis/dump.rdb' % env.remote_dump_path)
#     run('service redis-server start')

#     run('restart %s-hipache' % env.processes_prefix)
#     run('restart %s-websockets' % env.processes_prefix)

# @task
# def upstart_export():
#     local('sudo foreman export upstart /etc/init -u root -a stage1')

# @task
# def upstart_deploy():
#     local('sudo rm -rf /tmp/init/*')
#     local('sudo foreman export upstart /tmp/init -u root -a stage1')
#     local('sudo find /tmp/init -type f -exec sed -e \'s!/vagrant!%s!\' -e \'s! export PORT=.*;!!\' -i "{}" \;' % env.project_path)
#     local('rsync --verbose --rsh=ssh --progress -crDpLt --force --delete /tmp/init/* %(user)s@%(host)s:%(remote)s' % {
#         'user': env.user,
#         'host': env.host_string,
#         'remote': env.upstart_path
#     })


# @task
# def rm_cache():
#     sudo('rm -rf %s/app/cache/*' % env.project_path)

# @task
# def llog():
#     local('sudo tail -f %s' % ' '.join(env.log_files))

# @task
# def log():
#     sudo('tail -f %s' % ' '.join(env.log_files))

# @task
# def log_build():
#     sudo('tail -f /tmp/log/consumer-build.*.log')

# @task
# def prepare_assets():
#     info('preparing assets')
#     local('app/console assetic:dump --env=prod --no-debug')

# @task
# def provision():
#     # with settings(warn_only=True):
#         # if run('test -f /etc/apt/sources.list.d/dotdeb.list').succeeded:
#             # info('already provisioned, skipping')
#             # return

#     info('provisioning %s' % env.host_string)

#     with cd('/tmp'):
#         put('packer/support/config/apt/dotdeb.list', 'apt-dotdeb.list')
#         put('packer/support/config/apt/docker.list', 'apt-docker.list')
#         put('packer/support/config/apt/rabbitmq.list', 'apt-rabbitmq.list')
#         put('packer/support/config/apt/sources.list', 'apt-sources.list')
#         put('packer/support/config/nginx/prod/default', 'nginx-default')
#         put('packer/support/config/php/prod.ini', 'php-php.ini')
#         put('packer/support/config/grub/default', 'grub-default')
#         put('packer/support/config/docker/default', 'docker-default')
#         put('packer/support/scripts/stage1.sh', 'stage1.sh')
#         put('packer/support/scripts/prod.sh', 'prod.sh')

#         with settings(warn_only=True):
#             if run('test -d /usr/lib/vmware-tools').succeeded:
#                 put('packer/support/config/rabbitmq/vm.config', 'rabbitmq-rabbitmq.config')

#     sudo('bash /tmp/stage1.sh')
#     sudo('bash /tmp/prod.sh')
#     # reboot()

# @task
# def check_connection():
#     run('echo "OK"')

# @task
# def redis_list_frontends():
#     run('redis-cli keys frontend:\\*')

# @task
# def redis_list_auths():
#     run('redis-cli keys auth:\\*')

# @task
# def redis_flushall():
#     info('flushing redis')
#     run('redis-cli FLUSHALL')

# @task
# def hipache_init_redis():
#     info('initializing redis for hipache')
#     run('redis-cli DEL frontend:%s' % env.host_string)
#     run('redis-cli RPUSH frontend:%s stage1 http://127.0.0.1:8080/' % env.host_string)
#     run('redis-cli DEL frontend:help.%s' % env.host_string)
#     run('redis-cli RPUSH frontend:help.%s help http://127.0.0.1:8080/' % env.host_string)

# @task
# def hipache_init_redis_local():
#     local('redis-cli DEL frontend:stage1.dev')
#     local('redis-cli RPUSH frontend:stage1.dev stage1 http://127.0.0.1:8080/')
#     local('redis-cli DEL frontend:help.stage1.dev')
#     local('redis-cli RPUSH frontend:help.stage1.dev help http://127.0.0.1:8080/')

# @task
# def fix_permissions():
#     with settings(warn_only=True):
#         if run('test -d %s/app/cache' % env.project_path).succeeded:
#             info('fixing cache and logs permissions')
#             www_user = run('ps aux | grep -E \'nginx\' | grep -v root | head -1 | cut -d\  -f1')
#             sudo('setfacl -R -m u:%(www_user)s:rwX -m u:$(whoami):rwX %(project_path)s/app/cache %(project_path)s/app/logs' % { 'www_user': www_user, 'project_path': env.project_path })
#             sudo('setfacl -dR -m u:%(www_user)s:rwX -m u:$(whoami):rwX %(project_path)s/app/cache %(project_path)s/app/logs' % { 'www_user': www_user, 'project_path': env.project_path })


# @task
# def needs_cold_deploy():
#     with settings(warn_only=True):
#         return run('test -d %s' % env.project_path).failed

# @task
# def prepare():
#     with settings(warn_only=True):
#         if run('test -d %s' % env.project_path).succeeded:
#             info('already prepared, skipping')
#             return

#     info('preparing host')
#     sudo('mkdir %s' % env.project_path)
#     sudo('chown -R www-data:www-data %s' % env.project_path)

# @task
# def reset_database():
#     processes_stop()
#     with cd(env.project_path):
#         run('app/console --env=prod doctrine:database:drop --force')
#         run('app/console --env=prod doctrine:database:create')
#         run('app/console --env=prod doctrine:schema:update --force')
#     processes_start()
#     docker_clean()

# @task
# def create_database():
#     run('%s/app/console --env=prod doctrine:database:create' % env.project_path)

# @task
# def init_parameters():
#     run('cp %s/app/config/parameters.yml.dist %s/app/config/parameters.yml' % (env.project_path, env.project_path))
#     run('cp %s/app/config/github.yml.dist %s/app/config/github.yml' % (env.project_path, env.project_path))

# @task
# def docker_update():
#     docker_build()

# @task
# def docker_build():
#     info('building docker')
#     sudo('docker build -t stage1 %s/docker/stage1' % env.project_path)
#     sudo('docker build -t php %s/docker/php' % env.project_path)
#     sudo('docker build -t symfony2 %s/docker/symfony2' % env.project_path)

# @task
# def docker_clean():
#     info('cleaning docker')
#     sudo('%s/bin/docker/clean.sh' % env.project_path)

# @task
# def deploy():
#     if needs_cold_deploy():
#         info('first time deploy')
#         cold_deploy()
#     else:
#         hot_deploy()

# @task
# def cold_deploy():
#     prepare()
#     branch = git_branch()
#     info('deploying branch "%s"' % branch)

#     prepare_deploy(branch)
#     tag_release()
#     reset_environment()

#     prepare_assets()

#     processes_stop()
#     rsync()

#     fix_permissions()

#     with cd(env.project_path):
#         docker_build()
#         init_parameters()
#         info('clearing remote cache')
#         run('php app/console cache:clear --env=prod --no-debug')
#         run('chmod -R 0777 app/cache app/logs')
#         create_database()
#         info('running database migrations')
#         run('php app/console doctrine:schema:update --env=prod --no-debug --force')

#     # services_restart()
#     processes_start()

#     run('chown -R www-data:www-data %s' % env.project_path)
#     run('chmod -R 0777 %s/app/cache %s/app/logs' % (env.project_path, env.project_path))

# @task
# def hot_deploy():
#     branch = git_branch()
#     info('deploying branch "%s"' % branch)

#     prepare_deploy(branch)
#     tag_release()
#     reset_environment()

#     prepare_assets()

#     # processes_stop()
#     rsync()

#     with cd(env.project_path):
#         info('clearing remote cache')
#         run('php app/console cache:clear --env=prod --no-debug')
#         run('chmod -R 0777 app/cache app/logs')
#         info('running database migrations')
#         run('php app/console doctrine:schema:update --env=prod --no-debug --force')

#     # services_restart()
#     # processes_start()
#     run('chown -R www-data:www-data %s' % env.project_path)
#     run('chmod -R 0777 %s/app/cache %s/app/logs' % (env.project_path, env.project_path))

# def info(string):
#     print(green('---> %s' % string))

# def warning(string):
#     print(yellow('---> %s' % string))

# def error(string):
#     abord(red('---> %s' %string))

# @task
# def is_release_tagged():
#     return len(local('git tag --contains HEAD', capture=True)) > 0

# @task
# def git_branch():
#     return local('git symbolic-ref -q HEAD | sed -e \'s,^refs/heads/,,\'', capture=True)

# @task
# def builder_restart():
#     info('restarting builder')
#     with settings(warn_only=True):
#         sudo('restart %s-consumer-build' % env.processes_prefix)

# @task
# def processes_stop():
#     info('stopping processes')
#     with settings(warn_only=True):
#         for process in env.processes:
#             sudo('stop %s-%s' % (env.processes_prefix, process))

# @task
# def processes_start():
#     info('starting processes')
#     with settings(warn_only=True):
#         for process in env.processes:
#             sudo('start %s-%s' % (env.processes_prefix, process))

# @task
# def processes_restart():
#     info('restarting processes')
#     with settings(warn_only=True):
#         for process in env.processes:
#             sudo('restart %s-%s' % (env.processes_prefix, process))

# @task
# def services_restart():
#     sudo('/etc/init.d/nginx restart')
#     sudo('/etc/init.d/php5-fpm restart')
#     sudo('/etc/init.d/rabbitmq-server restart')

# @runs_once
# @task
# def prepare_deploy(ref='HEAD'):
#     info('checking that the repository is clean')
#     with settings(warn_only=True):
#         if (1 == local('git diff --quiet --ignore-submodules -- %s' % ref).return_code):
#             error('Your repository is not in a clean state.')

#         # local('git fetch --quiet origin refs/heads/%(ref)s:refs/remotes/origin/%(ref)s' % {'ref': ref})

#         if (1 == local('git diff --quiet --ignore-submodules -- remotes/origin/%s' % ref)):
#             error('Please merge origin before deploying')

# @task
# def diff():
#     last_tag = local('git describe --tags $(git rev-list --tags --max-count=1)', capture=True)
#     local('git log --pretty=oneline --color %s..' % last_tag)

# @runs_once
# @task
# def tag_release():
#     if is_release_tagged():
#         info('release is already tagged, skipping')
#         return

#     tag = local('git describe --tags $(git rev-list --tags --max-count=1) | sed \'s/v//\' | awk -F . \'{ printf "v%d.%d.%d", $1, $2, $3 + 1 }\'', capture=True)
#     local('git tag %s' % tag)
#     info('tagged version %s' % tag)

#     local('git push origin --tags')

# @runs_once
# @task
# def reset_environment():
#     info('resetting local environment')
#     local('php app/console cache:clear --env=prod --no-debug --no-warmup')
#     # local('php app/console cache:clear --env=dev --no-debug --no-warmup')
#     local('composer dump-autoload --optimize')

# @task
# def rsync():
#     info('rsyncing to remote')
#     c = "rsync --verbose --rsh=ssh --exclude-from=%(exclude_from)s --progress -crDpLt --force --delete ./ %(user)s@%(host)s:%(remote)s" % {
#         'exclude_from': env.rsync_exclude_from,
#         'user': env.user,
#         'host': env.host_string,
#         'remote': env.project_path
#     }

#     local(c)
