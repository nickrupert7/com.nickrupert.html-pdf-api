<?php

namespace App\Exceptions;

class CouldNotCreatePdfException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Could not create PDF file on the current host.');
    }
}
