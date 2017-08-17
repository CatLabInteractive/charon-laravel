<?php

namespace CatLab\Charon\Laravel\InputParsers;

use Illuminate\Http\Request;

/**
 * Class PostInputParser
 * @package CatLab\Charon\InputParsers
 */
class PostInputParser extends \CatLab\Charon\InputParsers\PostInputParser
{
    use LaravelInputParser;

    /**
     * @param null $request
     * @return mixed
     */
    protected function getPostFromRequest($request = null)
    {
        if ($request instanceof Request) {
            return $request->input();
        } else {
            return $_POST;
        }
    }
}