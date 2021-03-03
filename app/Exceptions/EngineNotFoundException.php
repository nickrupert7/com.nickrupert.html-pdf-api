<?php

namespace App\Exceptions;

class EngineNotFoundException extends \Exception
{
    public function __construct()
    {
        parent::__construct('No suitable conversion engine was found on the current host.');
    }
}
