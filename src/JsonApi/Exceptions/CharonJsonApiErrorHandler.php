<?php

namespace CatLab\Charon\Laravel\JsonApi\Exceptions;

use CatLab\Charon\Laravel\Exceptions\CharonErrorHandler;

/**
 * Class CharonJsonApiErrorHandler
 * @package CatLab\Charon\Laravel\JsonApi\Exceptions
 */
class CharonJsonApiErrorHandler extends CharonErrorHandler
{
    public function jsonResponse($message, $detail = null, $status = 500)
    {
        return response()->json([
            'errors' => [
                [
                    'status' => $status,
                    'title' => $message,
                    'detail' => $detail
                ]
        ]], $status)->header('Content-type', 'application/vnd.api+json');
    }
}
