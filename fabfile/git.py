from fabric.api import *
from utils import info, error

def git_branch():
    return local('git symbolic-ref -q HEAD | sed -e \'s,^refs/heads/,,\'', capture=True)

def git_check_is_clean(ref):
    info('checking that the repository is clean')
    with settings(warn_only=True):
        if (1 == local('git diff --quiet --ignore-submodules -- %s' % ref).return_code):
            error('Your repository is not in a clean state.')

        # local('git fetch --quiet origin refs/heads/%(ref)s:refs/remotes/origin/%(ref)s' % {'ref': ref})

        if (1 == local('git diff --quiet --ignore-submodules -- remotes/origin/%s' % ref)):
            error('Please merge origin before deploying')

def git_tag_release():
    if len(local('git tag --contains HEAD', capture=True)) == 0:
        tag = local('git describe --tags $(git rev-list --tags --max-count=1) | sed \'s/v//\' | awk -F . \'{ printf "v%d.%d.%d", $1, $2, $3 + 1 }\'', capture=True)
        local('git tag %s' % tag)
        info('tagged version %s' % tag)
        local('git push origin --tags')