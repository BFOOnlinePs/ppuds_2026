<?php

namespace Modules\Core\Exceptions;

use Exception;
use Illuminate\Http\Request;

class BusinessRuleException extends Exception
{
    protected $code;

    public function __construct($message = "", $code = 422)
    {
        parent::__construct($message, $code);
        $this->code = $code;
    }

    public function render(Request $request)
    {
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status'    => false,
                'message'   => $this->getMessage(),
                'data'      => null
            ], $this->code);
        }

        return false;
    }
}
