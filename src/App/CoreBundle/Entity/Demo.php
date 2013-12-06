<?php

namespace App\CoreBundle\Entity;

/**
 * Demo
 */
class Demo
{
    /**
     * @var string
     */
    private $demoKey;

    /**
     * @var string
     */
    private $email;

    /**
     * @var \DateTime
     */
    private $createdAt;

    /**
     * @var \DateTime
     */
    private $updatedAt;

    /**
     * @var integer
     */
    private $id;

    /**
     * @var \App\CoreBundle\Entity\Project
     */
    private $project;

    /**
     * @var \App\CoreBundle\Entity\Build
     */
    private $build;

    /**
     * @var \App\CoreBundle\Entity\User
     */
    private $user;

    /**
     * Set email
     *
     * @param string $email
     * @return Demo
     */
    public function setEmail($email)
    {
        $this->email = $email;
    
        return $this;
    }

    /**
     * Get email
     *
     * @return string 
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     * @return Demo
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
     * @return Demo
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
     * @return Demo
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
     * Set build
     *
     * @param \App\CoreBundle\Entity\Build $build
     * @return Demo
     */
    public function setBuild(\App\CoreBundle\Entity\Build $build = null)
    {
        $this->build = $build;
    
        return $this;
    }

    /**
     * Get build
     *
     * @return \App\CoreBundle\Entity\Build 
     */
    public function getBuild()
    {
        return $this->build;
    }

    /**
     * Set user
     *
     * @param \App\CoreBundle\Entity\Project $user
     * @return Demo
     */
    public function setUser(\App\CoreBundle\Entity\Project $user = null)
    {
        $this->user = $user;
    
        return $this;
    }

    /**
     * Get user
     *
     * @return \App\CoreBundle\Entity\Project 
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * Set demoKey
     *
     * @param string $demoKey
     * @return Demo
     */
    public function setDemoKey($demoKey)
    {
        $this->demoKey = $demoKey;
    
        return $this;
    }

    /**
     * Get demoKey
     *
     * @return string 
     */
    public function getDemoKey()
    {
        return $this->demoKey;
    }
}