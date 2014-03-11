<?php

namespace App\Model;

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
     * @var \App\Model\Build
     */
    private $build;

    /**
     * @var string
     */
    private $deliveryId;

    /**
     * @var string
     */
    private $event;

    /**
     * @return string
     */
    public function pretty()
    {
        return json_encode(json_decode($this->payload), JSON_PRETTY_PRINT);
    }

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
     * @param \App\Model\Build $build
     * @return GithubPayload
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
     * Set deliveryId
     *
     * @param string $deliveryId
     * @return GithubPayload
     */
    public function setDeliveryId($deliveryId)
    {
        $this->deliveryId = $deliveryId;
    
        return $this;
    }

    /**
     * Get deliveryId
     *
     * @return string 
     */
    public function getDeliveryId()
    {
        return $this->deliveryId;
    }

    /**
     * Set event
     *
     * @param string $event
     * @return GithubPayload
     */
    public function setEvent($event)
    {
        $this->event = $event;
    
        return $this;
    }

    /**
     * Get event
     *
     * @return string 
     */
    public function getEvent()
    {
        return $this->event;
    }
}