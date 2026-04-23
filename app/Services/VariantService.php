<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class VariantService
{

    public function __construct(
        protected SkuGeneratorService $skuService,
        protected ImageService $imageService
    ) {}

    public function sync(Product $product, array $variantsData): void
    {
        $incomingVariants = collect($variantsData);
        $currentVariantIds = $product->variants->pluck('id');
        $incomingVariantIds = $incomingVariants->pluck('id')->whereNotNull();

        $variantsToDeleteIds = $currentVariantIds->diff($incomingVariantIds);
        if ($variantsToDeleteIds->isNotEmpty()) {

            $this->deleteByIds($variantsToDeleteIds->all());
        }

        foreach ($incomingVariants as $variantData) {

            if (empty($variantData['id']) && empty($variantData['sku'])) {
                $variantData['sku'] = $this->skuService->generate($product, $variantData['attribute_value_ids']);
            }
            // variant
            $variant = $product->variants()->updateOrCreate(
                ['id' => $variantData['id'] ?? null],
                Arr::except($variantData, ['id', 'attribute_value_ids'])
            );
            $variant->attributeValues()->sync($variantData['attribute_value_ids']);
        }
    }

    protected function deleteByIds(array $variantIds): void
    {
        $variantsToDelete = ProductVariant::with('images')->whereIn('id', $variantIds)->get();

        foreach ($variantsToDelete as $variant) {
            $this->imageService->deleteAllForVariant($variant);
        }
        ProductVariant::destroy($variantIds);
    }
}
