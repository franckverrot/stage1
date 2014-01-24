<?php

namespace App\AdminBundle\Controller;

class BuildController extends Controller
{
    public function outputAction($id)
    {
        $build = $this->findBuild($id);

        return $this->render('AppAdminBundle:Build:output.html.twig', ['build' => $build]);
    }

    public function payloadAction($id)
    {
        $build = $this->findBuild($id);

        return $this->render('AppAdminBundle:Build:payload.html.twig', ['build' => $build]);
    }
}