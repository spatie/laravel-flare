<?php

namespace Workbench\App\Http\Controllers;

class InvokableController
{
    public function __invoke()
    {
        return "To invoke or not to invoke, that is the question!";
    }
}
