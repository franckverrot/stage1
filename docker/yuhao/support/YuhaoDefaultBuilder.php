<?php

use Yuhao\Adapter\AdapterInterface;

use Symfony\Component\Yaml\Yaml;

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

    public function config($source)
    {
        return file_exists($source.'/.build.yml') ? Yaml::parse($source.'/.build.yml') : [];
    }
}