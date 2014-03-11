<?php

namespace App\CoreBundle\Controller;

use App\Model\Branch;
use App\Model\Build;
use App\Model\Project;
use App\Model\ProjectSettings;
use App\Model\GithubPayload;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

use Exception;

class HooksController extends Controller
{
    public function scheduleAction(Request $request)
    {
        try {
            $project = $this->findProject($request->get('project'), false);
            $ref = $request->get('ref');
            $hash = $this->getHashFromRef($project, $ref);

            $scheduler = $this->get('app_core.build_scheduler');
            $build = $scheduler->schedule($project, $ref, $hash);

            return new JsonResponse([
                'build_url' => $this->generateUrl('app_core_build_show', ['id' => $build->getId()]),
                'build' => $build->asMessage(),
            ], 201);            
        } catch (Exception $e) {
            $this->container->get('logger')->error($e->getMessage());

            if (method_exists($e, 'getResponse')) {
                $this->container->get('logger')->error($e->getResponse()->getBody(true));
            }

            return new JsonResponse(['class' => 'danger', 'message' => $e->getMessage()], 500);
        }
    }

    public function githubAction(Request $request)
    {
        try {
            $logger = $this->get('logger');
            $payload = json_decode($request->getContent());

            if (!isset($payload->ref)) {
                return new JsonResponse(json_encode(null), 400);
            }

            $ref = substr($payload->ref, 11);
            $hash = $payload->after;

            # first, find project!
            $em = $this->getDoctrine()->getManager();

            $project = $em->getRepository('Model:Project')->findOneByGithubId($payload->repository->id);

            if (!$project) {
                throw $this->createNotFoundException('Unknown Github project');
            }

            # then, check if ref is configured to be automatically built
            $doBuild = false;

            switch ($project->getSettings()->getPolicy()) {
                case ProjectSettings::POLICY_ALL:
                    $doBuild = true;
                    break;
                case ProjectSettings::POLICY_NONE:
                    $doBuild = false;
                    break;
                case ProjectSettings::POLICY_PATTERNS:
                    $patterns = explode(PHP_EOL, $project->getSettings()->getBranchPatterns());

                    foreach ($patterns as $pattern) {
                        $regex = strtr($pattern, ['*' => '.*', '?' => '.']);

                        if (preg_match('/'.$regex.'/i', $ref)) {
                            $doBuild = true;
                        }
                    }
                    break;
                default:
                    $logger->error('could not find a build policy', ['project' => $project->getId(), 'ref' => $ref]);
                    return new JsonResponse(['class' => 'danger', 'message' => 'Could not find a build policy'], 400);
            }

            if (!$doBuild) {
                $logger->info('build declined by project policy', ['project' => $project->getId(), 'ref' => $ref]);
                return new JsonResponse(['class' => 'info', 'message' => 'Build declined by project policy ('.$project->getSettings()->getPolicy().')'], 200);
            }

            if ($hash === '0000000000000000000000000000000000000000') {
                $branch = $em
                    ->getRepository('Model:Branch')
                    ->findOneByProjectAndName($project, $ref);

                $branch->setDeleted(true);

                $em->persist($branch);
                $em->flush();

                return new JsonResponse(json_encode(null), 200);
            }

            if ($project->getStatus() === Project::STATUS_HOLD) {
                return new JsonResponse(['class' => 'danger', 'message' => 'Project is on hold']);
            }

            $sameHashBuilds = $em->getRepository('Model:Build')->findByHash($hash);

            if (count($sameHashBuilds) > 0) {
                $logger->warn('found builds with same hash', ['cound' => count($sameHashBuilds)]);
                $allowRebuild = array_reduce($sameHashBuilds, function($result, $b) {
                    return $result || $b->getAllowRebuild();
                }, false);
            }

            if (isset($allowRebuild) && !$allowRebuild) {
                $logger->warn('build already scheduled for hash', ['hash' => $hash]);
                return new JsonResponse(['class' => 'danger', 'message' => 'Build already scheduled for hash'], 400);                    
            } else {
                $logger->info('scheduling build for hash', ['hash' => $hash]);
            }

            $scheduler = $this->get('app_core.build_scheduler');

            $initiator = $em->getRepository('Model:User')->findOneByGithubUsername($payload->pusher->name);

            $build = $scheduler->schedule($project, $ref, $hash);
            $logger->info('scheduled build', ['build' => $build->getId(), 'ref' => $build->getRef()]);

            $payload = new GithubPayload();
            $payload->setPayload($request->getContent());
            $payload->setBuild($build);
            $payload->setDeliveryId($request->headers->get('X-GitHub-Delivery'));
            $payload->setEvent($request->headers->get('X-GitHub-Event'));

            $em->persist($payload);
            $em->flush();

            return new JsonResponse([
                'build_url' => $this->generateUrl('app_core_build_show', ['id' => $build->getId()]),
                'build' => $build->asMessage(),
            ], 201);
        } catch (Exception $e) {
            $logger->error($e->getMessage(), ['trace' => $e->getTraceAsString()]);

            if (method_exists($e, 'getResponse')) {
                $logger->error($e->getResponse()->getBody(true));
            }

            return new JsonResponse(['class' => 'danger', 'message' => $e->getMessage()], 500);
        }
    }
}
