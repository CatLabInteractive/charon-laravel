<?php


namespace CatLab\Charon\Laravel\Exceptions;

use CatLab\Charon\Exceptions\NoInputDataFound;
use CatLab\Requirements\Exceptions\ValidationException;
use Exception;
use CatLab\Charon\Exceptions\EntityNotFoundException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * Class Handler
 * @package CatLab\Charon\Laravel\Exceptions
 */
class CharonErrorHandler
{
    /**
     *
     */
    const TITLE_RESOURCE_NOT_FOUND = 'Resource not found';

    /**
     * @var string
     */
    protected $responseType = 'application/json';

    /**
     * Try to handle a Charon exception
     * @param $request
     * @param Exception $exception
     * @return \Illuminate\Http\JsonResponse|null
     */
    public function handleException($request, Exception $exception)
    {
        switch (get_class($exception)) {

            case EntityNotFoundException::class:
            case ModelNotFoundException::class:
                return $this->jsonApiErrorResponse(
                    self::TITLE_RESOURCE_NOT_FOUND,
                    $exception->getMessage(),
                    [],
                    404
                );

            case NoInputDataFound::class:
                return $this->jsonApiErrorResponse(
                    $exception->getMessage(),
                    null,
                    [],
                    400
                );

            case ValidationException::class:
                return $this->jsonApiErrorResponse(
                    $exception->getMessage(),
                    null,
                    [],
                    422
                );
        }

        return null;
    }

    /**
     * @param $message
     * @param string $detailMessage
     * @param array $detailParameters
     * @param int $status
     * @return \Illuminate\Http\JsonResponse
     */
    public function jsonApiErrorResponse(
        string $message,
        string $detailMessage = null,
        array $detailParameters = [],
        int $status = 500
    ) {
        return response()->json(['errors' => [
            'status' => $status,
            'title' => $this->processMessage($message),
            'detail' => $detailMessage !== null ? $this->processDetail($detailMessage, $detailParameters) : $detailMessage
        ]], $status);
    }

    /**
     * @param string $message
     * @return string
     */
    protected function processMessage(string $message)
    {
        return $message;
    }

    /**
     * @param string $detail
     * @param array $detailParameters
     * @return string
     */
    protected function processDetail(string $detail, array $detailParameters)
    {
        return $detail;
    }
}
