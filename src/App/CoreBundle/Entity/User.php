<?php

namespace App\CoreBundle\Entity;

use stdClass;

class User
{
    public $id;

    public $githubId;

    public $username;

    public $email;

    public $accessToken;

    public $createdAt;

    public $updatedAt;

    public $lastLoginAt;

    public function __toString()
    {
        return $this->getUsername();
    }

    static public function fromGithubResponse(stdClass $response)
    {
        $user = new static();
        $user->setGithubId($response->id);
        $user->setUsername($response->login);
        $user->setEmail($response->email);

        return $user;
    }

    /**
     * Set githubId
     *
     * @param integer $githubId
     * @return User
     */
    public function setGithubId($githubId)
    {
        $this->githubId = $githubId;
    
        return $this;
    }

    /**
     * Get githubId
     *
     * @return integer 
     */
    public function getGithubId()
    {
        return $this->githubId;
    }

    /**
     * Set username
     *
     * @param string $username
     * @return User
     */
    public function setUsername($username)
    {
        $this->username = $username;
    
        return $this;
    }

    /**
     * Get username
     *
     * @return string 
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * Set email
     *
     * @param string $email
     * @return User
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
     * Set accessToken
     *
     * @param string $accessToken
     * @return User
     */
    public function setAccessToken($accessToken)
    {
        $this->accessToken = $accessToken;
    
        return $this;
    }

    /**
     * Get accessToken
     *
     * @return string 
     */
    public function getAccessToken()
    {
        return $this->accessToken;
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
     * Set createdAt
     *
     * @param \DateTime $createdAt
     * @return User
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
     * @return User
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
     * Set lastLoginAt
     *
     * @param \DateTime $lastLoginAt
     * @return User
     */
    public function setLastLoginAt($lastLoginAt)
    {
        $this->lastLoginAt = $lastLoginAt;
    
        return $this;
    }

    /**
     * Get lastLoginAt
     *
     * @return \DateTime 
     */
    public function getLastLoginAt()
    {
        return $this->lastLoginAt;
    }
}