<?php

namespace App\CoreBundle\Message;

use App\CoreBundle\Entity\Build;

use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

use Exception;

abstract class AbstractMessage implements MessageInterface
{
    private $router;

    private $object;

    private $extra = array();

    public function __construct($object)
    {
        $this->object = $object;
    }

    public function setExtra(array $extra)
    {
        $this->extra = $extra;
    }

    public function setRouter(RouterInterface $router)
    {
        $this->router = $router;
    }

    public function generateUrl($route, $parameters = array(), $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH)
    {
        return $this->router->generate($route, $parameters, $referenceType);
    }

    public function getObject()
    {
        return $this->object;
    }

    public function toArray()
    {
        return [
            'event' => $this->getEvent(),
            'channel' => $this->getChannel(),
            'timestamp' => microtime(true),
            'data' => array_merge($this->extra, $this->getData()),
        ];
    }

    public function __toString()
    {
        try {
            return json_encode($this->toArray());
        } catch (Exception $e) {
            echo $e->getMessage();
            return get_class($e).': '.$e->getMessage();
        }
    }

    public function getEvent()
    {
        $className = get_class($this);
        $className = substr($className, strrpos($className, '\\') + 1, -7);
        $className = strtolower(preg_replace('~(?<=\\w)([A-Z])~', '.$1', $className));

        return $className;
    }

    public function getData()
    {
        $className = get_class($this->object);
        $className = substr($className, strrpos($className, '\\') + 1);
        $className = strtolower(preg_replace('~(?<=\\w)([A-Z])~', '_$1', $className));

        return [$className => $this->object->asMessage()];
    }

    public function getRoutes()
    {
        return [];
    }

    public function getChannel()
    {
        return $this->object->getChannel();
    }
}