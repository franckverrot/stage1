<?php

namespace App\CoreBundle;

use Symfony\Component\Process\ProcessBuilder;

use App\CoreBundle\Entity\Project;

use RuntimeException;

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

        $builder = new ProcessBuilder(['/usr/bin/ssh-keygen', '-q', '-N', '', '-t', 'rsa', '-f', $private, '-C', 'Stage1 Deploy Key']);
        $process = $builder->getProcess();

        $process->run();

        if (!$process->isSuccessful()) {
            throw new RuntimeException($process->getErrorOutput());
        }

        return [
            'private' => file_get_contents($private),
            'public'  => file_get_contents($public),
        ];
    }
}