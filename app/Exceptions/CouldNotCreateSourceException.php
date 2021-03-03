<?php

namespace App\Exceptions;

class CouldNotCreateSourceException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Could not create source file on the current host.');
    }
}
