<?php

namespace CatLab\Charon\Laravel\InputParsers;

use \Request;

/**
 * Class AbstractInputParser
 * @package CatLab\Charon\Laravel\InputParsers
 */
abstract class AbstractInputParser
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