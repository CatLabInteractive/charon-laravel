<?php

namespace CatLab\Charon\Laravel\Middleware;

use Closure;
use CatLab\Charon\Models\ResourceResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ResourceToOutput
{
    const RETURN_TYPE_TEXT = 'text';
    const RETURN_TYPE_JSON = 'json';

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $response = $next($request);

        if ($response instanceof ResourceResponse) {

            $format = $this->getReturnFormat($request);
            switch($format) {
                case self::RETURN_TYPE_TEXT:
                    return $this->toText($response);

                case self::RETURN_TYPE_JSON:
                default:
                    return $this->toJSON($response);
            }

        }

        return $response;
    }

    /**
     * @param ResourceResponse $response
     * @return \Illuminate\Http\JsonResponse
     */
    protected function toJSON(ResourceResponse $response)
    {
        return \Response::json($response->getResource()->toArray());
    }

    /**
     * @param ResourceResponse $response
     * @return Response
     */
    protected function toText(ResourceResponse $response)
    {
        return new Response(
            print_r($response->getResource()->toArray(), true),
            200,
            [
                'Content-type' => 'text/text'
            ]
        );
    }

    /**
     * @param Request $request
     * @return string
     */
    protected function getReturnFormat(Request $request)
    {
        $format = $request->route('format');
        switch (strtolower($format)) {
            case 'txt':
            case 'text':
                return self::RETURN_TYPE_TEXT;

            case 'json':
            default:
                return self::RETURN_TYPE_JSON;
        }
    }
}