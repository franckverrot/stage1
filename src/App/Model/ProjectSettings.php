<?php

namespace App\Model;

use Doctrine\ORM\Mapping as ORM;

/**
 * ProjectSettings
 */
class ProjectSettings
{
    const POLICY_ALL = 'all';
    const POLICY_NONE = 'none';
    const POLICY_PATTERNS = 'patterns';
    const POLICY_PR = 'pr';
    
    /**
     * @var string
     */
    private $buildYml;

    /**
     * @var integer
     */
    private $id;

    /**
     * @var \App\Model\Project
     */
    private $project;


    /**
     * Set buildYml
     *
     * @param string $buildYml
     * @return ProjectSettings
     */
    public function setBuildYml($buildYml)
    {
        $this->buildYml = $buildYml;
    
        return $this;
    }

    /**
     * Get buildYml
     *
     * @return string 
     */
    public function getBuildYml()
    {
        return $this->buildYml;
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
     * Set project
     *
     * @param \App\Model\Project $project
     * @return ProjectSettings
     */
    public function setProject(\App\Model\Project $project = null)
    {
        $this->project = $project;
    
        return $this;
    }

    /**
     * Get project
     *
     * @return \App\Model\Project 
     */
    public function getProject()
    {
        return $this->project;
    }
    /**
     * @var string
     */
    private $branchPolicy;

    /**
     * @var array
     */
    private $branchPatterns;


    /**
     * Set branchPatterns
     *
     * @param array $branchPatterns
     * @return ProjectSettings
     */
    public function setBranchPatterns($branchPatterns)
    {
        $this->branchPatterns = $branchPatterns;
    
        return $this;
    }

    /**
     * Get branchPatterns
     *
     * @return array 
     */
    public function getBranchPatterns()
    {
        return $this->branchPatterns;
    }
    /**
     * @var string
     */
    private $policy;


    /**
     * Set policy
     *
     * @param string $policy
     * @return ProjectSettings
     */
    public function setPolicy($policy)
    {
        $this->policy = $policy;
    
        return $this;
    }

    /**
     * Get policy
     *
     * @return string 
     */
    public function getPolicy()
    {
        return $this->policy;
    }
}