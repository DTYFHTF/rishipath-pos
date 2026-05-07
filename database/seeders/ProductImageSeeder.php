<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductImageSeeder extends Seeder
{
    private string $sourceDir = 'images/productv2';
    private string $targetDir = 'images/productv2-webp';

    private array $aliasMap = [
        'alkhalifa-dates-pkt' => 'alkhalifa-dates-premium',
        'black-mustard-seeds' => 'mustard-seeds',
        'mustard-seeds' => 'mustard-seeds',
        'yellow-mustard' => 'mustard',
        'kalonji-nigella-sativa' => 'nigella-sativa',
        'ban-silam-seeds' => 'silam',
        'mirchi-dhulo' => 'mirchi-powder',
        'whole-turmeric-pieces' => 'whole-turmeric',
        'dry-ginger-powder' => 'dry-ginger-powder',
        'fennel-seeds-sweet' => 'fennel-seeds-sweet',
        'fennel-seeds-normal' => 'fennel-seeds-normal',
        'fennel-seeds-normal-local' => 'fennel-seeds-normal-local',
        'kishmish-raisins' => 'raisins',
        'anjeer-figs' => 'anjeer',
        'dates-khajur' => 'alkhalifa-dates-premium',
        'areca-nut' => 'areca-nut',
    ];

    public function run(): void
    {
        $sourcePath = public_path($this->sourceDir);
        $targetPath = public_path($this->targetDir);

        if (! is_dir($sourcePath)) {
            $this->command->warn("ProductImageSeeder: source folder not found at {$sourcePath}");
            return;
        }

        if (! is_dir($targetPath)) {
            mkdir($targetPath, 0755, true);
        }

        $sourceFiles = $this->buildSourceIndex($sourcePath);
        if (empty($sourceFiles)) {
            $this->command->warn('ProductImageSeeder: no source images found in productv2.');
            return;
        }

        $updated = 0;
        $missing = 0;

        $lastId = 0;
        do {
            $products = Product::query()
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(200)
                ->get();

            foreach ($products as $product) {
                $lastId = $product->id;

                $sourceFile = $this->resolveSourceFile($product->name, $sourceFiles);
                if (! $sourceFile) {
                    $missing++;
                    continue;
                }

                $sourceFullPath = $sourcePath . DIRECTORY_SEPARATOR . $sourceFile;
                $targetFile = Str::slug($product->name) . '.webp';
                $targetFullPath = $targetPath . DIRECTORY_SEPARATOR . $targetFile;

                if (! $this->convertToWebp($sourceFullPath, $targetFullPath)) {
                    continue;
                }

                $relativeUrl = '/' . trim($this->targetDir, '/') . '/' . $targetFile;
                if ($product->image_url !== $relativeUrl) {
                    $product->image_url = $relativeUrl;
                    $product->save();
                    $updated++;
                }
            }
        } while ($products->count() === 200);

        $this->command->info("ProductImageSeeder: updated {$updated} products, unmatched {$missing}.");
    }

    private function buildSourceIndex(string $sourcePath): array
    {
        $index = [];
        $files = scandir($sourcePath) ?: [];

        foreach ($files as $file) {
            $full = $sourcePath . DIRECTORY_SEPARATOR . $file;
            if (! is_file($full)) {
                continue;
            }

            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if (! in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                continue;
            }

            $stem = pathinfo($file, PATHINFO_FILENAME);
            $normalized = $this->normalizeKey($stem);
            if (! isset($index[$normalized])) {
                $index[$normalized] = [];
            }
            $index[$normalized][] = $file;
        }

        return $index;
    }

    private function resolveSourceFile(string $productName, array $sourceFiles): ?string
    {
        $productKey = $this->normalizeKey($productName);

        $candidateKeys = array_filter([
            $productKey,
            $this->aliasMap[$productKey] ?? null,
        ]);

        foreach ($candidateKeys as $candidate) {
            if (isset($sourceFiles[$candidate])) {
                return $sourceFiles[$candidate][0];
            }
        }

        $bestFile = null;
        $bestScore = 0;
        $productTokens = $this->tokens($productKey);

        foreach ($sourceFiles as $sourceKey => $files) {
            $sourceTokens = $this->tokens($sourceKey);
            $common = count(array_intersect($productTokens, $sourceTokens));
            if ($common > $bestScore) {
                $bestScore = $common;
                $bestFile = $files[0];
            }
        }

        return $bestScore >= 2 ? $bestFile : null;
    }

    private function normalizeKey(string $value): string
    {
        $value = strtolower($value);
        $value = preg_replace('/\(.*?\)/', ' ', $value) ?? $value;
        $value = str_replace(['.jpg', '.jpeg', '.png', '(1)'], ' ', $value);
        $value = str_replace(['&', '/'], ' ', $value);
        $value = str_replace(['transparent', 'bag', 'photo'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', trim($value)) ?? $value;

        return Str::slug($value);
    }

    private function tokens(string $value): array
    {
        return array_values(array_filter(explode('-', $value)));
    }

    private function convertToWebp(string $source, string $target): bool
    {
        if (is_file($target) && filemtime($target) >= filemtime($source)) {
            return true;
        }

        $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));

        if ($ext === 'webp') {
            return copy($source, $target);
        }

        if (! function_exists('imagewebp')) {
            return copy($source, $target);
        }

        $image = match ($ext) {
            'jpg', 'jpeg' => @imagecreatefromjpeg($source),
            'png'         => @imagecreatefrompng($source),
            default       => null,
        };

        if (! $image) {
            return false;
        }

        // Convert palette/indexed PNGs to true-color RGBA (required for imagewebp)
        if (imagecolorstotal($image) > 0 || imageistruecolor($image) === false) {
            $w = imagesx($image);
            $h = imagesy($image);
            $trueColor = imagecreatetruecolor($w, $h);
            imagealphablending($trueColor, false);
            imagesavealpha($trueColor, true);
            $transparent = imagecolorallocatealpha($trueColor, 0, 0, 0, 127);
            imagefill($trueColor, 0, 0, $transparent);
            imagecopy($trueColor, $image, 0, 0, 0, 0, $w, $h);
            imagedestroy($image);
            $image = $trueColor;
        }

        $result = imagewebp($image, $target, 82);
        imagedestroy($image);

        return (bool) $result;
    }
}
