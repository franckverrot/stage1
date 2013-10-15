<?php

namespace App\AdminBundle\Controller;

class HipacheController extends Controller
{
    public function indexAction()
    {
        $redis = $this->container->get('app_core.redis');
        $frontends_raw = $redis->keys('frontend:*');

        $redis->multi();

        foreach ($frontends_raw as $frontend) {
            $redis->lrange($frontend, 0, -1);
        }

        $results = $redis->exec();

        $frontends = [];

        foreach ($results as $i => $result) {
            $frontends[$frontends_raw[$i]] = $result[0];
        }

        return $this->render('AppAdminBundle:Hipache:index.html.twig', [
            'frontends' => $frontends,
        ]);
    }
}