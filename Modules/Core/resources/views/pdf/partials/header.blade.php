{{-- resources/views/pdf/partials/header.blade.php --}}
<htmlpageheader name="docHeader" dir="rtl">
    @php
        use Illuminate\Support\Str;

        $rawLogo = $settingsModel->getFirstMediaPath('logo') ?? null;

        $normalizeImg = function (?string $src): ?string {
            if (empty($src)) return null;

            // base64
            if (Str::startsWith($src, 'data:image')) {
                return $src;
            }

            if (Str::startsWith($src, ['http://','https://'])) {
                return $src;
            }

            if (Str::startsWith($src, ['/','C:\\','D:\\','E:\\'])) {
                return 'file://' . $src;
            }

            return 'file://' . public_path($src);
        };

        $logoSrc = $normalizeImg($rawLogo);

        $siteName   = $settings->site_name ?? 'اسم الشركة';
        $tagline    = $company['tagline']    ?? null;
        $qr         = $company['qr']         ?? null;      // base64 أو URL
        $shortCode  = $company['short_code'] ?? null;

        // 3) معلومات التواصل
        $address = $company['address'] ?? null;
        $phone   = $company['phone']   ?? null;
        $mobile  = $company['mobile']  ?? null;
        $email   = $company['email']   ?? null;
        $website = $company['website'] ?? null;
    @endphp

    <table width="100%" style="border-collapse: collapse; border: none">
        <tr>
            {{-- الشعار (يمين) --}}
            <td style="width: 18%; text-align: right; vertical-align: middle;">
                @if($logoSrc)
                    <img src="{{ $logoSrc }}" alt="Logo" style="height: 26mm; object-fit: contain;">
                @endif
            </td>

            {{-- الاسم والسطر التعريفي (وسط) --}}
            <td style="width: 64%; text-align: center; vertical-align: middle; padding: 0 6mm;">
                <div style="font-weight: bold; font-size: 15pt; line-height: 1.2;">
                    {{ $siteName }}
                </div>
                @if($tagline)
                    <div style="font-size: 9pt; color:#555; margin-top: 2mm;">
                        {{ $tagline }}
                    </div>
                @endif
            </td>

            {{-- QR أو باركود (يسار) --}}
            <td style="width: 18%; text-align: left; vertical-align: middle;">
                    <div style="display: inline-block;">
                        {!! app(\Modules\Core\Services\PdfService::class)->generateBarcodeForPdf($shortCode) !!}
                    </div>
            </td>
        </tr>
    </table>

    {{-- فاصل علوي --}}
    <div style="margin-top: 3mm; border-bottom: 1.2pt solid #0c456f;"></div>

    {{-- سطر معلومات التواصل (اختياري) --}}
    @if($address || $phone || $mobile || $email || $website)
        <table width="100%" style="border-collapse: collapse; margin-top: 2.5mm; font-size: 9pt; color:#333; direction: rtl;">
            <tr>
                <td style="text-align: right; white-space: nowrap;">
                    @if($address) {{ $address }} @endif
                </td>
                <td style="text-align: center; white-space: nowrap;">
                    @if($phone) {{ $phone }} @endif
                    @if($phone && $mobile) | @endif
                    @if($mobile) {{ $mobile }} @endif
                </td>
                <td style="text-align: left; white-space: nowrap; direction: ltr;">
                    @if($email) {{ $email }} @endif
                    @if($email && $website) | @endif
                    @if($website) {{ $website }} @endif
                </td>
            </tr>
        </table>
    @endif
</htmlpageheader>
