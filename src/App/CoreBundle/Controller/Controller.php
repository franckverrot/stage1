<?php

namespace App\CoreBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller as BaseController;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Cookie;

use App\CoreBundle\Entity\Project;
use App\CoreBundle\Entity\Build;
use App\CoreBundle\Message\MessageInterface;
use App\CoreBundle\Value\ProjectAccess;

class Controller extends BaseController
{
    const REGEXP_IP = '\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}';

    protected function getHashFromRef(Project $project, $ref, $accessToken = null)
    {
        $client = $this->container->get('app_core.client.github');
        $client->setDefaultOption('header/Autorization', 'token '.$accessToken);
        $client->setDefaultOption('header/Accept', 'application/vnd.github.v3');

        $request = $client->get(['/repos/{owner}/{repo}/git/refs/heads', [
            'owner' => $project->getGithubOwnerLogin(),
            'repo' => $project->getName(),
        ]]);

        $response = $request->send();
        $remoteRefs = $response->json();

        $branches = array();

        foreach ($remoteRefs as $remoteRef) {
            if ('refs/heads/'.$ref === $remoteRef['ref']) {
                $hash = $remoteRef['object']['sha'];
                break;
            }
        }

        return $hash;
    }

    protected function addFlash($type, $message)
    {
        $this->getRequest()->getSession()->getFlashBag()->add($type, $message);
    }

    /**
     * @todo fix this
     */
    protected function getClientIp()
    {
        if (preg_match('/'.self::REGEXP_IP.'$/', $this->getRequest()->server->get('HTTP_X_FORWARDED_FOR'), $matches)) {
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
        $args = ['auth:'.$project->getSlug()];

        if ($this->container->getParameter('feature_ip_access_list') || $access->getIp() === '0.0.0.0') {
            $args[] = $access->getIp();
        }

        if ($this->container->getParameter('feature_token_access_list')) {
            $args[] = $access->getToken();
        }

        $args = array_filter($args, function($arg) { return strlen($arg) > 0; });

        return call_user_func_array([$this->get('app_core.redis'), 'sadd'], $args);
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

    protected function findBuild($id, $checkAuth = true)
    {
        $build = $this->getDoctrine()->getRepository('AppCoreBundle:Build')->find($id);

        if (!$build) {
            throw $this->createNotFoundException('Build not found');
        }

        if ($checkAuth && !$build->getProject()->getUsers()->contains($this->getUser())) {
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

    protected function publishWebsocket(MessageInterface $message)
    {
        $producer = $this->get('old_sound_rabbit_mq.websocket_producer');
        $producer->publish((string) $message);
    }

    protected function removeAndFlush($entity)
    {
        $em = $this->getDoctrine()->getManager();
        $em->remove($entity);
        $em->flush();
    }

    protected function persistAndFlush()
    {
        $entities = func_get_args();

        $em = $this->getDoctrine()->getManager();
        array_walk($entities, [$em, 'persist']);
        $em->flush();
    }

    protected function setCurrentProjectId($id)
    {
        $this->get('request')->attributes->set('current_project_id', (integer) $id);
    }

    protected function setCurrentBuildId($id)
    {
        $this->get('request')->attributes->set('current_build_id', (integer) $id);
    }

    protected function findUserByUsername($username)
    {
        $user = $this->getDoctrine()->getRepository('AppCoreBundle:User')->findOneByUsername($username);

        if (!$user) {
            throw $this->createNotFoundException('User not found');
        }

        return $user;
    }

    protected function findProject($id, $checkAuth = true)
    {
        $project = $this->getDoctrine()->getRepository('AppCoreBundle:Project')->find($id);

        if (!$project) {
            throw $this->createNotFoundException('Project not found');
        }

        if ($checkAuth && !$project->getUsers()->contains($this->getUser())) {
            throw new AccessDeniedException();
        }

        return $project;
    }

    protected function findProjectsBySlug($slug)
    {
        $projects = $this->getDoctrine()->getRepository('AppCoreBundle:Project')->findBySlug($slug);

        if (count($projects) === 0) {
            throw $this->createNotFoundException(sprintf('Could not find projects with slug "%s"', $slug));
        }

        return $projects;
    }

    protected function findProjectBySlug($slug)
    {
        $project = $this->getDoctrine()->getRepository('AppCoreBundle:Project')->findOneBySlug($slug);

        if (!$project) {
            throw $this->createNotFoundException(sprintf('Could not find project with slug "%s"', $slug));
        }
        
        return $project;
    }
}