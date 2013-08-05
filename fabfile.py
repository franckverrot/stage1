from fabric.api import *
from fabric.colors import *

env.host_string = 'stage1-prod'
env.project_path = '/app'
env.use_ssh_config = True
env.rsync_exclude_from = './app/Resources/rsync-exclude.txt'

env.consumers = []

def setup():
    sudo('mkdir /app')
    sudo('chown vagrant /app')

def deploy():
    with hide('running', 'stdout'):
        branch = git_branch()
        info('deploying branch "%s"' % branch)

        prepare_deploy(branch)
        tag_release()
        reset_environment()

        consumers_stop()
        rsync()

        with cd(env.project_path):
            run('cp app/config/parameters.yml.dist app/config/parameters.yml')
            info('clearing remote cache')
            run('php app/console cache:clear --env=prod --no-debug')
            run('chmod -R 0777 app/cache app/logs')
            info('running database migrations')
            run('php app/console doctrine:schema:update --env=prod --no-debug --force')

        services_restart()
        consumers_start()

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

def consumers_stop():
    info('stopping consumers')
    for service in env.consumers:
        run('monit stop ' + service)

def consumers_start():
    info('starting consumers')
    for service in env.consumers:
        run('monit start ' + service)

def services_restart():
    sudo('/etc/init.d/nginx restart')
    sudo('/etc/init.d/php5-fpm restart')

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
    c = "rsync --quiet --rsh=ssh --exclude-from=%(exclude_from)s --progress -crDpLt --force --delete ./ %(host)s:%(remote)s" % {
        'exclude_from': env.rsync_exclude_from,
        'host': env.host_string,
        'remote': env.project_path
    }

    local(c)
