from fabric.api import *
from utils import info

# @task(name='prepare_assets')
def symfony_prepare_assets():
    info('preparing assets')
    local('app/console assetic:dump --env=prod --no-debug')

# @task(name='reset_local_environment', alias='reset_local_env')
def symfony_reset_local_environment():
    info('resetting local environment')
    local('php app/console cache:clear --env=prod --no-debug --no-warmup')
    local('composer dump-autoload --optimize')

# @task(name='reset_remote_environment', alias='reset_remote_env')
def symfony_reset_remote_environment():
    info('clearing remote cache')
    run('php app/console cache:clear --env=prod --no-debug')
    run('chown -R www-data:www-data .')
    run('chmod -R 0777 app/cache app/logs')

# @task(name='run_migrations', alias='migrate')
def symfony_run_migrations():
    info('running database migrations')
    run('php app/console doctrine:schema:update --env=prod --no-debug --force')

# @task(name='fix_permissions', alias='fix_perms')
def symfony_fix_permissions():
    with settings(warn_only=True):
        if run('test -d %s/app/cache' % env.project_path).succeeded:
            info('fixing cache and logs permissions')
            www_user = run('ps aux | grep -E \'nginx\' | grep -v root | head -1 | cut -d\  -f1')
            sudo('setfacl -R -m u:%(www_user)s:rwX -m u:$(whoami):rwX %(project_path)s/app/cache %(project_path)s/app/logs' % { 'www_user': www_user, 'project_path': env.project_path })
            sudo('setfacl -dR -m u:%(www_user)s:rwX -m u:$(whoami):rwX %(project_path)s/app/cache %(project_path)s/app/logs' % { 'www_user': www_user, 'project_path': env.project_path })