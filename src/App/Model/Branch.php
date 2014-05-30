<?php

namespace App\Model;

class Branch
{
    protected $id;

    protected $name;

    protected $project;

    protected $createdAt;

    protected $updatedAt;

    protected $deleted = false;

    protected $builds;

    protected $isDemo = false;

    /** not persisted **/

    protected $lastBuild;

    /** @Buildable */
    public function getRef()
    {
        return $this->getName();
    }

    /**
     * @return string
     */
    public function getDomain()
    {
        $name = preg_replace('/[^a-z0-9\-]/', '-', strtolower($this->getName()));

        return sprintf('%s.%s', $name, $this->getProject()->getDomain());
    }
    
    /**
     * Constructor
     */
    public function __construct()
    {
        $this->builds = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function __toString()
    {
        return $this->getName();
    }

    public function hasBuildingBuild()
    {
        return null !== $this->getBuildingBuild();
    }

    public function getBuildingBuild()
    {
        foreach ($this->getBuilds() as $build) {
            if ($build->isBuilding()) {
                return $build;
            }
        }

        return null;
    }

    public function hasRunningBuild()
    {
        return null !== $this->getRunningBuild();
    }

    public function getRunningBuild()
    {
        foreach ($this->getBuilds() as $build) {
            if ($build->isRunning()) {
                return $build;
            }
        }

        return null;
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
     * @param \App\Model\Project $project
     * @return Branch
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
     * Add builds
     *
     * @param \App\Model\Build $builds
     * @return Branch
     */
    public function addBuild(\App\Model\Build $builds)
    {
        $this->builds[] = $builds;
    
        return $this;
    }

    /**
     * Remove builds
     *
     * @param \App\Model\Build $builds
     */
    public function removeBuild(\App\Model\Build $builds)
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

    /**
     * Set deleted
     *
     * @param boolean $deleted
     * @return Branch
     */
    public function setDeleted($deleted)
    {
        $this->deleted = $deleted;
    
        return $this;
    }

    /**
     * Get deleted
     *
     * @return boolean 
     */
    public function getDeleted()
    {
        return $this->deleted;
    }

    /**
     * Set isDemo
     *
     * @param boolean $isDemo
     * @return Branch
     */
    public function setIsDemo($isDemo)
    {
        $this->isDemo = $isDemo;
    
        return $this;
    }

    /**
     * Get isDemo
     *
     * @return boolean 
     */
    public function getIsDemo()
    {
        return $this->isDemo;
    }
}