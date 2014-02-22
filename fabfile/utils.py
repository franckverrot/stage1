from fabric.api import *
from fabric.colors import *

def info(string):
    print(green('---> %s' % string))

def warning(string):
    print(yellow('---> %s' % string))

def error(string):
    abort(red('---> %s' %string))

def rsync():
    info('rsyncing to remote')
    c = "rsync --verbose --rsh=ssh --exclude-from=%(exclude_from)s --progress -crDpLt --force --delete ./ %(user)s@%(host)s:%(remote)s" % {
        'exclude_from': env.rsync_exclude_from,
        'user': env.user,
        'host': env.host_string,
        'remote': env.project_path
    }

    local(c)