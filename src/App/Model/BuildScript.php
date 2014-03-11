<?php

namespace App\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * BuildScript
 */
class BuildScript
{
    /**
     * @var string
     */
    private $buildScript;

    /**
     * @var string
     */
    private $runScript;

    /**
     * @var string
     */
    private $config;

    /**
     * @var integer
     */
    private $id;

    /**
     * @var \App\Model\Build
     */
    private $build;

    /**
     * @param string $json
     * 
     * @return App\Model\BuildScript
     */
    public static function fromJson($json)
    {
        $data = json_decode($json, true);

        $obj = new self();
        $obj->setBuildScript($data['build']);
        $obj->setRunScript($data['run']);
        $obj->setConfig($data['config']);

        return $obj;
    }


    /**
     * Set buildScript
     *
     * @param string $buildScript
     * @return BuildScript
     */
    public function setBuildScript($buildScript)
    {
        $this->buildScript = $buildScript;
    
        return $this;
    }

    /**
     * Get buildScript
     *
     * @return string 
     */
    public function getBuildScript()
    {
        return $this->buildScript;
    }

    /**
     * Set runScript
     *
     * @param string $runScript
     * @return BuildScript
     */
    public function setRunScript($runScript)
    {
        $this->runScript = $runScript;
    
        return $this;
    }

    /**
     * Get runScript
     *
     * @return string 
     */
    public function getRunScript()
    {
        return $this->runScript;
    }

    /**
     * Set config
     *
     * @param string $config
     * @return BuildScript
     */
    public function setConfig($config)
    {
        $this->config = $config;
    
        return $this;
    }

    /**
     * Get config
     *
     * @return string 
     */
    public function getConfig()
    {
        return $this->config ?: [];
    }

    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set build
     *
     * @param \App\Model\Build $build
     * @return BuildScript
     */
    public function setBuild(\App\Model\Build $build = null)
    {
        $this->build = $build;
    
        return $this;
    }

    /**
     * Get build
     *
     * @return \App\Model\Build 
     */
    public function getBuild()
    {
        return $this->build;
    }
    /**
     * @var array
     */
    private $runtimeEnv;


    /**
     * Set runtimeEnv
     *
     * @param array $runtimeEnv
     * @return BuildScript
     */
    public function setRuntimeEnv($runtimeEnv)
    {
        $this->runtimeEnv = $runtimeEnv;
    
        return $this;
    }

    /**
     * Get runtimeEnv
     *
     * @return array 
     */
    public function getRuntimeEnv()
    {
        return $this->runtimeEnv;
    }
}