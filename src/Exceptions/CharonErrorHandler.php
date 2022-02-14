<?php


namespace CatLab\Charon\Laravel\Exceptions;

use CatLab\Charon\Exceptions\CharonException;
use CatLab\Charon\Exceptions\InputDecodeException;
use CatLab\Charon\Exceptions\NoInputDataFound;
use CatLab\Requirements\Exceptions\ResourceValidationException;
use CatLab\Requirements\Exceptions\ValidationException;
use CatLab\Requirements\Models\Message;
use CatLab\Requirements\Models\TranslatableMessage;
use Exception;
use CatLab\Charon\Exceptions\EntityNotFoundException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Throwable;

/**
 * Class Handler
 * @package CatLab\Charon\Laravel\Exceptions
 */
class CharonErrorHandler
{
    /**
     *
     */
    const TITLE_RESOURCE_NOT_FOUND = 'Resource not found.';
    const TITLE_RESOURCE_VALIDATION_FAILED = 'Resource validation failed.';
    const TITLE_INPUT_VALIDATION_FAILED = 'Input validation failed.';

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
    public function handleException($request, Throwable $exception)
    {
        switch (true) {

            case $exception instanceof EntityNotFoundException:
                return $this->jsonApiErrorResponse(
                    self::TITLE_RESOURCE_NOT_FOUND,
                    $exception->getErrorMessage(),
                    [],
                    404
                );

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
                    $exception->getErrorMessage(),
                    null,
                    [],
                    400
                );

            case $exception instanceof ResourceValidationException:
                return $this->getResourceValidationResponse($exception);

            case $exception instanceof InputValidatorException:
                return $this->getInputValidatorException($exception);

            case $exception instanceof ValidationException:
            case $exception instanceof CharonHttpException:
                return $this->jsonApiErrorResponse(
                    $exception->getMessage(),
                    $exception->getMessage(),
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
    protected function processMessage($message)
    {
        return strval($message);
    }

    /**
     * @param Message|string $detail
     * @param array $detailParameters
     * @return string
     */
    protected function processDetail($detail, array $detailParameters)
    {
        return strval($detail);
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

            $error = [
                'status' => 422,
                'source' => $source,
                'title' => $this->processMessage(self::TITLE_RESOURCE_VALIDATION_FAILED),
                'detail' => $this->processDetail($validationMessage, [ $property ])
            ];

            if ($validationMessage instanceof TranslatableMessage) {
                $error['message_template'] = $validationMessage->getTemplate();
                $error['message_values'] = $validationMessage->getValues();
            }

            $errors[] = $error;
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

    /**
     * @param InputValidatorException $exception
     * @return \Illuminate\Http\JsonResponse
     */
    protected function getInputValidatorException(InputValidatorException $exception)
    {
        $errors = [];
        foreach ($exception->getMessages() as $validationMessage) {
            /** @var Message $validationMessage */
            /** @var Message $validationMessage */
            $property = $validationMessage->getPropertyName();

            $source = [
                'pointer' => '/' . $exception->getContainer() . '/' . $property
            ];

            $error = [
                'status' => 400,
                'source' => $source,
                'title' => $this->processMessage(self::TITLE_INPUT_VALIDATION_FAILED),
                'detail' => $this->processDetail($validationMessage, [ $property ]),
                'provided' => $validationMessage->getProvidedValue()
            ];

            if ($validationMessage instanceof TranslatableMessage) {
                $error['message_template'] = $validationMessage->getTemplate();
                $error['message_values'] = $validationMessage->getValues();
            }

            $errors[] = $error;
        }

        return $this->toJsonApiResponse(['errors' => $errors ], 400);
    }
}
