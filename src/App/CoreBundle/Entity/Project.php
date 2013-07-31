<?php

namespace App\CoreBundle\Entity;



/**
 * Project
 */
class Project
{
    /**
     * @var integer
     */
    protected $id;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $cloneUrl;

    protected $owner;

    protected $builds;

    protected $createdAt;

    protected $updatedAt;

    protected $lastBuildAt;

    protected $lastBuildRef;

    public function getPendingBuilds()
    {
        return $this->getBuilds()->filter(function($build) {
            return $build->isPending();
        });
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
     * Set name
     *
     * @param string $name
     * @return Project
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
     * Set cloneUrl
     *
     * @param string $cloneUrl
     * @return Project
     */
    public function setCloneUrl($cloneUrl)
    {
        $this->cloneUrl = $cloneUrl;
    
        return $this;
    }

    /**
     * Get cloneUrl
     *
     * @return string 
     */
    public function getCloneUrl()
    {
        return $this->cloneUrl;
    }

    /**
     * Set owner
     *
     * @param \App\CoreBundle\Entity\User $owner
     * @return Project
     */
    public function setOwner(\App\CoreBundle\Entity\User $owner = null)
    {
        $this->owner = $owner;
    
        return $this;
    }

    /**
     * Get owner
     *
     * @return \App\CoreBundle\Entity\User 
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     * @return Project
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
     * @return Project
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
     * Set lastBuildAt
     *
     * @param \DateTime $lastBuildAt
     * @return Project
     */
    public function setLastBuildAt($lastBuildAt)
    {
        $this->lastBuildAt = $lastBuildAt;
    
        return $this;
    }

    /**
     * Get lastBuildAt
     *
     * @return \DateTime 
     */
    public function getLastBuildAt()
    {
        return $this->lastBuildAt;
    }

    /**
     * Set lastBuildRef
     *
     * @param string $lastBuildRef
     * @return Project
     */
    public function setLastBuildRef($lastBuildRef)
    {
        $this->lastBuildRef = $lastBuildRef;
    
        return $this;
    }

    /**
     * Get lastBuildRef
     *
     * @return string 
     */
    public function getLastBuildRef()
    {
        return $this->lastBuildRef;
    }
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->builds = new \Doctrine\Common\Collections\ArrayCollection();
    }
    
    /**
     * Add builds
     *
     * @param \App\CoreBundle\Entity\Build $builds
     * @return Project
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