<?php

namespace Modules\PPUDS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Modules\Core\Entities\User;
use Modules\PPUDS\DTOs\OtpChallengeDTO;
use Modules\PPUDS\Enums\LoginClient;
use Modules\PPUDS\Enums\OtpChannel;
use Modules\PPUDS\Exceptions\OtpLoginException;
use Modules\PPUDS\Http\Requests\Auth\OtpCodeRequest;
use Modules\PPUDS\Services\LoginOtpService;

/**
 * The dashboard's one-time code page — internal login only.
 *
 * It serves two flows, depending on the dashboard's sign-in mode:
 *  - code only / password or code: email first, then the code;
 *  - password then code: the password was accepted by the login form
 *    (RequireLoginCodeAfterPassword) and only the code is asked for here.
 *
 * Plain forms, like the password page beside it. The rules live in
 * LoginOtpService; this only keeps track of who is waiting for a code and,
 * on success, starts the session exactly as the password login does.
 */
class OtpLoginController extends Controller
{
    private const SESSION_KEY = 'otp_login';

    /** Called once the password step of a two-step sign-in succeeded. */
    public static function rememberPendingSignIn(Request $request, User $user, OtpChallengeDTO $challenge, bool $remember): void
    {
        $request->session()->put(self::SESSION_KEY, [
            'email' => (string) $user->email,
            'after_password' => $user->getKey(),
            'remember' => $remember,
            ...self::challengeState($challenge),
        ]);
    }

    public function show(Request $request, LoginOtpService $otp)
    {
        $pending = $this->pending($request, $otp);

        // Without a code on its way, this page only exists for code-only sign-in.
        if (! $pending && ! $otp->codeLoginAvailable(LoginClient::WEB)) {
            return redirect()->route('login');
        }

        return view('ppuds::auth.otp-login', [
            'pending' => $pending,
            'resendAfter' => $pending ? max(0, (int) $pending['resend_at'] - now()->timestamp) : 0,
            'passwordLogin' => $otp->passwordLoginAllowed(LoginClient::WEB) && ! ($pending['after_password'] ?? null),
            'channelChoice' => $otp->channelChoiceAllowed(),
            'defaultChannel' => $otp->resolveChannel()?->key(),
            'channelOptions' => collect($otp->enabledChannels())
                ->mapWithKeys(fn (OtpChannel $channel): array => [$channel->key() => $channel->getLabel()])
                ->all(),
        ]);
    }

    /** Sends the code — or sends it again. */
    public function send(OtpCodeRequest $request, LoginOtpService $otp)
    {
        $pending = $this->pending($request, $otp);

        try {
            if ($pending && $pending['after_password']) {
                // Resending the second step: only for the person whose password was accepted.
                $user = User::query()->findOrFail($pending['after_password']);
                $challenge = $otp->requestAfterPassword($user, OtpChannel::fromKey($request->input('channel')), $request->ip(), LoginClient::WEB);
                $email = (string) $user->email;
            } else {
                $email = (string) $request->input('email');
                $challenge = $otp->request($email, OtpChannel::fromKey($request->input('channel')), null, $request->ip(), LoginClient::WEB);
            }
        } catch (OtpLoginException $e) {
            return back()->withInput($request->only('email', 'channel'))->withErrors(['email' => $e->getMessage()]);
        }

        $request->session()->put(self::SESSION_KEY, [
            'email' => $email,
            'after_password' => $pending['after_password'] ?? null,
            'remember' => $pending['remember'] ?? false,
            ...self::challengeState($challenge),
        ]);

        return redirect()->route('login.otp');
    }

    public function verify(Request $request, LoginOtpService $otp)
    {
        $pending = $this->pending($request, $otp);

        if (! $pending) {
            return redirect()->route('login.otp');
        }

        $request->validate([
            'code' => ['required', 'string', 'regex:/^\s*\d{4,8}\s*$/'],
        ], [
            'code.required' => __('The code is required.'),
            'code.regex' => __('The code is invalid or has expired.'),
        ]);

        try {
            // A wrong code throws a ValidationException, which sends the
            // person back to the form with the error under the code field.
            $user = $pending['after_password']
                ? $otp->verifyAfterPassword(User::query()->findOrFail($pending['after_password']), (string) $request->input('code'), 'web', LoginClient::WEB)
                : $otp->verify($pending['email'], (string) $request->input('code'), null, 'web', LoginClient::WEB);
        } catch (OtpLoginException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        $request->session()->forget(self::SESSION_KEY);

        Auth::login($user, (bool) $pending['remember']);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    /** Starts over: back to the email (or the password) step. */
    public function reset(Request $request)
    {
        $request->session()->forget(self::SESSION_KEY);

        return redirect()->route('login');
    }

    /**
     * The sign-in waiting for its code, if any. A two-step one is dropped as
     * soon as the dashboard no longer uses two-step sign-in.
     */
    private function pending(Request $request, LoginOtpService $otp): ?array
    {
        $pending = $request->session()->get(self::SESSION_KEY);

        if (! is_array($pending)) {
            return null;
        }

        $stillValid = ($pending['after_password'] ?? null)
            ? $otp->codeAfterPasswordRequired(LoginClient::WEB)
            : $otp->codeLoginAvailable(LoginClient::WEB);

        if (! $stillValid) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        return $pending;
    }

    private static function challengeState(OtpChallengeDTO $challenge): array
    {
        return [
            'channel' => $challenge->channel->key(),
            'expires_in' => $challenge->expiresIn,
            'code_length' => $challenge->codeLength,
            'resend_at' => now()->addSeconds($challenge->resendAfter)->timestamp,
        ];
    }
}
