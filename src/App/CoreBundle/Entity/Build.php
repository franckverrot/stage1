<?php

namespace App\CoreBundle\Entity;

use BadMethodCallException;

class Build
{
    const STATUS_SCHEDULED = 1;

    const STATUS_BUILDING = 2;

    const STATUS_RUNNING = 3;

    const STATUS_CANCELED = 4;

    const STATUS_FAILED = 5;

    const STATUS_KILLED = 6;

    const STATUS_DELETED = 7;

    const STATUS_OBSOLETE = 8;

    const LOG_OUTPUT = 'output';

    const LOG_APPLICATION = 'application';
    
    private $id;

    private $project;

    private $initiator;

    private $status;

    private $ref;

    private $hash;

    private $port;

    private $host;

    private $containerId;

    private $imageId;

    private $message;

    private $exitCode;

    private $exitCodeText;

    private $createdAt;

    private $updatedAt;

    private $branch;

    private $logs;

    private $channel;

    private $streamOutput = true;

    private $streamSteps = false;

    private $isDemo = false;

    private $demo;

    public function __construct()
    {
        $this->logs = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function __call($method, $args)
    {
        if (defined($const = 'self::STATUS_'.(strtoupper(strpos($method, 'is') === 0 ? substr($method, 2) : $method)))) {
            return $this->getStatus() === constant($const);
        }

        throw new BadMethodCallException(sprintf('Method "%s" does not exist in object "%s"', $method, __CLASS__));
    }

    public function __toString()
    {
        return json_encode($this->asWebsocketMessage());
    }

    /**
     * @return array
     */
    public function getOutputLogs()
    {
        return $this->getLogs(self::LOG_OUTPUT);
    }

    /**
     * @return array
     */
    public function getApplicationLogs()
    {
        return $this->getLogs(self::LOG_APPLICATION);
    }

    public function getUsers()
    {
        return $this->getProject()->getUsers();
    }

    public function getChannel()
    {
        if (null !== $this->channel) {
            return $this->channel;
        }

        return $this->getProject()->getChannel();
    }

    public function setChannel($channel)
    {
        $this->channel = $channel;
    }

    /**
     * @param string $message
     * @param string $type      log|output
     */
    public function appendLog($message, $type, $stream = null)
    {
        $log = new BuildLog();
        $log->setBuild($this);
        $log->setType($type);
        $log->setMessage(trim($message));
        $log->setStream($stream);
        
        return $log;
    }

    public function hasContainer()
    {
        return $this->containerId !== null;
    }

    # @todo @slug move to its own service
    private function normalize($string)
    {
        return preg_replace('/[^a-z0-9\-]/', '-', strtolower($string));
    }

    public function getBranchDomain()
    {
        return sprintf('%s.%s', $this->getNormRef(), $this->getProject()->getSlug());
    }

    public function getNormRef()
    {
        return $this->normalize($this->getRef());
    }

    public function asWebsocketMessage()
    {
        return [
            'id' => $this->getId(),
            'ref' => $this->getRef(),
            'normRef' => $this->getNormRef(),
            'hash' => $this->getHash(),
            'status' => $this->getStatus(),
            'status_label' => $this->getStatusLabel(),
            'status_label_class' => $this->getStatusLabelClass(),
            'url' => $this->getUrl(),
            'port' => $this->getPort(),
        ];

    }

    public function getImageName()
    {
        return sprintf('b/%d/%s/%d', $this->getProject()->getId(), $this->getNormRef(), $this->getId());
    }

    public function getImageTag()
    {
        return $this->getId();
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

        if ($status !== self::STATUS_RUNNING) {
            $this->setPort(null);
        }
    
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

    /**
     * Set port
     *
     * @param string $port
     * @return Build
     */
    public function setPort($port)
    {
        $this->port = $port;
    
        return $this;
    }

    /**
     * Get port
     *
     * @return string 
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * Get url
     *
     * @return string 
     */
    public function getUrl()
    {
        return sprintf('http://%s/', $this->getHost());
    }

    /**
     * Set branch
     *
     * @param \App\CoreBundle\Entity\Branch $branch
     * @return Build
     */
    public function setBranch(\App\CoreBundle\Entity\Branch $branch = null)
    {
        $this->branch = $branch;
    
        return $this;
    }

    /**
     * Get branch
     *
     * @return \App\CoreBundle\Entity\Branch 
     */
    public function getBranch()
    {
        return $this->branch;
    }
    
    /**
     * Add logs
     *
     * @param \App\CoreBundle\Entity\BuildLog $logs
     * @return Build
     */
    public function addLog(\App\CoreBundle\Entity\BuildLog $logs)
    {
        $this->logs[] = $logs;
    
        return $this;
    }

    /**
     * Remove logs
     *
     * @param \App\CoreBundle\Entity\BuildLog $logs
     */
    public function removeLog(\App\CoreBundle\Entity\BuildLog $logs)
    {
        $this->logs->removeElement($logs);
    }

    /**
     * Get logs
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getLogs($type = null)
    {
        if (null === $type) {
            return $this->logs;
        }

        return array_filter($this->logs->toArray(), function($log) use ($type) {
            return $log->getType() === $type;
        });
    }

    /**
     * Set streamOutput
     *
     * @param boolean $streamOutput
     * @return Build
     */
    public function setStreamOutput($streamOutput)
    {
        $this->streamOutput = $streamOutput;
    
        return $this;
    }

    /**
     * Get streamOutput
     *
     * @return boolean 
     */
    public function getStreamOutput()
    {
        return $this->streamOutput;
    }

    /**
     * Set streamSteps
     *
     * @param boolean $streamSteps
     * @return Build
     */
    public function setStreamSteps($streamSteps)
    {
        $this->streamSteps = $streamSteps;
    
        return $this;
    }

    /**
     * Get streamSteps
     *
     * @return boolean 
     */
    public function getStreamSteps()
    {
        return $this->streamSteps;
    }

    /**
     * Set host
     *
     * @param string $host
     * @return Build
     */
    public function setHost($host)
    {
        $this->host = $host;
    
        return $this;
    }

    /**
     * Get host
     *
     * @return string 
     */
    public function getHost()
    {
        return $this->host;
    }

    public function isDemo()
    {
        return $this->getIsDemo();
    }

    /**
     * Set isDemo
     *
     * @param boolean $isDemo
     * @return Build
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

    /**
     * Set demo
     *
     * @param \App\CoreBundle\Entity\Demo $demo
     * @return Build
     */
    public function setDemo(\App\CoreBundle\Entity\Demo $demo = null)
    {
        $this->demo = $demo;
    
        return $this;
    }

    /**
     * Get demo
     *
     * @return \App\CoreBundle\Entity\Demo 
     */
    public function getDemo()
    {
        return $this->demo;
    }
}