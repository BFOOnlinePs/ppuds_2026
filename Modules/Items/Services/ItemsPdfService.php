<?php

namespace Modules\Items\Services;

use Modules\Core\Services\PdfService;
use Modules\Items\Entities\Product;

/**
 * PDF Service خاص بـ Items Module
 */
class ItemsPdfService extends PdfService
{
    /**
     * Generate product barcode labels PDF
     */
    public function generateBarcodeLabels(Product $product, array $quantities = [])
    {
        $data = $this->prepareBarcodeData($product, $quantities);

        return $this->downloadPdf(
            'items::pdf.barcode-labels',
            $data,
            'barcode-labels-' . $product->slug,
            [
                'orientation' => 'Landscape',
                'format' => [216, 76]
            ]
        );
    }

    /**
     * Generate product catalog PDF
     */
    public function generateProductCatalog(Product $product)
    {
        $data = $this->prepareProductData($product);

        return $this->downloadPdf(
            'items::pdf.product-catalog',
            $data,
            'product-' . $product->slug
        );
    }

    /**
     * Generate products report PDF
     */
    public function generateProductsReport($products): \Illuminate\Http\Response
    {
        $data = [
            'products' => $products,
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'total_products' => $products->count()
        ];

        return $this->downloadPdf(
            'items::pdf.products-report',
            $data,
            'products-report-' . now()->format('Y-m-d')
        );
    }

    /**
     * Prepare barcode data for PDF
     */
    private function prepareBarcodeData(Product $product, array $quantities): array
    {
        $barcodes = [];

        // Main product barcode
        $quantity = $quantities['quantity_' . $product->id] ?? 1;
        $barcodes[] = $this->createBarcodeItem($product, $quantity);

        // Variations barcodes
        foreach ($product->variations as $variation) {
            $quantity = $quantities['quantity_' . $variation->id] ?? 1;
            $barcodes[] = $this->createBarcodeItem($variation, $quantity);
        }

        return [
            'barcodes' => $barcodes,
            'generated_at' => now()->format('Y-m-d H:i:s')
        ];
    }

    /**
     * Create barcode item data
     */
    private function createBarcodeItem(Product $product, int $quantity): array
    {
        $qrData = json_encode([
            'sku' => $product->sku,
            'name' => $product->name,
            'price' => $product->sale_price
        ]);

        return [
            'product' => $product,
            'quantity' => $quantity,
            'barcode_html' => $this->generateBarcode($product->sku),
            'qr_code_html' => $this->generateQrCode($qrData, 4)
        ];
    }

    /**
     * Prepare product data for catalog
     */
    private function prepareProductData(Product $product): array
    {
        return [
            'product' => $product,
            'categories' => $product->categories,
            'variations' => $product->variations,
            'image' => $product->getImageAttribute(),
            'barcode_html' => $this->generateBarcode($product->sku),
            'qr_code_html' => $this->generateQrCode($product->sku),
            'generated_at' => now()->format('Y-m-d H:i:s')
        ];
    }
}
