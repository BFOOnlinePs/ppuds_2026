<?php

namespace Modules\Core\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Config;
use Milon\Barcode\DNS1D;
use Milon\Barcode\DNS2D;
use PDF;

class PdfService
{
    protected DNS1D $barcode;
    protected DNS2D $qrCode;

    public function __construct()
    {
        $this->barcode = new DNS1D();
        $this->qrCode = new DNS2D();
    }

    public function generateBarcode(?string $code, string $type = 'C128', int $width = 4, int $height = 50): string
    {
        if (empty($code) || !is_string($code)) {
            $code = $this->generateDefaultBarcode();
        }

        $code = $this->sanitizeBarcode($code);

        try {
            return $this->barcode->getBarcodeHTML($code, $type, $width, $height);
        } catch (\Exception $e) {
            return $this->barcode->getBarcodeHTML($this->generateDefaultBarcode(), $type, $width, $height);
        }
    }

    public function generateBarcodeSvg(?string $code, string $type = 'C128', int $width = 2, int $height = 50): string
    {
        if (empty($code) || !is_string($code)) {
            $code = $this->generateDefaultBarcode();
        }

        $code = $this->sanitizeBarcode($code);

        try {
            return $this->barcode->getBarcodeSVG($code, $type, $width, $height);
        } catch (\Exception $e) {
            return $this->barcode->getBarcodeSVG($this->generateDefaultBarcode(), $type, $width, $height);
        }
    }

    public function generateBarcodePng(?string $code, string $type = 'C128', int $width = 2, int $height = 50): string
    {
        if (empty($code) || !is_string($code)) {
            $code = $this->generateDefaultBarcode();
        }

        $code = $this->sanitizeBarcode($code);

        try {
            $barcodeData = $this->barcode->getBarcodePNG($code, $type, $width, $height);
            return 'data:image/png;base64,' . base64_encode($barcodeData);
        } catch (\Exception $e) {
            $barcodeData = $this->barcode->getBarcodePNG($this->generateDefaultBarcode(), $type, $width, $height);
            return 'data:image/png;base64,' . base64_encode($barcodeData);
        }
    }

    public function generateBarcodeForPdf(?string $code, string $type = 'C128', int $width = 2, int $height = 50): string
    {
        // استخدام SVG أولاً لأنه يعطي جودة أفضل
        $svg = $this->generateBarcodeSvg($code, $type, $width, $height);

        if (!empty($svg)) {
            return '<div style="display: inline-block;">' . $svg . '</div>';
        }

        // إذا فشل SVG، استخدم PNG
        $png = $this->generateBarcodePng($code, $type, $width, $height);
        return '<img src="' . $png . '" style="height: ' . $height . 'px;" />';
    }

    private function sanitizeBarcode(string $code): string
    {
        // إزالة المسافات والأحرف غير المرغوب فيها
        $code = preg_replace('/[^A-Za-z0-9\-]/', '', $code);

        // التأكد من وجود محتوى
        if (empty($code)) {
            return $this->generateDefaultBarcode();
        }

        return $code;
    }

    private function generateDefaultBarcode(): string
    {
        return 'SKU' . str_pad(mt_rand(1, 99999999), 8, '0', STR_PAD_LEFT);
    }

    public function generateQrCode(string $data, int $size = 5): string
    {
        return $this->qrCode->getBarcodeHTML($data, 'QRCODE', $size, $size);
    }

    public function generatePdf(string $view, array $data, string $filename = 'file.pdf', array $options = [])
    {
        $pdfContent = PDF::loadView($view, $data, [], $options)->output();

        return response()->streamDownload(function () use ($pdfContent) {

            if (ob_get_length()) {
                ob_end_clean();
            }

            echo $pdfContent;

        }, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }


    public function downloadPdf(string $view, array $data, string $filename = 'file.pdf', array $config = [])
    {
//        return PDF::loadView($view, $data, [], $config)->download($filename);
        return $this->generatePdf($view, $data, $filename, $config);
    }

    public function getPdfContent(string $view, array $data, array $config = []): string
    {
        return PDF::loadView($view, $data, [], $config)->output();
    }

    /**
     * Stream PDF
     */
    public function streamPdf(string $view, array $data, string $filename = 'file.pdf', array $config = [])
    {
        return PDF::loadView($view, $data, [], $config)->stream($filename);
    }

    public function generateDirectPdf(string $view, array $data, string $filename, array $customPaperSize, string $orientation = 'P')
    {
        $config = [
            'format' => $customPaperSize,
            'orientation' => $orientation,
            'margin_left' => 2,
            'margin_right' => 2,
            'margin_top' => 2,
            'margin_bottom' => 0,
            'font_data' => [
                'tajawal' => []
            ]
        ];

        return PDF::loadView($view, $data, [], $config)->stream($filename);
    }
}
