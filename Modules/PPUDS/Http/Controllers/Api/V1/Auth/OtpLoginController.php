<?php

namespace Modules\PPUDS\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;
use Modules\Core\Actions\StoreDeviceTokenAction;
use Modules\Core\Entities\User;
use Modules\Core\Traits\ApiResponse;
use Modules\Core\Transformers\V1\UserResource;
use Modules\PPUDS\Entities\CompanyQrCode;
use Modules\PPUDS\Enums\LoginClient;
use Modules\PPUDS\Enums\OtpChannel;
use Modules\PPUDS\Exceptions\OtpLoginException;
use Modules\PPUDS\Http\Requests\Auth\OtpCodeRequest;
use Modules\PPUDS\Http\Requests\Auth\OtpVerifyRequest;
use Modules\PPUDS\Services\CompanyQrCodeService;
use Modules\PPUDS\Services\LoginOtpService;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The mobile app's internal sign-in, as configured from the dashboard
 * (Settings → Sign In & Verification → mobile app sign-in method):
 *
 *  - password only           → POST /api/v1/auth/login, as before;
 *  - code only               → otp/request {email}, then otp/verify {email, code};
 *  - password or code        → either of the two above;
 *  - password then code      → /api/v1/auth/login returns a login_ticket,
 *                              then otp/verify {login_ticket, code};
 *  - company QR (on its own switch) → company-qr/resolve, then otp/request and
 *                              otp/verify with the same qr_token.
 *
 * Every successful sign-in returns a Sanctum token with exactly the same
 * response as /api/v1/auth/login, so the rest of the app is unchanged.
 * Nothing applies while the system signs in through the university SSO.
 */
class OtpLoginController extends Controller
{
    use ApiResponse;

    private const GUARD = 'sanctum';

    public function __construct(
        private readonly LoginOtpService $otp,
        private readonly CompanyQrCodeService $qrCodes,
    ) {}

