<?php

namespace App\CoreBundle\Controller;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Process\ProcessBuilder;

use App\Model\Build;

class BuildController extends Controller
{
    public function infosAction($id)
    {
        $build = $this->findBuild($id);
        $this->setCurrentBuild($build);

        return $this->render('AppCoreBundle:Build:infos.html.twig', ['build' => $build]);
    }

    public function failureAction($id)
    {
        $build = $this->findBuild($id);
        $this->setCurrentBuild($build);

        return $this->render('AppCoreBundle:Build:failure.html.twig', ['build' => $build]);
    }

    public function logsDownloadAction(Request $request, $id)
    {
        $build = $this->findBuild($id);
        $redis = $this->get('app_core.redis');
        $perPage = $this->container->getParameter('build_logs_load_limit');
        $list = $build->getLogsList();
        $gzip = (bool) $request->get('gzip', false);

        $tmpuri = tempnam(sys_get_temp_dir(), 'logs-download-');

        if ($gzip) {
            $tmp = gzopen($tmpuri, 'w9');
        } else {
            $tmp = fopen($tmpuri, 'w+');
        }

        $offset = 0;

        do {
            $logs = $redis->lrange($list, $offset, $offset + $perPage);

            $logs = array_map(function($log) {
                $message = json_decode($log, true)['message'];
                $message = preg_replace('/\[\d+(?:;\d+)?m/', '', $message);

                return $message;
            }, $logs);

            $offset += $perPage;

            $string = implode('', $logs);

            if ($gzip) {
                gzwrite($tmp, $string);
            } else {
                fwrite($tmp, $string);
            }

        } while (count($logs) >= $perPage);

        fclose($tmp);

        $filename = sprintf('%s_%s_%s.%s', $build->getProject()->getSlug(), $build->getRef(), $build->getId(), $gzip ? 'log.gz' : 'log');

        $response = new StreamedResponse();

        $response->headers->set('Content-Disposition', 'attachment; filename='.$filename);
        $response->headers->set('Content-Type', $gzip ? 'application/gzip' : 'text/plain');

        $response->setCallback(function() use($tmpuri) {
            readfile($tmpuri);
            unlink($tmpuri);
        });

        return $response;
    }

    public function logsLoadAction(Request $request, $id)
    {
        $build = $this->findBuild($id);

        $redis = $this->get('app_core.redis');
        $page = $request->get('page', 1);
        $limit = $request->get('limit', $this->container->getParameter('build_logs_load_limit'));

        $list = $build->getLogsList();
        $total = $redis->llen($list);
        $pages = ceil($total / $limit);

        if ($page < 0) {
            $page = $pages + ($page + 1);
        }

        $offset_start = max(0, ($page - 1) * $limit);
        $offset_stop  = min($offset_start + $limit - 1, $total);

        $response = [
            'source' => $list,
            'offset_start' => $offset_start,
            'offset_stop' => $offset_stop,
            'limit' => $limit,
            'total' => $total,
            'pages' => $pages,
            'request_page' => $request->get('page'),
            'page' => $page,
            'previous_page' => ($page - 1 < 1) ? null : $page - 1,
            'next_page' => ($page + 1 > $pages) ? null : $page + 1,
        ];

        if ($offset_start >= $total) {
            $response['error'] = 'out of bound';

            return new JsonResponse($response, 400);
        }

        $items = $redis->lrange($list, $offset_start, $offset_stop);
        $items = array_map('json_decode', $items);

        $response['items_length'] = count($items);
        $response['items'] = $items;

        return new JsonResponse($response, 200);
    }

    public function showAction($id, $forceTab = null)
    {
        $this->setCurrentBuildId($id);

        $build = $this->findBuild($id);
        $this->setCurrentProjectId($build->getProject()->getId());

        if (null === $forceTab) {
            if ($build->isRunning()) {
                $forceTab = 'logs';
            } else {
                $forceTab = 'output';
            }
        }

        /**
         * @todo application logs do not work yet, so we just force every request to display the build output
         */
        $forceTab = 'output';

        $streamLogs = false;

        if ($forceTab === 'output') {
            $logsList = $build->getLogsList();
            $logsLength = $this->get('app_core.redis')->llen($logsList);

            $streamLimit = $this->container->getParameter('build_logs_stream_limit');

            if ($streamLimit === 0 || $logsLength < $streamLimit) {
                $streamLogs = true;
            }
        }

        return $this->render('AppCoreBundle:Build:show.html.twig', [
            'build' => $build,
            'forceTab' => $forceTab,
            'streamLogs' => $streamLogs,
        ]);
    }

    public function cancelAction($id)
    {
        try {
            $build = $this->findBuild($id);
            $build->setStatus(Build::STATUS_CANCELED);

            $this->persistAndFlush($build);

            $factory = $this->get('app_core.message.factory');
            $message = $factory->createBuildCanceled($build);

            $this->publishWebsocket($message);

            return new JsonResponse(null, 200);
        } catch (Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], 500);
        }
    }

    public function killAction($id)
    {
        try {
            $build = $this->findBuild($id);
            $routingKey = $build->getRoutingKey();

            $this->get('logger')->info('sending kill order', [
                'build' => $id,
                'routing_key' => $routingKey
            ]);

            $this->get('old_sound_rabbit_mq.kill_producer')->publish(json_encode(['build_id' => $id]), $routingKey);

            return new JsonResponse(null, 200);
        } catch (Exception $e) {
            return new JsonResponse(['message' => $e->getMessage()], 500);
        }
    }
}