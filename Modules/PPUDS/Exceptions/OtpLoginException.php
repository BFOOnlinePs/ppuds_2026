<?php

namespace Modules\PPUDS\Exceptions;

use Illuminate\Http\Request;
use Modules\Core\Exceptions\BusinessRuleException;

/**
 * A sign-in code request the system refuses as a whole: the feature is off,
 * or too many codes were asked for. Rendered in the API's usual envelope; the
 * web page shows the message as a form error.
 *
 * It never says whether the email belongs to anyone — both cases are decided
 * before, and independently of, any account lookup.
 */
class OtpLoginException extends BusinessRuleException
{
    public function __construct(string $message, int $code, public readonly ?int $retryAfter = null)
    {
        parent::__construct($message, $code);
    }

    public static function unavailable(): self
    {
        return new self(__('Signing in with a code is not available.'), 403);
    }

    public static function throttled(int $seconds): self
    {
        return new self(
            __('Too many requests. Please try again in :seconds seconds.', ['seconds' => max($seconds, 1)]),
            429,
            max($seconds, 1),
        );
    }

    public function render(Request $request)
    {
        if (! ($request->expectsJson() || $request->is('api/*'))) {
            return false;
        }

        return response()->json([
            'status' => false,
            'message' => $this->getMessage(),
            'data' => $this->retryAfter !== null ? ['retry_after' => $this->retryAfter] : null,
        ], $this->code, $this->retryAfter !== null ? ['Retry-After' => $this->retryAfter] : [], JSON_UNESCAPED_UNICODE);
    }
}
