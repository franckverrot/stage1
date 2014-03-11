<?php

namespace App\Model;

/**
 * BetaSignup
 */
class BetaSignup
{
    const STATUS_DEFAULT = 0;

    const STATUS_EMAIL_SENT = 1;

    /**
     * @var string
     */
    private $email;

    /**
     * @var string
     */
    private $betaKey;

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
    private $tries;

    /**
     * @var integer
     */
    private $id;

    /**
     * @var integer
     */
    private $status = 0;

    /**
     * @var \DateTime
     */
    private $emailSentAt;

    public function isEmailSent()
    {
        return $this->getStatus() === self::STATUS_EMAIL_SENT;
    }

    /**
     * Set email
     *
     * @param string $email
     * @return BetaSignup
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
     * Set betaKey
     *
     * @param string $betaKey
     * @return BetaSignup
     */
    public function setBetaKey($betaKey)
    {
        $this->betaKey = $betaKey;
    
        return $this;
    }

    /**
     * Get betaKey
     *
     * @return string 
     */
    public function getBetaKey()
    {
        return $this->betaKey;
    }

    /**
     * Set createdAt
     *
     * @param \DateTime $createdAt
     * @return BetaSignup
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
     * @return BetaSignup
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
     * Set tries
     *
     * @param integer $tries
     * @return BetaSignup
     */
    public function setTries($tries)
    {
        $this->tries = $tries;
    
        return $this;
    }

    /**
     * Get tries
     *
     * @return integer 
     */
    public function getTries()
    {
        return $this->tries;
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
     * Set status
     *
     * @param integer $status
     * @return BetaSignup
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
     * Set emailSentAt
     *
     * @param \DateTime $emailSentAt
     * @return BetaSignup
     */
    public function setEmailSentAt($emailSentAt)
    {
        $this->emailSentAt = $emailSentAt;
    
        return $this;
    }

    /**
     * Get emailSentAt
     *
     * @return \DateTime 
     */
    public function getEmailSentAt()
    {
        return $this->emailSentAt;
    }
}