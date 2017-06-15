<?php

namespace CatLab\Charon\Laravel\InputParsers;

use \Request;

/**
 * Trait LaravelInputParser
 * @package CatLab\Charon\Laravel\InputParsers
 */
trait LaravelInputParser
{
    /**
     * @return mixed|string
     */
    protected function getContentType()
    {
        $contentType = mb_strtolower(Request::header('content-type'));
        $parts = explode(';', $contentType);
        return $parts[0];
    }

    /**
     * @return bool|string
     */
    protected function getRawContent()
    {
        return Request::instance()->getContent();
    }
}