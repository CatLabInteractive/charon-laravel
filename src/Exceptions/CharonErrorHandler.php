<?php


namespace CatLab\Charon\Laravel\Exceptions;

use CatLab\Charon\Exceptions\NoInputDataFound;
use CatLab\Requirements\Exceptions\ValidationException;
use Exception;
use CatLab\Charon\Exceptions\EntityNotFoundException;

/**
 * Class Handler
 * @package CatLab\Charon\Laravel\Exceptions
 */
class CharonErrorHandler
{
    protected $responseType = 'application/json';

    /**
     * Try to handle a Charon exception
     * @param $request
     * @param Exception $exception
     * @return \Illuminate\Http\JsonResponse
     */
    public function handleException($request, Exception $exception)
    {
        switch (get_class($exception)) {

            case EntityNotFoundException::class:
                return $this->jsonResponse($exception->getMessage(), null, 404);

            case NoInputDataFound::class:
                return $this->jsonResponse($exception->getMessage(), null, 400);

            case ValidationException::class:

                $details = [];


                return $this->jsonResponse($exception->getMessage(), null, 422);


        }
    }

    public function jsonResponse($message, $detail = null, $status = 500)
    {
        return response()->json(['errors' => [
            'status' => $status,
            'title' => $message,
            'detail' => $detail
        ]], $status);
    }
}
