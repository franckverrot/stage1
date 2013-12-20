<?php

namespace App\CoreBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class BuildController extends Controller
{
    public function logsLoadAction(Request $request, $id)
    {
        $build = $this->findBuild($id);

        $redis = $this->get('app_core.redis');
        $page = $request->get('page', 1);
        $limit = $request->get('limit', $this->container->getParameter('build_logs_load_limit'));

        $list = $build->getLogsList();
        $length = $redis->llen($list);

        $offset = $page < 0
            ? $length + ($page * $limit)
            : ($page - 1) * $limit;

        if ($offset < 0) {
            $offset = 0;
        }

        $response = [
            'offset' => $offset,
            'limit' => $limit,
            'length' => $length,
            'pages' => ceil($length / $limit),
            'page' => ceil($offset / $limit) + 1,
        ];

        if ($offset >= $length) {
            $response['error'] = 'out of bound';

            return new JsonResponse($response, 400);
        }

        $items = $redis->lrange($list, $offset, $offset + $limit);
        $items = array_map('json_decode', $items);

        $response['items'] = $items;

        return new JsonResponse($response, 200);
    }
}