<?php

namespace App\CoreBundle\Entity;

use BadMethodCallException;

class Build
{
    const STATUS_SCHEDULED = 1;

    const STATUS_BUILDING = 2;

    const STATUS_RUNNING = 3;

    const STATUS_FAILED = 4;

    const STATUS_CANCELED = 5;

    const STATUS_KILLED = 6;

    const STATUS_DELETED = 7;

    const STATUS_OBSOLETE = 8;
    
    private $id;

    private $project;

    private $initiator;

    private $status;

    private $ref;

    private $hash;

    private $url;

    private $containerId;

    private $imageId;

    private $message;

    private $output;

    private $exitCode;

    private $exitCodeText;

    private $createdAt;

    private $updatedAt;

    public function asWebsocketMessage()
    {
        return [
            'id' => $this->getId(),
            'ref' => $this->getRef(),
            'status' => $this->getStatus(),
            'status_label' => $this->getStatusLabel(),
            'status_label_class' => $this->getStatusLabelClass(),
            'url' => $this->getUrl(),
        ];

    }

    public function getImageName()
    {
        return sprintf('build/%s/%s', $this->getProject()->getName(), $this->getRef());
    }

    public function getImageTag()
    {
        return $this->getId();
    }

    public function __call($method, $args)
    {
        if (defined($const = 'self::STATUS_'.(strtoupper(strpos($method, 'is') === 0 ? substr($method, 2) : $method)))) {
            return $this->getStatus() === constant($const);
        }

        throw new BadMethodCallException(sprintf('Method "%s" does not exist in object "%s"', $method, __CLASS__));
    }

    public function isPending()
    {
        return in_array($this->getStatus(), [
            self::STATUS_SCHEDULED,
            self::STATUS_BUILDING
        ]);
    }

    public function getStatusLabelClass()
    {
        switch ($this->getStatus()) {
            case self::STATUS_BUILDING:
                return 'info';
            case self::STATUS_RUNNING:
                return 'success';
            case self::STATUS_FAILED:
                return 'important';
            case self::STATUS_KILLED:
                return 'warning';
            default:
                return 'default';
        }
    }

    public function getStatusLabel()
    {
        switch ($this->getStatus()) {
            case self::STATUS_SCHEDULED:
                return 'scheduled';
            case self::STATUS_BUILDING:
                return 'building';
            case self::STATUS_RUNNING:
                return 'running';
            case self::STATUS_FAILED:
                return 'failed';
            case self::STATUS_CANCELED:
                return 'canceled';
            case self::STATUS_KILLED:
                return 'killed';
            case self::STATUS_DELETED:
                return 'deleted';
            case self::STATUS_OBSOLETE:
                return 'obsolete';
            default:
                return 'unknown';
        }
    }

    /**
     * Set status
     *
     * @param integer $status
     * @return Build
     */
    public function setStatus($status)
    {
        $this->status = $status;
    
        return $this;
    }

    /**
     * Get status
     *
     * @return integer 
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Set ref
     *
     * @param string $ref
     * @return Build
     */
    public function setRef($ref)
    {
        $this->ref = $ref;
    
        return $this;
    }

    /**
     * Get ref
     *
     * @return string 
     */
    public function getRef()
    {
        return $this->ref;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     * @return Build
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
     * @return Build
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
     * @return Build
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
     * Set initiator
     *
     * @param \App\CoreBundle\Entity\User $initiator
     * @return Build
     */
    public function setInitiator(\App\CoreBundle\Entity\User $initiator = null)
    {
        $this->initiator = $initiator;
    
        return $this;
    }

    /**
     * Get initiator
     *
     * @return \App\CoreBundle\Entity\User 
     */
    public function getInitiator()
    {
        return $this->initiator;
    }

    /**
     * Set hash
     *
     * @param string $hash
     * @return Build
     */
    public function setHash($hash)
    {
        $this->hash = $hash;
    
        return $this;
    }

    /**
     * Get hash
     *
     * @return string 
     */
    public function getHash()
    {
        return $this->hash;
    }

    /**
     * Set url
     *
     * @param string $url
     * @return Build
     */
    public function setUrl($url)
    {
        $this->url = $url;
    
        return $this;
    }

    /**
     * Get url
     *
     * @return string 
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set containerId
     *
     * @param string $containerId
     * @return Build
     */
    public function setContainerId($containerId)
    {
        $this->containerId = $containerId;
    
        return $this;
    }

    /**
     * Get containerId
     *
     * @return string 
     */
    public function getContainerId()
    {
        return $this->containerId;
    }

    /**
     * Set imageId
     *
     * @param string $imageId
     * @return Build
     */
    public function setImageId($imageId)
    {
        $this->imageId = $imageId;
    
        return $this;
    }

    /**
     * Get imageId
     *
     * @return string 
     */
    public function getImageId()
    {
        return $this->imageId;
    }

    /**
     * Set message
     *
     * @param string $message
     * @return Build
     */
    public function setMessage($message)
    {
        $this->message = $message;
    
        return $this;
    }

    /**
     * Get message
     *
     * @return string 
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * Set output
     *
     * @param string $output
     * @return Build
     */
    public function setOutput($output)
    {
        $this->output = $output;
    
        return $this;
    }

    /**
     * Get output
     *
     * @return string 
     */
    public function getOutput()
    {
        return $this->output;
    }

    /**
     * Set exitCode
     *
     * @param integer $exitCode
     * @return Build
     */
    public function setExitCode($exitCode)
    {
        $this->exitCode = $exitCode;
    
        return $this;
    }

    /**
     * Get exitCode
     *
     * @return integer 
     */
    public function getExitCode()
    {
        return $this->exitCode;
    }

    /**
     * Set exitCodeText
     *
     * @param string $exitCodeText
     * @return Build
     */
    public function setExitCodeText($exitCodeText)
    {
        $this->exitCodeText = $exitCodeText;
    
        return $this;
    }

    /**
     * Get exitCodeText
     *
     * @return string 
     */
    public function getExitCodeText()
    {
        return $this->exitCodeText;
    }
}