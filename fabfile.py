from fabric.api import *
from fabric.colors import *

env.host_string = 'stage1-prod'
env.project_path = '/vagrant'
env.use_ssh_config = True
env.rsync_exclude_from = './app/Resources/rsync-exclude.txt'

env.processes = ['consumer-build', 'consumer-kill', 'websocket-build', 'websocket-build-output']

def provision():
    with settings(warn_only=True):
        if run('test -f /etc/apt/sources.list.d/dotdeb.list').succeeded:
            info('already provisioned, skipping')
            return

    info('provisioning host')

    with cd('/tmp'):
        put('app/Resources/support/config/apt/dotdeb.list', 'apt-dotdeb.list')
        put('app/Resources/support/config/apt/rabbitmq.list', 'apt-rabbitmq.list')
        put('app/Resources/support/config/apt/sources.list', 'apt-sources.list')
        put('app/Resources/support/config/nginx/prod/default', 'nginx-default')
        put('app/Resources/support/config/nginx/prod/htpasswd', 'nginx-htpasswd')
        put('app/Resources/support/config/php/prod.ini', 'php-php.ini')
        put('app/Resources/support/scripts/stage1.sh', 'stage1.sh')

        with settings(warn_only=True):
            if run('test -d /usr/lib/vmware-tools').succeeded:
                put('app/Resources/support/config/rabbitmq/vm.config', 'rabbitmq-rabbitmq.config')

    sudo('bash /tmp/stage1.sh')
    reboot()

def needs_cold_deploy():
    with settings(warn_only=True):
        return run('test -d %s' % env.project_path).failed

def prepare():
    with settings(warn_only=True):
        if run('test -d %s' % env.project_path).succeeded:
            info('already prepared, skipping')
            return

    info('preparing host')
    sudo('mkdir %s' % env.project_path)
    sudo('chown -R www-data:www-data %s' % env.project_path)

def create_database():
    run('%s/app/console --env=prod doctrine:database:create' % env.project_path)

def init_parameters():
    run('cp %s/app/config/parameters.yml.dist %s/app/config/parameters.yml' % (env.project_path, env.project_path))

def docker_build():
    sudo('docker build -t symfony2 %s/docker' % env.project_path)

def deploy():
    with hide('running', 'stdout', 'stderr'):
        if needs_cold_deploy():
            info('first time deploy')
            cold_deploy()
        else:
            hot_deploy()

def cold_deploy():
    prepare()
    branch = git_branch()
    info('deploying branch "%s"' % branch)

    prepare_deploy(branch)
    tag_release()
    reset_environment()

    processes_stop()
    rsync()

    with cd(env.project_path):
        docker_build()
        init_parameters()
        info('clearing remote cache')
        run('php app/console cache:clear --env=prod --no-debug')
        run('chmod -R 0777 app/cache app/logs')
        create_database()
        info('running database migrations')
        run('php app/console doctrine:schema:update --env=prod --no-debug --force')

    services_restart()
    processes_start()

    run('chown -R www-data:www-data %s' % env.project_path)
    run('chmod -R 0777 %s/app/cache %s/app/logs' % (env.project_path, env.project_path))

def hot_deploy():
    branch = git_branch()
    info('deploying branch "%s"' % branch)

    prepare_deploy(branch)
    tag_release()
    reset_environment()

    processes_stop()
    rsync()

    with cd(env.project_path):
        info('clearing remote cache')
        run('php app/console cache:clear --env=prod --no-debug')
        run('chmod -R 0777 app/cache app/logs')
        info('running database migrations')
        run('php app/console doctrine:schema:update --env=prod --no-debug --force')

    services_restart()
    processes_start()
    run('chown -R www-data:www-data %s' % env.project_path)
    run('chmod -R 0777 %s/app/cache %s/app/logs' % (env.project_path, env.project_path))

def info(string):
    print(green('---> %s' % string))

def warning(string):
    print(yellow('---> %s' % string))

def error(string):
    abord(red('---> %s' %string))

def is_release_tagged():
    return len(local('git tag --contains HEAD', capture=True)) > 0

def git_branch():
    return local('git symbolic-ref -q HEAD | sed -e \'s|^refs/heads/||\'', capture=True)

def processes_stop():
    info('stopping processes')
    run('monit stop all')

def processes_start():
    info('starting processes')
    run('monit start all')

def services_restart():
    sudo('/etc/init.d/nginx restart')
    sudo('/etc/init.d/php5-fpm restart')
    sudo('/etc/init.d/rabbitmq-server restart')

@runs_once
def prepare_deploy(ref='HEAD'):
    info('checking that the repository is clean')
    with settings(warn_only=True):
        if (1 == local('git diff --quiet --ignore-submodules -- %s' % ref).return_code):
            error('Your repository is not in a clean state.')

        # local('git fetch --quiet origin refs/heads/%(ref)s:refs/remotes/origin/%(ref)s' % {'ref': ref})

        if (1 == local('git diff --quiet --ignore-submodules -- remotes/origin/%s' % ref)):
            error('Please merge origin before deploying')

@runs_once
def tag_release():
    if is_release_tagged():
        info('release is already tagged, skipping')
        return

    tag = local('git describe --tags $(git rev-list --tags --max-count=1) | sed \'s/v//\' | awk -F . \'{ printf "v%d.%d.%d", $1, $2, $3 + 1 }\'', capture=True)
    local('git tag %s' % tag)
    info('tagged version %s' % tag)

    local('git push --tags')

@runs_once
def reset_environment():
    info('resetting local environment')
    local('php app/console cache:clear --env=prod --no-debug --no-warmup')
    local('php app/console cache:clear --env=dev --no-debug --no-warmup')
    local('composer dump-autoload --optimize')

def rsync():
    info('rsyncing to remote')
    c = "rsync --verbose --rsh=ssh --exclude-from=%(exclude_from)s --progress -crDpLt --force --delete ./ %(host)s:%(remote)s" % {
        'exclude_from': env.rsync_exclude_from,
        'host': env.host_string,
        'remote': env.project_path
    }

    local(c)
