<?php

namespace Modules\PPUDS\Services;

use Illuminate\Support\Facades\DB;
use Milon\Barcode\QRcode;
use Modules\Core\Entities\User;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyQrCode;
use Modules\PPUDS\Enums\CompanyStatus;
use Modules\PPUDS\Settings\AuthenticationSettings;
use RuntimeException;

/**
 * Company sign-in QR codes: generating, revoking, resolving and drawing them.
 *
 * The QR holds a link with 256 random bits in it. That only names the
 * company — it is not a credential, and the person scanning it still proves
 * who they are with a one-time code. The token is looked up by its SHA-256
 * hash, so it cannot be guessed or forged from a company id, and a company
 * has at most one working code: generating a new one revokes the old one.
 */
class CompanyQrCodeService
{
    /** White border, in modules, that scanners need around the code. */
    private const QUIET_ZONE = 4;

    public function activeFor(Company $company): ?CompanyQrCode
    {
        return CompanyQrCode::query()
            ->where('company_id', $company->getKey())
            ->active()
            ->latest('id')
            ->first();
    }

    /** Issues a new code for the company, revoking the one in use. */
    public function generate(Company $company, ?User $by = null): CompanyQrCode
    {
        $validityDays = app(AuthenticationSettings::class)->qr_code_validity_days;
        $token = $this->newToken();

        return DB::transaction(function () use ($company, $by, $validityDays, $token): CompanyQrCode {
            CompanyQrCode::query()
                ->where('company_id', $company->getKey())
                ->whereNull('revoked_at')
                ->lockForUpdate()
                ->get()
                ->each(fn (CompanyQrCode $code) => $this->markRevoked($code, $by));

            return CompanyQrCode::create([
                'company_id' => $company->getKey(),
                'token_hash' => $this->hash($token),
                'token' => $token,
                'expires_at' => $validityDays > 0 ? now()->addDays($validityDays) : null,
                'created_by' => $by?->getKey(),
            ]);
        });
    }

    public function revoke(CompanyQrCode $code, ?User $by = null): void
    {
        if ($code->revoked_at === null) {
            $this->markRevoked($code, $by);
        }
    }

    /**
     * The working code a scan points at, or null.
     *
     * Accepts either the bare token or the whole scanned link, so the app
     * can pass on whatever the camera read. The company must still exist and
     * be active — a deactivated company's QR stops working at once.
     */
    public function resolve(?string $scanned): ?CompanyQrCode
    {
        $token = $this->extractToken($scanned);

        if ($token === null) {
            return null;
        }

        $code = CompanyQrCode::query()
            ->where('token_hash', $this->hash($token))
            ->active()
            ->with('company')
            ->first();

        if (! $code || ! $code->company || $code->company->status !== CompanyStatus::ACTIVE) {
            return null;
        }

        return $code;
    }

    /** Counts a successful sign-in made through the code. */
    public function recordUse(CompanyQrCode $code): void
    {
        CompanyQrCode::query()->whereKey($code->getKey())->update([
            'use_count' => DB::raw('use_count + 1'),
            'last_used_at' => now(),
        ]);
    }

    /** What the QR encodes: a link the app reads, which also opens a help page in a browser. */
    public function url(CompanyQrCode $code): string
    {
        return route('company-qr.landing', ['token' => $code->token]);
    }

    public function svg(CompanyQrCode $code, int $moduleSize = 8): string
    {
        $matrix = $this->matrix($this->url($code));
        $size = ($matrix['num_cols'] + self::QUIET_ZONE * 2) * $moduleSize;

        $rects = '';
        foreach ($matrix['bcode'] as $row => $cells) {
            foreach ($cells as $col => $cell) {
                if ((int) $cell === 1) {
                    $x = ($col + self::QUIET_ZONE) * $moduleSize;
                    $y = ($row + self::QUIET_ZONE) * $moduleSize;
                    $rects .= "<rect x=\"{$x}\" y=\"{$y}\" width=\"{$moduleSize}\" height=\"{$moduleSize}\"/>";
                }
            }
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'
            ."<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$size}\" height=\"{$size}\" viewBox=\"0 0 {$size} {$size}\" shape-rendering=\"crispEdges\">"
            .'<rect width="100%" height="100%" fill="#ffffff"/>'
            ."<g fill=\"#000000\">{$rects}</g></svg>";
    }

    public function png(CompanyQrCode $code, int $moduleSize = 12): string
    {
        if (! function_exists('imagecreate')) {
            throw new RuntimeException('The GD extension is required to export PNG images.');
        }

        $matrix = $this->matrix($this->url($code));
        $size = ($matrix['num_cols'] + self::QUIET_ZONE * 2) * $moduleSize;

        $image = imagecreate($size, $size);
        $white = imagecolorallocate($image, 255, 255, 255);
        $black = imagecolorallocate($image, 0, 0, 0);
        imagefill($image, 0, 0, $white);

        foreach ($matrix['bcode'] as $row => $cells) {
            foreach ($cells as $col => $cell) {
                if ((int) $cell === 1) {
                    $x = ($col + self::QUIET_ZONE) * $moduleSize;
                    $y = ($row + self::QUIET_ZONE) * $moduleSize;
                    imagefilledrectangle($image, $x, $y, $x + $moduleSize - 1, $y + $moduleSize - 1, $black);
                }
            }
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    public function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    protected function markRevoked(CompanyQrCode $code, ?User $by): void
    {
        $code->forceFill([
            'revoked_at' => now(),
            'revoked_by' => $by?->getKey(),
        ])->save();
    }

    /** 32 random bytes, URL-safe base64: 43 characters, 256 bits. */
    protected function newToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
    }

    protected function extractToken(?string $scanned): ?string
    {
        $scanned = trim((string) $scanned);

        if ($scanned === '') {
            return null;
        }

        if (str_contains($scanned, '/')) {
            $path = (string) (parse_url($scanned, PHP_URL_PATH) ?? '');
            $scanned = basename(rtrim($path, '/'));
        }

        return preg_match('/^[A-Za-z0-9_-]{32,128}$/', $scanned) ? $scanned : null;
    }

    /** @return array{num_rows: int, num_cols: int, bcode: array<int, array<int, int>>} */
    protected function matrix(string $content): array
    {
        // Error correction H: the code still scans when a printed copy is
        // creased or partly covered.
        $matrix = (new QRcode($content, 'H'))->getBarcodeArray();

        if (! is_array($matrix) || empty($matrix['bcode'])) {
            throw new RuntimeException('The QR code could not be generated.');
        }

        return $matrix;
    }
}
