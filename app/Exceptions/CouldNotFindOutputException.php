<?php

namespace App\Exceptions;

class CouldNotFindOutputException extends \Exception
{
    public function __construct()
    {
        parent::__construct('Could not find output file on the current host.');
    }
}
