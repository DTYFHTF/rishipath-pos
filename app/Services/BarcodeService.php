<?php

namespace App\Services;

use App\Models\ProductVariant;
use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorHTML;
use Picqer\Barcode\BarcodeGeneratorSVG;

class BarcodeService
{
    /**
     * Supported barcode types
     */
    const TYPE_CODE128 = 'C128';
    const TYPE_EAN13 = 'EAN13';
    const TYPE_CODE39 = 'C39';
    const TYPE_QR = 'QR';

    /**
     * Generate barcode for product variant
     */
    public function generateBarcodeForVariant(ProductVariant $variant): string
    {
        // If variant already has barcode, return it
        if ($variant->barcode) {
            return $variant->barcode;
        }

        // Generate new barcode using pattern: RSH-{variant_id}-{random}
        $barcode = 'RSH' . str_pad($variant->id, 6, '0', STR_PAD_LEFT) . mt_rand(100, 999);
        
        // Update variant with new barcode
        $variant->update(['barcode' => $barcode]);
        
        return $barcode;
    }

    /**
     * Generate barcode image as PNG
     */
    public function generateBarcodeImage(string $code, string $type = self::TYPE_CODE128, int $widthFactor = 2, int $height = 50): string
    {
        $generator = new BarcodeGeneratorPNG();
        
        $barcodeType = $this->getBarcodeType($type);
        
        return base64_encode($generator->getBarcode($code, $barcodeType, $widthFactor, $height));
    }

    /**
     * Generate barcode as HTML
     */
    public function generateBarcodeHTML(string $code, string $type = self::TYPE_CODE128): string
    {
        $generator = new BarcodeGeneratorHTML();
        
        $barcodeType = $this->getBarcodeType($type);
        
        return $generator->getBarcode($code, $barcodeType);
    }

    /**
     * Generate barcode as SVG
     */
    public function generateBarcodeSVG(string $code, string $type = self::TYPE_CODE128, int $widthFactor = 2, int $height = 50): string
    {
        $generator = new BarcodeGeneratorSVG();
        
        $barcodeType = $this->getBarcodeType($type);
        
        return $generator->getBarcode($code, $barcodeType, $widthFactor, $height);
    }

    /**
     * Find product variant by barcode
     */
    public function findVariantByBarcode(string $barcode): ?ProductVariant
    {
        return ProductVariant::where('barcode', $barcode)->first();
    }

    /**
     * Validate barcode format
     */
    public function validateBarcode(string $barcode): bool
    {
        // Basic validation: barcode should be alphanumeric and not empty
        return !empty($barcode) && preg_match('/^[A-Za-z0-9\-]+$/', $barcode);
    }

    /**
     * Generate barcode label data for printing
     */
    public function generateLabelData(ProductVariant $variant): array
    {
        // Ensure variant has a barcode
        $barcode = $variant->barcode ?? $this->generateBarcodeForVariant($variant);
        
        // Generate barcode image
        $barcodeImage = $this->generateBarcodeImage($barcode);
        
        return [
            'barcode' => $barcode,
            'barcode_image' => $barcodeImage,
            'product_name' => $variant->product->name,
            'variant_name' => $variant->pack_size . ' ' . $variant->unit,
            'mrp' => $variant->mrp_india,
            'sku' => $variant->sku,
            'product_id' => $variant->product_id,
            'variant_id' => $variant->id,
        ];
    }

    /**
     * Generate multiple labels for bulk printing
     */
    public function generateBulkLabels(array $variantIds, int $copiesPerVariant = 1): array
    {
        $labels = [];
        
        foreach ($variantIds as $variantId) {
            $variant = ProductVariant::find($variantId);
            
            if ($variant) {
                $labelData = $this->generateLabelData($variant);
                
                // Add multiple copies if requested
                for ($i = 0; $i < $copiesPerVariant; $i++) {
                    $labels[] = $labelData;
                }
            }
        }
        
        return $labels;
    }

    /**
     * Check if barcode is valid EAN-13 format
     */
    public function isValidEAN13(string $barcode): bool
    {
        // EAN-13 must be exactly 13 digits
        if (!preg_match('/^\d{13}$/', $barcode)) {
            return false;
        }

        // Calculate checksum
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int)$barcode[$i] * (($i % 2 === 0) ? 1 : 3);
        }
        
        $checksum = (10 - ($sum % 10)) % 10;
        
        return (int)$barcode[12] === $checksum;
    }

    /**
     * Parse scanned barcode input
     * Scanner typically sends barcode followed by Enter key
     */
    public function parseScannedInput(string $input): ?string
    {
        // Remove whitespace and newlines
        $barcode = trim($input);
        
        // Validate format
        if ($this->validateBarcode($barcode)) {
            return $barcode;
        }
        
        return null;
    }

    /**
     * Get barcode type constant for generator
     */
    private function getBarcodeType(string $type): string
    {
        return match($type) {
            self::TYPE_CODE128 => BarcodeGeneratorPNG::TYPE_CODE_128,
            self::TYPE_EAN13 => BarcodeGeneratorPNG::TYPE_EAN_13,
            self::TYPE_CODE39 => BarcodeGeneratorPNG::TYPE_CODE_39,
            default => BarcodeGeneratorPNG::TYPE_CODE_128,
        };
    }

    /**
     * Generate batch of barcodes for new products
     */
    public function generateBatchBarcodes(array $variantIds): array
    {
        $results = [];
        
        foreach ($variantIds as $variantId) {
            $variant = ProductVariant::find($variantId);
            
            if ($variant && !$variant->barcode) {
                $barcode = $this->generateBarcodeForVariant($variant);
                $results[$variantId] = [
                    'success' => true,
                    'barcode' => $barcode,
                    'variant' => $variant->product->name . ' - ' . $variant->pack_size . $variant->unit,
                ];
            } else {
                $results[$variantId] = [
                    'success' => false,
                    'message' => $variant ? 'Barcode already exists' : 'Variant not found',
                ];
            }
        }
        
        return $results;
    }

    /**
     * Get barcode statistics
     */
    public function getBarcodeStats(): array
    {
        $totalVariants = ProductVariant::count();
        $withBarcode = ProductVariant::whereNotNull('barcode')->count();
        $withoutBarcode = $totalVariants - $withBarcode;
        
        return [
            'total_variants' => $totalVariants,
            'with_barcode' => $withBarcode,
            'without_barcode' => $withoutBarcode,
            'percentage' => $totalVariants > 0 ? round(($withBarcode / $totalVariants) * 100, 2) : 0,
        ];
    }
}
