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
                    404
                );

            case NoInputDataFound::class:
                return $this->jsonApiErrorResponse(
                    $exception->getMessage(),
                    null,
                    400
                );

            case ValidationException::class:
                $details = [];

                return $this->jsonApiErrorResponse($exception->getMessage(), null, 422);
        }

        return null;
    }

    /**
     * @param $message
     * @param null $detail
     * @param int $status
     * @return \Illuminate\Http\JsonResponse
     */
    public function jsonApiErrorResponse($message, $detail = null, $status = 500)
    {
        return response()->json(['errors' => [
            'status' => $status,
            'title' => $this->processMessage($message),
            'detail' => $detail !== null ? $this->processDetail($detail) : $detail
        ]], $status);
    }

    /**
     * @param string $message
     * @return string
     */
    protected function processMessage($message)
    {
        return $message;
    }

    /**
     * @param string[] $detail
     * @return string[]
     */
    protected function processDetail(array $detail)
    {
        return $detail;
    }
}
