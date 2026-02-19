<?php

namespace Tests\Integration\Controllers;

use CatLab\Charon\Laravel\Controllers\ResourceController;
use Illuminate\Routing\Controller;

class BaseResourceController extends Controller
{
    use ResourceController;

    public function __construct($resourceDefinition = null)
    {
        if ($resourceDefinition) {
            $this->setResourceDefinition($resourceDefinition);
        }
    }
}
