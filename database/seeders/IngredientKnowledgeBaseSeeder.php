<?php

namespace Database\Seeders;

use App\Models\Ingredient;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

/**
 * Seeds the Ingredient Knowledge Base (IKB) from the internal master workbook
 * "Shuddhidham_Ingredient_Knowledge_Base.xlsx" (exported to
 * database/data/ingredient_knowledge_base.json).
 *
 * Idempotent: updateOrCreate keyed by (organization_id, code). Re-running
 * refreshes knowledge fields but never touches manually linked product_id
 * once set.
 */
class IngredientKnowledgeBaseSeeder extends Seeder
{
    public function run(): void
    {
        $org = Organization::where('slug', 'rishipath')->firstOrFail();

        $path = database_path('data/ingredient_knowledge_base.json');
        $entries = json_decode(File::get($path), true);

        $created = 0;
        $updated = 0;
        $linked = 0;

        foreach ($entries as $entry) {
            unset($entry['hero_note']);

            $ingredient = Ingredient::updateOrCreate(
                ['organization_id' => $org->id, 'code' => $entry['code']],
                $entry + ['active' => true]
            );

            $ingredient->wasRecentlyCreated ? $created++ : $updated++;

            // Best-effort link to the POS product carrying live pricing.
            if (! $ingredient->product_id) {
                $productId = $this->matchProduct($org->id, $ingredient);
                if ($productId) {
                    $ingredient->update(['product_id' => $productId]);
                    $linked++;
                }
            }
        }

        $this->command->info("✅ Ingredient KB: {$created} created, {$updated} updated, {$linked} linked to POS products.");
    }

    /**
     * Match an ingredient to a POS product by name (exact first, then prefix).
     */
    private function matchProduct(int $orgId, Ingredient $ingredient): ?int
    {
        $base = Product::where('organization_id', $orgId)->where('active', true);

        // e.g. "Cumin" → "Cumin Seeds"; "Sichuan Pepper (Timur)" → strip the parenthesis
        $plain = trim(preg_replace('/\s*\(.*\)$/', '', $ingredient->name));

        foreach ([$ingredient->name, $plain] as $name) {
            if ($name === '') {
                continue;
            }
            $id = (clone $base)->where('name', $name)->value('id')
                ?? (clone $base)->where('name', 'like', $name.'%')->orderBy('id')->value('id');
            if ($id) {
                return $id;
            }
        }

        return null;
    }
}
