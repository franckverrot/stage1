<?php

namespace App\CoreBundle;

use Symfony\Component\Process\ProcessBuilder;

use App\CoreBundle\Entity\Project;

class SshKeys
{
    static public function dump(Project $project, $file = null)
    {
        if (null === $file) {
            $file = tempnam(sys_get_temp_dir(), 'build-ssh-keys-');
        }

        file_put_contents($file, $project->getPrivateKey());
        file_put_contents($file.'.pub', $project->getPublicKey());

        return $file;
    }

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