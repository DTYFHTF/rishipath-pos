<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductImageSeeder extends Seeder
{
    /**
     * Maps product name → image filename (stored in /images/products/{slug}.webp)
     * Only products that have an image from the Capcut folder are listed here.
     */
    private array $imageMap = [
        'Turmeric Powder'               => 'turmeric-powder.webp',
        'Cumin Seeds'                   => 'cumin-seeds.webp',
        'Coriander Seeds'               => 'coriander-seeds.webp',
        'Star Anise'                    => 'star-anise.webp',
        'Black Pepper (Whole)'          => 'black-pepper-whole.webp',
        'White Pepper'                  => 'white-pepper.webp',
        'Sichuan Pepper (Timur)'        => 'sichuan-pepper-timur.webp',
        'Mustard (Yellow)'              => 'mustard-yellow.webp',
        'Fenugreek Seeds'               => 'fenugreek-seeds.webp',
        'Sesame Seeds (White)'          => 'sesame-seeds-white.webp',
        'Black Sesame Seeds'            => 'black-sesame-seeds.webp',
        'Garden Cress Seeds (Halim)'    => 'garden-cress-seeds-halim.webp',
        'Ajwain (Carom Seeds)'          => 'ajwain-carom-seeds.webp',
        'Silam Seeds'                   => 'silam-seeds.webp',
        'Cinnamon (Dalchini)'           => 'cinnamon-dalchini.webp',
        'Green Cardamom (Elaichi)'      => 'green-cardamom-elaichi.webp',
        'Black Cardamom (Badi Elaichi)' => 'black-cardamom-badi-elaichi.webp',
        'Mace (Javitri)'                => 'mace-javitri.webp',
        'Cloves (Lwang)'                => 'cloves-lwang.webp',
        'Areca Nut (Supari)'            => 'areca-nut-supari.webp',
        'Desiccated Coconut'            => 'desiccated-coconut.webp',
    ];

    public function run(): void
    {
        $updated = 0;

        foreach ($this->imageMap as $productName => $imageFile) {
            $count = Product::where('name', $productName)
                ->update(['image_url' => '/images/products/' . $imageFile]);

            if ($count > 0) {
                $updated++;
                $this->command->line("  ✅ {$productName}");
            } else {
                $this->command->warn("  ⚠️  Not found: {$productName}");
            }
        }

        $this->command->info("ProductImageSeeder: updated {$updated} products.");
    }
}
