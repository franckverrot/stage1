from fabric.api import *
from utils import *

@task
def export():
    local('sudo mkdir -p /etc/service')
    local('sudo rm -rf /etc/service/*')
    local('sudo cp -r ./service/web/* /etc/service/')
    local('sudo cp -r ./service/build/* /etc/service/')

@task
def deploy():
    execute(deploy_build)
    execute(deploy_web)
    execute(deploy_router)
    execute(ensure_executable)

@task
@roles('web', 'build')
def ensure_executable():
    run('find /etc/service -name run -exec chmod +x {} \;')

def rsync(type, user, host):
    local('rsync --verbose --rsh=ssh --progress -crDpLt --force --delete ./service/%(type)s/ %(user)s@%(host)s:/etc/service/' % {
        'type': type,
        'user': user,
        'host': host
    })


@task
@roles('build')
def deploy_build():
    rsync('build', env.user, env.host_string)

@task
@roles('web')
def deploy_web():
    rsync('web', env.user, env.host_string)

@task
@roles('router')
def deploy_router():
    rsync('router', env.user, env.host_string)