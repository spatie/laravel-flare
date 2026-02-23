<?php

namespace Workbench\App\Exceptions;

class ContextException extends \Exception
{

    public static function create(): self
    {
        return new static('I failed');
    }

    public function context(): array
    {
        return [
            'info' => 'Additional context information',
            'code' => 1234,
        ];
    }

}
