<?php

namespace App\Exceptions;

class PdfCreationFailedException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Engine failed to create PDF.');
    }
}
