<?php

namespace App\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * ProjectSettings
 */
class ProjectSettings
{
    /**
     * @var string
     */
    private $buildYml;

    /**
     * @var integer
     */
    private $id;

    /**
     * @var \App\CoreBundle\Entity\Project
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
     * @param \App\CoreBundle\Entity\Project $project
     * @return ProjectSettings
     */
    public function setProject(\App\CoreBundle\Entity\Project $project = null)
    {
        $this->project = $project;
    
        return $this;
    }

    /**
     * Get project
     *
     * @return \App\CoreBundle\Entity\Project 
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
     * Set branchPolicy
     *
     * @param string $branchPolicy
     * @return ProjectSettings
     */
    public function setBranchPolicy($branchPolicy)
    {
        $this->branchPolicy = $branchPolicy;
    
        return $this;
    }

    /**
     * Get branchPolicy
     *
     * @return string 
     */
    public function getBranchPolicy()
    {
        return $this->branchPolicy;
    }

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
}