<?php

namespace Modules\PPUDS\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Str;
use Modules\Core\Entities\AppVersion;
use Modules\Core\Services\PdfService;
use Modules\PPUDS\Entities\Company;
use Modules\PPUDS\Entities\CompanyQrCode;
use Modules\PPUDS\Services\CompanyQrCodeService;

/**
 * Files for a company's sign-in QR code, plus the page its link opens in a
 * browser. Generating and revoking happen on the company details page.
 */
class CompanyQrCodeController extends Controller
{
    public function __construct(private readonly CompanyQrCodeService $qrCodes) {}

    /**
     * Someone scanned the code with the phone camera instead of the app.
     * The page only points them to the app; nothing here signs anyone in.
     */
    public function landing(string $token)
    {
        $code = $this->qrCodes->resolve($token);

        return view('ppuds::company-qr.landing', [
            'company' => $code?->company,
            'storeLinks' => AppVersion::query()
                ->whereNotNull('store_url')
                ->pluck('store_url', 'platform')
                ->all(),
        ]);
    }

    public function download(Company $company, string $format)
    {
        $code = $this->activeCode($company);
        $filename = $this->filename($company, $format);

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

    /** A printable sheet to hang at the company: name, QR and instructions. */
    public function print(Company $company, PdfService $pdf)
    {
        $code = $this->activeCode($company);

        return $pdf->streamPdf('ppuds::pdf.company-qr.poster', [
            'company' => $company,
            'qrImage' => 'data:image/png;base64,'.base64_encode($this->qrCodes->png($code, 10)),
            'expiresAt' => $code->expires_at,
        ], $this->filename($company, 'pdf'));
    }

    private function activeCode(Company $company): CompanyQrCode
    {
        return $this->qrCodes->activeFor($company) ?? abort(404);
    }

    private function filename(Company $company, string $extension): string
    {
        $slug = Str::slug((string) $company->name) ?: 'company-'.$company->getKey();

        return "{$slug}-sign-in-qr.{$extension}";
    }
}
