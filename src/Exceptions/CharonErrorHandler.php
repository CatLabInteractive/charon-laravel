<?php


namespace CatLab\Charon\Laravel\Exceptions;

use CatLab\Charon\Exceptions\InputDecodeException;
use CatLab\Charon\Exceptions\NoInputDataFound;
use CatLab\Requirements\Exceptions\ResourceValidationException;
use CatLab\Requirements\Exceptions\ValidationException;
use CatLab\Requirements\Models\Message;
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
    const TITLE_RESOURCE_VALIDATION_FAILED = 'Resource validation failed';

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
        switch (true) {

            case $exception instanceof EntityNotFoundException:
            case $exception instanceof ModelNotFoundException:
                return $this->jsonApiErrorResponse(
                    self::TITLE_RESOURCE_NOT_FOUND,
                    $exception->getMessage(),
                    [],
                    404
                );

            case $exception instanceof NoInputDataFound:
            case $exception instanceof InputDecodeException:
                return $this->jsonApiErrorResponse(
                    $exception->getMessage(),
                    null,
                    [],
                    400
                );

            case $exception instanceof ResourceValidationException:
                return $this->getResourceValidationResponse($exception);

            case $exception instanceof ValidationException:
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
            [
                'status' => $status,
                'title' => $this->processMessage($message),
                'detail' => $detailMessage !== null
                    ? $this->processDetail($detailMessage, $detailParameters)
                    : $detailMessage
            ]
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

    /**
     * @param ResourceValidationException $exception
     * @return \Illuminate\Http\JsonResponse
     */
    protected function getResourceValidationResponse(ResourceValidationException $exception)
    {
        $errors = [];
        foreach ($exception->getMessages() as $validationMessage) {
            /** @var Message $validationMessage */

            $property = $validationMessage->getPropertyName();
            $source = [
                'pointer' => '/data/' . $property
            ];

            $errors[] = [
                'status' => 422,
                'source' => $source,
                'title' => self::TITLE_RESOURCE_VALIDATION_FAILED,
                'detail' => $this->processDetail($validationMessage->getMessage(), [ $property ])
            ];
        }

        return $this->toJsonApiResponse(['errors' => $errors ], 422);
    }

    /**
     * @param $data
     * @param $status
     * @return \Illuminate\Http\JsonResponse
     */
    protected function toJsonApiResponse($data, $status)
    {
        return response()->json($data, $status, [
            'Content-Type' => $this->responseType
        ]);
    }
}
