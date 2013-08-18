<?php

namespace App\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller as BaseController;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Cookie;

use App\CoreBundle\Entity\Project;
use App\CoreBundle\Entity\Build;

use App\CoreBundle\Value\ProjectAccess;

class Controller extends BaseController
{
    const REGEXP_IP = '\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}';

    protected function addFlash($type, $message)
    {
        $request->getSession()->getFlashBag()->add($type, $message);
    }

    /**
     * @todo fix this
     */
    protected function getClientIp()
    {
        if (preg_match('/'.self::REGEXP_IP.'$/', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches)) {
            return $matches[0];
        }

        if (array_key_exists('REMOTE_ADDR', $_SERVER)) {
            return $_SERVER['REMOTE_ADDR'];
        }

        return null;
    }

    protected function getProjectAccessList(Project $project)
    {
        $accessList = $this
            ->get('app_core.redis')
            ->smembers('auth:' . $project->getSlug());

        $accessList = array_filter($accessList, function($token) {
            return $token !== '0.0.0.0';
        });

        $ips = array_filter($accessList, function($string) { return preg_match('/^'.self::REGEXP_IP.'$/S', $string); });
        $tokens = array_diff($accessList, $ips);

        return ['ips' => $ips, 'tokens' => $tokens];
    }

    protected function grantProjectAccess(Project $project, ProjectAccess $access)
    {
        return $this
            ->get('app_core.redis')
            ->sadd('auth:' . $project->getSlug(), $access->getIp(), $access->getToken());
    }

    protected function revokeProjectAccess(Project $project, ProjectAccess $access)
    {
        return $this
            ->get('app_core.redis')
            ->srem('auth:' . $project->getSlug(), $access->getIp(), $access->getToken());
    }

    protected function isProjectAccessGranted(Project $project, ProjectAccess $access)
    {
        $authKey = 'auth:' . $project->getSlug();

        $results = $this
            ->get('app_core.redis')
            ->multi()
            ->sismember($authKey, $access->getIp())
            ->sismember($authKey, $access->getToken())
            ->exec();

        return (false !== array_search(true, $results));
    }

    protected function github_post($url, $payload)
    {
        return json_decode(file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'content' => json_encode($payload),
                'header' => 'Authorization: token '.$this->getUser()->getAccessToken()."\r\n".
                            "Content-Type: application/json\r\n"
            ],
        ])));
    }

    protected function github_get($url)
    {
        return json_decode(file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => 'Authorization: token '.$this->getUser()->getAccessToken()."\r\n"
            ],
        ])));
    }

    protected function findBuild($id)
    {
        $build = $this->getDoctrine()->getRepository('AppCoreBundle:Build')->find($id);

        if (!$build) {
            throw $this->createNotFoundException();
        }

        if ($build->getProject()->getOwner() != $this->getUser()) {
            throw new AccessDeniedException();
        }

        return $build;
    }

    /**
     * @todo use BuildRepository#findPendingByProject
     */
    protected function findPendingBuilds($project)
    {
        $qb = $this->getDoctrine()->getRepository('AppCoreBundle:Build')->createQueryBuilder('b');

        $qb
            ->where($qb->expr()->eq('b.project', ':project'))
            ->andWhere($qb->expr()->in('b.status', [Build::STATUS_SCHEDULED, Build::STATUS_BUILDING]))
            ->setParameter(':project', $project->getId());

        return $qb->getQuery()->execute();
    }

    protected function publishWebsocket($event, $data)
    {
        $this->get('old_sound_rabbit_mq.websocket_producer')->publish(json_encode([
            'event' => $event,
            'timestamp' => microtime(true),
            'data' => $data,
        ]));
    }

    protected function removeAndFlush($entity)
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($entity);
        $em->flush();
    }

    protected function persistAndFlush($entity)
    {
        $em = $this->getDoctrine()->getManager();
        $em->persist($entity);
        $em->flush();
    }

    protected function setCurrentProjectId($id)
    {
        $this->get('request')->attributes->set('current_project_id', $id);
    }

    protected function findProject($id)
    {
        $project = $this->getDoctrine()->getRepository('AppCoreBundle:Project')->find($id);

        if (!$project) {
            throw $this->createNotFoundException();
        }

        if ($project->getOwner() != $this->getUser()) {
            throw new AccessDeniedException();
        }

        return $project;
    }

    protected function findProjectBySlug($slug)
    {
        $project = $this->getDoctrine()->getRepository('AppCoreBundle:Project')->findOneBySlug($slug);

        if (!$project) {
            throw $this->createNotFoundException();
        }
        
        return $project;
    }
}