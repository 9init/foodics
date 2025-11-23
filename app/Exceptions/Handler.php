<?php

namespace App\Exceptions;

use Exception;

class Handler extends Exception
{
    public function render($request, Throwable $e)
    {
        if ($request->expectsJson()) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }

        return parent::render($request, $e);
    }
}
