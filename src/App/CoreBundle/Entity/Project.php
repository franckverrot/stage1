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

    protected $slug;

    protected $sshUrl;

    protected $keysUrl;

    protected $hooksUrl;

    protected $githubId;

    protected $githubFullName;

    protected $githubOwnerLogin;

    protected $githubHookId;

    protected $githubDeployKeyId;

    protected $builds;

    protected $createdAt;

    protected $updatedAt;

    protected $lastBuildAt;

    protected $lastBuildRef;

    protected $publicKey;

    protected $privateKey;

    protected $masterPassword;

    protected $users;

    protected $branches;

    public function __toString()
    {
        return json_encode($this->asWebsocketMessage());
    }

    public function getChannel()
    {
        return 'project.'.$this->getId();
    }

    public function getFullName()
    {
        return $this->getGithubFullName();
    }

    public function asMessage()
    {
        return [
            'id' => $this->getId(),
            'name' => $this->getName(),
            'nb_pending_builds' => count($this->getPendingBuilds()),
        ];
    }

    public function getPendingBuilds()
    {
        return $this->getBuilds()->filter(function($build) {
            return $build->isPending();
        });
    }

    public function getRunningBuilds()
    {
        return $this->getBuilds()->filter(function($build) {
            return $build->isRunning();
        });
    }

    public function getBuildingBuilds()
    {
        return $this->getBuilds()->filter(function($build) {
            return $build->isBuilding();
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
        $this->users = new \Doctrine\Common\Collections\ArrayCollection();
        $this->branches = new \Doctrine\Common\Collections\ArrayCollection();
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

    /**
     * Set githubFullName
     *
     * @param string $githubFullName
     * @return Project
     */
    public function setGithubFullName($githubFullName)
    {
        $this->githubFullName = $githubFullName;
    
        return $this;
    }

    /**
     * Get githubFullName
     *
     * @return string 
     */
    public function getGithubFullName()
    {
        return $this->githubFullName;
    }

    /**
     * Set githubOwnerLogin
     *
     * @param string $githubOwnerLogin
     * @return Project
     */
    public function setGithubOwnerLogin($githubOwnerLogin)
    {
        $this->githubOwnerLogin = $githubOwnerLogin;
    
        return $this;
    }

    /**
     * Get githubOwnerLogin
     *
     * @return string 
     */
    public function getGithubOwnerLogin()
    {
        return $this->githubOwnerLogin;
    }

    /**
     * Set githubHookId
     *
     * @param integer $githubHookId
     * @return Project
     */
    public function setGithubHookId($githubHookId)
    {
        $this->githubHookId = $githubHookId;
    
        return $this;
    }

    /**
     * Get githubHookId
     *
     * @return integer 
     */
    public function getGithubHookId()
    {
        return $this->githubHookId;
    }

    /**
     * Set githubDeployKeyId
     *
     * @param integer $githubDeployKeyId
     * @return Project
     */
    public function setGithubDeployKeyId($githubDeployKeyId)
    {
        $this->githubDeployKeyId = $githubDeployKeyId;
    
        return $this;
    }

    /**
     * Get githubDeployKeyId
     *
     * @return integer 
     */
    public function getGithubDeployKeyId()
    {
        return $this->githubDeployKeyId;
    }

    /**
     * Set githubId
     *
     * @param integer $githubId
     * @return Project
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
     * Set publicKey
     *
     * @param string $publicKey
     * @return Project
     */
    public function setPublicKey($publicKey)
    {
        $this->publicKey = $publicKey;
    
        return $this;
    }

    /**
     * Get publicKey
     *
     * @return string 
     */
    public function getPublicKey()
    {
        return $this->publicKey;
    }

    /**
     * Set privateKey
     *
     * @param string $privateKey
     * @return Project
     */
    public function setPrivateKey($privateKey)
    {
        $this->privateKey = $privateKey;
    
        return $this;
    }

    /**
     * Get privateKey
     *
     * @return string 
     */
    public function getPrivateKey()
    {
        return $this->privateKey;
    }

    /**
     * Set sshUrl
     *
     * @param string $sshUrl
     * @return Project
     */
    public function setSshUrl($sshUrl)
    {
        $this->sshUrl = $sshUrl;
    
        return $this;
    }

    /**
     * Get sshUrl
     *
     * @return string 
     */
    public function getSshUrl()
    {
        return $this->sshUrl;
    }

    /**
     * Set slug
     *
     * @param string $slug
     * @return Project
     */
    public function setSlug($slug)
    {
        $this->slug = $slug;
    
        return $this;
    }

    /**
     * Get slug
     *
     * @return string 
     */
    public function getSlug()
    {
        return $this->slug;
    }

    /**
     * Set masterPassword
     *
     * @param string $masterPassword
     * @return Project
     */
    public function setMasterPassword($masterPassword)
    {
        $this->masterPassword = $masterPassword;
    
        return $this;
    }

    /**
     * Get masterPassword
     *
     * @return string 
     */
    public function getMasterPassword()
    {
        return $this->masterPassword;
    }

    /**
     * @return boolean
     */
    public function hasMasterPassword()
    {
        return strlen($this->getMasterPassword()) > 0;
    }

    /**
     * Add users
     *
     * @param \App\CoreBundle\Entity\User $users
     * @return Project
     */
    public function addUser(\App\CoreBundle\Entity\User $users)
    {
        $this->users[] = $users;
    
        return $this;
    }

    /**
     * Remove users
     *
     * @param \App\CoreBundle\Entity\User $users
     */
    public function removeUser(\App\CoreBundle\Entity\User $users)
    {
        $this->users->removeElement($users);
    }

    /**
     * Get users
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getUsers()
    {
        return $this->users;
    }

    /**
     * Add branches
     *
     * @param \App\CoreBundle\Entity\Branch $branch
     * @return Project
     */
    public function addBranch(\App\CoreBundle\Entity\Branch $branch)
    {
        return $this->addBranche($branch);
    }

    /**
     * Remove branches
     *
     * @param \App\CoreBundle\Entity\Branch $branch
     */
    public function removeBranch(\App\CoreBundle\Entity\Branch $branch)
    {
        return $this->removeBranche($branch);
    }

    /**
     * Get branches
     *
     * @return \Doctrine\Common\Collections\Collection 
     */
    public function getBranches()
    {
        return $this->branches;
    }

    /**
     * Add branches
     *
     * @param \App\CoreBundle\Entity\Branch $branches
     * @return Project
     */
    public function addBranche(\App\CoreBundle\Entity\Branch $branches)
    {
        $this->branches[] = $branches;
    
        return $this;
    }

    /**
     * Remove branches
     *
     * @param \App\CoreBundle\Entity\Branch $branches
     */
    public function removeBranche(\App\CoreBundle\Entity\Branch $branches)
    {
        $this->branches->removeElement($branches);
    }

    /**
     * Set keysUrl
     *
     * @param string $keysUrl
     * @return Project
     */
    public function setKeysUrl($keysUrl)
    {
        $this->keysUrl = $keysUrl;
    
        return $this;
    }

    /**
     * Get keysUrl
     *
     * @return string 
     */
    public function getKeysUrl()
    {
        return $this->keysUrl;
    }

    /**
     * Set hooksUrl
     *
     * @param string $hooksUrl
     * @return Project
     */
    public function setHooksUrl($hooksUrl)
    {
        $this->hooksUrl = $hooksUrl;
    
        return $this;
    }

    /**
     * Get hooksUrl
     *
     * @return string 
     */
    public function getHooksUrl()
    {
        return $this->hooksUrl;
    }
}