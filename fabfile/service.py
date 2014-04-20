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
    execute(ensure_executable)

@task
@roles('web', 'build')
def ensure_executable():
    run('find /etc/service -name run -exec chmod +x {} \;')

@task
@roles('build')
def deploy_build():
    local('rsync --verbose --rsh=ssh --progress -crDpLt --force --delete ./service/build/ %(user)s@%(host)s:/etc/service/' % {
        'project_path': env.project_path,
        'user': env.user,
        'host': env.host_string
    })

@task
@roles('web')
def deploy_web():
    local('rsync --verbose --rsh=ssh --progress -crDpLt --force --delete ./service/web/ %(user)s@%(host)s:/etc/service/' % {
        'project_path': env.project_path,
        'user': env.user,
        'host': env.host_string    
    })