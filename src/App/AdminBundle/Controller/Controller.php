<?php

namespace App\AdminBundle\Controller;

use App\CoreBundle\Controller\Controller as BaseController;

class Controller extends BaseController
{
    protected function findBuild($id)
    {
        $build = $this->getDoctrine()->getRepository('AppCoreBundle:Build')->find($id);

        if (!$build) {
            throw $this->createNotFoundException('Could not find build #'.$id);
        }

        return $build;
    }
}