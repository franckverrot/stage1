from fabric.api import *
from utils import *
from git import *
from symfony import *

@task(default=True)
def web_and_build():
    execute(web)
    execute(build)

@task
@roles('help')
def help():
    local('jekyll build -s ./help -d ./help/_site')

    local('rsync --verbose --rsh=ssh --progress -crDpLt --force --delete ./help %(user)s@%(host)s:%(remote)s/' % {
        'user': env.user,
        'host': env.host_string,
        'remote': env.project_path
    })

@task
@roles('build')
def build():
    ref = git_branch()

    info('deploying branch "%s"' % ref)
    info('checking that the repository is clean')

    git_check_is_clean(ref)
    git_tag_release()

    symfony_reset_local_environment()

    rsync()

    with cd(env.project_path):
        symfony_reset_remote_environment()

@task
@roles('web')
def web():
    ref = git_branch()
    info('deploying branch "%s"' % ref)

    git_check_is_clean(ref)
    git_tag_release()

    symfony_reset_local_environment()
    symfony_prepare_assets()

    rsync()

    with cd(env.project_path):
        symfony_reset_remote_environment()
        symfony_run_migrations()
