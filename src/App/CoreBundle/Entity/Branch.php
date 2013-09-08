<?php

namespace App\CoreBundle\Entity;

class Branch
{
    protected $id;

    protected $name;

    protected $project;

    protected $createdAt;

    protected $updatedAt;

    protected $builds;

    /** not persisted **/

    protected $lastBuild;
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->builds = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function getNormName()
    {
        return preg_replace('/[^a-z0-9\-]/', '-', strtolower($this->getName()));
    }

    public function getLastBuild()
    {
        return $this->lastBuild;
    }

    public function setLastBuild(Build $build)
    {
        $this->lastBuild = $build;

        return $this;
    }
    
    /**
     * Set name
     *
     * @param string $name
     * @return Branch
     */
    public function setName($name)
    {
        $this->name = $name;
    
        return $this;
    }

    /**
     * Get name
     *
     * @return string 
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     * @return Branch
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;
    
        return $this;
    }

    /**
     * Get createdAt
     *
     * @return \DateTime 
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set updatedAt
     *
     * @param \DateTime $updatedAt
     * @return Branch
     */
    public function setUpdatedAt($updatedAt)
    {
        $this->updatedAt = $updatedAt;
    
        return $this;
    }

    /**
     * Get updatedAt
     *
     * @return \DateTime 
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
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
     * @return Branch
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
     * Add builds
     *
     * @param \App\CoreBundle\Entity\Build $builds
     * @return Branch
     */
    public function addBuild(\App\CoreBundle\Entity\Build $builds)
    {
        $this->builds[] = $builds;
    
        return $this;
    }

    /**
     * Remove builds
     *
     * @param \App\CoreBundle\Entity\Build $builds
     */
    public function removeBuild(\App\CoreBundle\Entity\Build $builds)
    {
        $this->builds->removeElement($builds);
    }

    /**
     * Get builds
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getBuilds()
    {
        return $this->builds;
    }
}