    /**
     * @OA\Get(
     *     path="/api/v1/auth/options",
     *     summary="How the app signs in",
     *     description="The mobile sign-in options chosen in the dashboard. Read it before showing the sign-in screen: it says whether to ask for a password, a code, either, or both (two-step), and whether to offer company QR sign-in. Also included as data.auth in GET /api/v1/config.",
     *     tags={"Auth"},
     *
     *     @OA\Response(response=200, description="The options", @OA\JsonContent(
     *
     *         @OA\Property(property="status", type="boolean", example=true),
     *         @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", type="object",
     *             @OA\Property(property="mode", type="string", enum={"password","code","password_or_code","password_then_code"}, example="password_or_code"),
     *             @OA\Property(property="password_login", type="boolean", example=true),
     *             @OA\Property(property="code_login", type="boolean", example=true),
     *             @OA\Property(property="two_step", type="boolean", example=false),
     *             @OA\Property(property="company_qr_login", type="boolean", example=true),
     *             @OA\Property(property="channels", type="array", @OA\Items(type="string", enum={"mail","sms"})),
     *             @OA\Property(property="default_channel", type="string", nullable=true, example="mail"),
     *             @OA\Property(property="channel_choice", type="boolean", example=false),
     *             @OA\Property(property="code_length", type="integer", nullable=true, example=6)
     *         )
     *     ))
     * )
     */
    public function options()
    {
        return $this->successResponse(
            $this->otp->availability(LoginClient::MOBILE),
            __('Sign-in options retrieved successfully.'),
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/company-qr/resolve",
     *     summary="Read a scanned company QR code",
     *     description="Checks a scanned company QR code and returns the company it belongs to, so the app can show who the person is signing in to. Pass the whole scanned link or just its token. The QR only names the company: the person still signs in with a one-time code, sending the same qr_token to otp/request and otp/verify.",
     *     tags={"Auth"},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"qr_token"},
     *
     *         @OA\Property(property="qr_token", type="string", example="https://ds.ppu.edu/c/9fQx...")
     *     )),
     *
     *     @OA\Response(response=200, description="The company", @OA\JsonContent(
     *
     *         @OA\Property(property="status", type="boolean", example=true),
     *         @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", type="object",
     *             @OA\Property(property="company", type="object",
     *                 @OA\Property(property="id", type="integer", example=12),
     *                 @OA\Property(property="name", type="string"),
     *                 @OA\Property(property="logo_url", type="string")
     *             )
     *         )
     *     )),
     *
     *     @OA\Response(response=403, description="Company QR sign-in is switched off"),
     *     @OA\Response(response=404, description="The QR code is unknown, revoked or expired, or the company is inactive")
     * )
     */
    public function resolveCompany(Request $request)
    {
        $request->validate(['qr_token' => ['required', 'string', 'max:255']]);

        $code = $this->companyFrom($request->input('qr_token'));
        $company = $code->company;

        return $this->successResponse([
            'company' => [
                'id' => $company->getKey(),
                'name' => $company->name,
                'logo_url' => $company->getImageAttribute(),
            ],
        ], __('Company found.'));
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/otp/request",
     *     summary="Send (or resend) a sign-in code",
     *     description="Sends a one-time sign-in code. Three uses: (1) code-only sign-in — send email; (2) company QR sign-in — send email and qr_token; (3) resend the code of a two-step sign-in — send login_ticket only. For (1) and (2) the answer is identical whether or not the email belongs to anyone, so it cannot be used to discover accounts. Use resend_after to time the resend button.",
     *     tags={"Auth"},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(
     *
     *         @OA\Property(property="email", type="string", format="email", example="supervisor@example.com", description="Required unless login_ticket is sent"),
     *         @OA\Property(property="login_ticket", type="string", nullable=true, description="Two-step mode: the ticket returned by /api/v1/auth/login"),
     *         @OA\Property(property="channel", type="string", enum={"mail","sms"}, nullable=true, description="Honoured only when options.channel_choice is true"),
     *         @OA\Property(property="qr_token", type="string", nullable=true)
     *     )),
     *
     *     @OA\Response(response=200, description="A code is on its way (if the account may sign in)", @OA\JsonContent(
     *
     *         @OA\Property(property="status", type="boolean", example=true),
     *         @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", type="object",
     *             @OA\Property(property="channel", type="string", example="mail"),
     *             @OA\Property(property="expires_in", type="integer", example=600),
     *             @OA\Property(property="resend_after", type="integer", example=60),
     *             @OA\Property(property="code_length", type="integer", example=6)
     *         )
     *     )),
     *
     *     @OA\Response(response=401, description="The login ticket is invalid or expired; sign in with the password again"),
     *     @OA\Response(response=403, description="This kind of sign-in is switched off"),
     *     @OA\Response(response=404, description="The company QR code is not valid"),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=429, description="Too many requests; data.retry_after says how many seconds to wait")
     * )
     */
    public function requestCode(OtpCodeRequest $request)
    {
        $channel = OtpChannel::fromKey($request->input('channel'));

        if ($request->filled('login_ticket')) {
            $challenge = $this->otp->requestAfterPassword(
                $this->ticketHolder($request->input('login_ticket')),
                $channel,
                $request->ip(),
                LoginClient::MOBILE,
            );

            return $this->successResponse($challenge->toArray(), __('A new code has been sent.'));
        }

        $company = $request->filled('qr_token')
            ? $this->companyFrom($request->input('qr_token'))->company
            : null;

        $challenge = $this->otp->request(
            (string) $request->input('email'),
            $channel,
            $company,
            $request->ip(),
            LoginClient::MOBILE,
        );

        return $this->successResponse(
            $challenge->toArray(),
            __('If the account can sign in this way, a code has been sent.'),
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v1/auth/otp/verify",
     *     summary="Sign in with the code",
     *     description="Exchanges a one-time code for a Sanctum token — the same response as /api/v1/auth/login. Send email + code for a code-only sign-in, email + code + qr_token for a company QR sign-in, or login_ticket + code to finish a two-step sign-in. A code works once, and only for the sign-in it was sent for.",
     *     tags={"Auth"},
     *
     *     @OA\RequestBody(required=true, @OA\JsonContent(required={"code"},
     *
     *         @OA\Property(property="email", type="string", format="email", example="supervisor@example.com", description="Required unless login_ticket is sent"),
     *         @OA\Property(property="login_ticket", type="string", nullable=true),
     *         @OA\Property(property="code", type="string", example="482913"),
     *         @OA\Property(property="qr_token", type="string", nullable=true),
     *         @OA\Property(property="device_name", type="string", nullable=true, example="Samsung S23"),
     *         @OA\Property(property="fcm_token", type="string", nullable=true)
     *     )),
     *
     *     @OA\Response(response=200, description="Signed in", @OA\JsonContent(
     *
     *         @OA\Property(property="status", type="boolean", example=true),
     *         @OA\Property(property="message", type="string"),
     *         @OA\Property(property="data", type="object",
     *             @OA\Property(property="token", type="string", example="1|abcdefg123456789"),
     *             @OA\Property(property="token_type", type="string", example="Bearer"),
     *             @OA\Property(property="user", type="object")
     *         )
     *     )),
     *
     *     @OA\Response(response=401, description="The login ticket is invalid or expired; sign in with the password again"),
     *     @OA\Response(response=403, description="This kind of sign-in is switched off"),
     *     @OA\Response(response=404, description="The company QR code is not valid"),
     *     @OA\Response(response=422, description="The code is wrong, used or expired"),
     *     @OA\Response(response=429, description="Too many wrong codes; data.retry_after says how many seconds to wait")
     * )
     */
    public function verifyCode(OtpVerifyRequest $request, StoreDeviceTokenAction $storeDeviceToken)
    {
        $code = (string) $request->input('code');
        $qrCode = null;

        if ($request->filled('login_ticket')) {
            $user = $this->otp->verifyAfterPassword(
                $this->ticketHolder($request->input('login_ticket')),
                $code,
                self::GUARD,
                LoginClient::MOBILE,
            );
        } else {
            $qrCode = $request->filled('qr_token') ? $this->companyFrom($request->input('qr_token')) : null;

            $user = $this->otp->verify(
                (string) $request->input('email'),
                $code,
                $qrCode?->company,
                self::GUARD,
                LoginClient::MOBILE,
            );
        }

        if ($qrCode) {
            $this->qrCodes->recordUse($qrCode);
        }

        return $this->signedIn($request, $user, $storeDeviceToken);
    }

    /**
     * The Sanctum token and user — exactly what /api/v1/auth/login answers.
     */
    private function signedIn(Request $request, User $user, StoreDeviceTokenAction $storeDeviceToken)
    {
        $deviceName = $request->input('device_name') ?: ($request->userAgent() ?: 'Unknown Device');
        $token = $user->createToken($deviceName)->plainTextToken;

        $storeDeviceToken->execute($user, $request->input('fcm_token'), $deviceName);

        event(new Login(self::GUARD, $user, false));

        $user = QueryBuilder::for(User::class)
            ->where('id', $user->getKey())
            ->allowedIncludes(UserResource::allowedIncludes())
            ->first();

        return $this->successResponse([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => new UserResource($user),
        ], __('User logged in successfully'));
    }

    /**
     * The person a two-step login ticket was issued to.
     *
     * @throws OtpLoginException when the ticket is forged or expired.
     */
    private function ticketHolder(?string $ticket): User
    {
        return $this->otp->userFromLoginTicket($ticket)
            ?? throw new OtpLoginException(__('Your sign-in has expired. Please enter your password again.'), 401);
    }

    /**
     * The working QR code behind a scan. Company QR sign-in being off is
     * checked first, so a switched-off feature never reveals which codes exist.
     *
     * @throws OtpLoginException
     */
    private function companyFrom(?string $scanned): CompanyQrCode
    {
        if (! $this->otp->qrLoginAvailable()) {
            throw OtpLoginException::unavailable();
        }

        return $this->qrCodes->resolve($scanned)
            ?? throw new OtpLoginException(__('This QR code is not valid. Ask the company for its current code.'), 404);
    }
}
