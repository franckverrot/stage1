<?php

namespace App\CoreBundle;

use Symfony\Component\Process\ProcessBuilder;

class SshKeys
{
    static public function generate($comment = 'stage1 deploy key')
    {
        $private = tempnam(sys_get_temp_dir(), 'ssh-keygen-');
        $public  = $private.'.pub';

        unlink($private);

        $builder = new ProcessBuilder(['/usr/bin/ssh-keygen', '-q', '-t', 'rsa', '-f', $private, '-C', 'stage1 deploy key']);
        $process = $builder->getProcess();

        $process->run();

        return [
            'private' => file_get_contents($private),
            'public'  => file_get_contents($public),
        ];
    }
}