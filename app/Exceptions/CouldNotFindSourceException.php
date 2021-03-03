<?php

namespace App\Exceptions;

class CouldNotFindSourceException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Could not find source file on the current host.');
    }
}
