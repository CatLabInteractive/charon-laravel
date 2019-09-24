<?php


namespace CatLab\Charon\Laravel\Contracts;

/**
 * Interface ResponseFactory
 * @package CatLab\Charon\Laravel\Contracts
 */
interface ResponseFactory
{
    public function createResponse(): Response;
}
