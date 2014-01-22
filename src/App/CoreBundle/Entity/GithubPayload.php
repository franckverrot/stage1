<?php

namespace App\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * GithubPayload
 */
class GithubPayload
{
    /**
     * @var string
     */
    private $payload;

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
     * @var \App\CoreBundle\Entity\Build
     */
    private $build;


    /**
     * Set payload
     *
     * @param string $payload
     * @return GithubPayload
     */
    public function setPayload($payload)
    {
        $this->payload = $payload;
    
        return $this;
    }

    /**
     * Get payload
     *
     * @return string 
     */
    public function getPayload()
    {
        return $this->payload;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     * @return GithubPayload
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
     * @return GithubPayload
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
     * Set build
     *
     * @param \App\CoreBundle\Entity\Build $build
     * @return GithubPayload
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
}