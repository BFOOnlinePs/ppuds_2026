<?php

namespace Modules\PPUDS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Modules\Core\Entities\AppVersion;
use Modules\Core\Entities\User;
use Modules\Core\Services\PdfService;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyQrCode;
use Modules\PPUDS\Services\CompanyQrCodeService;

/**
 * Files for sign-in QR codes — the company's printed one and each person's
 * own — plus the page a code's link opens in a browser. Generating and
 * revoking happen on the company details page.
 */
class CompanyQrCodeController extends Controller
{
    public function __construct(private readonly CompanyQrCodeService $qrCodes) {}

    /**
     * Someone scanned the code with the phone camera instead of the app.
     * The page only points them to the app; nothing here signs anyone in.
     * A personal code's page never names its holder.
     */
    public function landing(string $token)
    {
        $code = $this->qrCodes->resolve($token);

        return view('ppuds::company-qr.landing', [
            'company' => $code && ! $code->isPersonal() ? $code->company : null,
            'personal' => (bool) $code?->isPersonal(),
            'storeLinks' => AppVersion::query()
                ->whereNotNull('store_url')
                ->pluck('store_url', 'platform')
                ->all(),
        ]);
    }

    public function download(Company $company, string $format)
    {
        return $this->image($this->activeCode($company), $format, $this->filename($company, $format));
    }

    /** A printable sheet to hang at the company: name, QR and instructions. */
    public function print(Company $company, PdfService $pdf)
    {
        $code = $this->activeCode($company);

        return $pdf->streamPdf('ppuds::pdf.company-qr.poster', [
            'company' => $company,
            'holder' => null,
            'qrImage' => 'data:image/png;base64,'.base64_encode($this->qrCodes->png($code, 10)),
            'expiresAt' => $code->expires_at,
        ], $this->filename($company, 'pdf'));
    }

    public function downloadPersonal(Company $company, User $user, string $format)
    {
        return $this->image($this->activePersonalCode($company, $user), $format, $this->filename($company, $format, $user));
    }

    /** A card to hand to the person privately: their name, QR and a warning. */
    public function printPersonal(Company $company, User $user, PdfService $pdf)
    {
        $code = $this->activePersonalCode($company, $user);

        return $pdf->streamPdf('ppuds::pdf.company-qr.poster', [
            'company' => $company,
            'holder' => $user,
            'qrImage' => 'data:image/png;base64,'.base64_encode($this->qrCodes->png($code, 10)),
            'expiresAt' => $code->expires_at,
        ], $this->filename($company, 'pdf', $user));
    }

    private function image(CompanyQrCode $code, string $format, string $filename)
    {
        return match ($format) {
            'svg' => response($this->qrCodes->svg($code), 200, [
                'Content-Type' => 'image/svg+xml',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]),
            'png' => response($this->qrCodes->png($code), 200, [
                'Content-Type' => 'image/png',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]),
            default => abort(404),
        };
    }

    private function activeCode(Company $company): CompanyQrCode
    {
        return $this->qrCodes->activeFor($company) ?? abort(404);
    }

    private function activePersonalCode(Company $company, User $user): CompanyQrCode
    {
        return $this->qrCodes->activePersonalFor($company, $user) ?? abort(404);
    }

    private function filename(Company $company, string $extension, ?User $holder = null): string
    {
        $slug = Str::slug((string) $company->name) ?: 'company-'.$company->getKey();

        if ($holder) {
            $slug .= '-'.(Str::slug((string) ($holder->name_en ?: $holder->name)) ?: 'user-'.$holder->getKey());
        }

        return "{$slug}-sign-in-qr.{$extension}";
    }
}
