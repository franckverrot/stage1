<?php

namespace App\CoreBundle\Tests\Message;

use App\CoreBundle\Message\BuildStartedMessage;

use PHPUnit_Framework_TestCase;

class BuildStartedMessageTest extends PHPUnit_Framework_TestCase
{
    public function testGetEvent()
    {
        $build = $this->getMock('App\\CoreBundle\\Entity\\Build');
        $message = new BuildStartedMessage($build);

        $this->assertEquals('build.started', $message->getEvent());
    }
}