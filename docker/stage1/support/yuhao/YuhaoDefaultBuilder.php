<?php

use Yuhao\Adapter\AdapterInterface;

class YuhaoDefaultBuilder implements AdapterInterface
{
    public function detect($source)
    {
        return true;
    }

    public function build($source)
    {
        return file_get_contents('/usr/local/bin/default_build');
    }

    public function run($source)
    {
        return file_get_contents('/usr/local/bin/default_run');
    }
}