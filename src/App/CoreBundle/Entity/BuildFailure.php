<?php

namespace App\CoreBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

use Exception;

/**
 * BuildFailure
 */
class BuildFailure
{
    /**
     * @var string
     */
    private $message;

    /**
     * @var integer
     */
    private $code;

    /**
     * @var string
     */
    private $trace;

    /**
     * @var integer
     */
    private $id;

    /**
     * @var \App\CoreBundle\Entity\Build
     */
    private $build;

    public static function fromException(Exception $e)
    {
        $obj = new self();
        $obj->setException(get_class($e));
        $obj->setMessage($e->getMessage());
        $obj->setCode($e->getCode());
        $obj->setTrace($e->getTraceAsString());

        return $obj;
    }


    /**
     * Set message
     *
     * @param string $message
     * @return BuildFailure
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
     * Set code
     *
     * @param integer $code
     * @return BuildFailure
     */
    public function setCode($code)
    {
        $this->code = $code;
    
        return $this;
    }

    /**
     * Get code
     *
     * @return integer 
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Set trace
     *
     * @param string $trace
     * @return BuildFailure
     */
    public function setTrace($trace)
    {
        $this->trace = $trace;
    
        return $this;
    }

    /**
     * Get trace
     *
     * @return string 
     */
    public function getTrace()
    {
        return $this->trace;
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
     * @return BuildFailure
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
     * @var string
     */
    private $exception;


    /**
     * Set exception
     *
     * @param string $exception
     * @return BuildFailure
     */
    public function setException($exception)
    {
        $this->exception = $exception;
    
        return $this;
    }

    /**
     * Get exception
     *
     * @return string 
     */
    public function getException()
    {
        return $this->exception;
    }
    /**
     * @var \DateTime
     */
    private $createdAt;

    /**
     * @var \DateTime
     */
    private $updatedAt;


    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     * @return BuildFailure
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
     * @return BuildFailure
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
}