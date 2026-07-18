<?php

namespace Tests\Feature;

use App\Enums\Ask;
use App\Enums\Status;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeOption;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductSection;
use App\Models\ProductSectionProduct;
use App\Models\ProductTag;
use App\Models\ProductTax;
use App\Models\ProductVariation;
use App\Models\Slider;
use App\Models\Tax;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\Unit;
use App\Http\Requests\ProductRequest;
use App\Services\Saas\TenantProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantDemoContentProvisioningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saas.marketing_host' => 'company.com',
            'saas.owner_host' => 'owner.company.com',
            'saas.merchant_host' => 'merchant.company.com',
            'saas.fallback_subdomain_suffix' => 'company.com',
            'media-library.queue_conversions_by_default' => false,
        ]);
    }

    public function test_owner_demo_storefront_content_is_copied_into_each_tenant_only_once(): void
    {
        Storage::fake('public');

        $alpha = $this->createTenant('alpha-demo');
        $beta = $this->createTenant('beta-demo');
        $source = $this->createOwnerDemoCatalog();

        /** @var TenantProvisioningService $service */
        $service = app(TenantProvisioningService::class);

        $stats = $service->seedStorefrontDefaultsForTenants('alpha-demo');

        $this->assertSame(1, $stats['tenants']);
        $this->assertGreaterThan(0, $stats['seeded_records']);

        $alphaProduct = Product::withoutGlobalScopes()
            ->where('tenant_id', $alpha->id)
            ->where('sku', $source['product']->sku)
            ->firstOrFail();
        $alphaSlider = Slider::withoutGlobalScopes()
            ->where('tenant_id', $alpha->id)
            ->where('title', $source['slider']->title)
            ->firstOrFail();
        $alphaCategory = ProductCategory::withoutGlobalScopes()
            ->where('tenant_id', $alpha->id)
            ->where('slug', $source['category']->slug)
            ->firstOrFail();
        $alphaSection = ProductSection::withoutGlobalScopes()
            ->where('tenant_id', $alpha->id)
            ->where('slug', $source['section']->slug)
            ->firstOrFail();

        $this->assertNotSame($source['product']->id, $alphaProduct->id);
        $this->assertSame($alphaCategory->id, $alphaProduct->product_category_id);
        $this->assertSame($alpha->id, $alphaProduct->tenant_id);
        $this->assertSame($alpha->id, $alphaSlider->tenant_id);
        $this->assertCount(1, $alphaProduct->getMedia('product'));
        $this->assertCount(1, $alphaSlider->getMedia('slider'));

        $this->assertDatabaseHas('product_tags', [
            'product_id' => $alphaProduct->id,
            'name' => 'demo',
        ]);
        $this->assertDatabaseHas('product_taxes', [
            'tenant_id' => $alpha->id,
            'product_id' => $alphaProduct->id,
        ]);
        $this->assertDatabaseHas('product_section_products', [
            'tenant_id' => $alpha->id,
            'product_section_id' => $alphaSection->id,
            'product_id' => $alphaProduct->id,
        ]);
        $this->assertDatabaseHas('product_variations', [
            'tenant_id' => $alpha->id,
            'product_id' => $alphaProduct->id,
            'sku' => '8800002',
        ]);
        $this->assertDatabaseHas('tenant_demo_content_seeds', [
            'tenant_id' => $alpha->id,
            'source_type' => Product::class,
            'source_id' => $source['product']->id,
            'target_type' => Product::class,
            'target_id' => $alphaProduct->id,
        ]);

        $this->assertDatabaseMissing('products', [
            'tenant_id' => $beta->id,
            'sku' => $source['product']->sku,
        ]);

        $service->seedStorefrontDefaultsForTenants();

        $this->assertSame(1, Product::withoutGlobalScopes()->where('tenant_id', $alpha->id)->where('sku', $source['product']->sku)->count());
        $this->assertSame(1, Product::withoutGlobalScopes()->where('tenant_id', $beta->id)->where('sku', $source['product']->sku)->count());
    }

    public function test_tenant_demo_copy_can_be_deleted_without_touching_owner_template_or_reappearing(): void
    {
        Storage::fake('public');

        $tenant = $this->createTenant('delete-demo');
        $source = $this->createOwnerDemoCatalog();

        /** @var TenantProvisioningService $service */
        $service = app(TenantProvisioningService::class);
        $service->seedStorefrontDefaultsForTenants('delete-demo');

        $tenantProduct = Product::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('sku', $source['product']->sku)
            ->firstOrFail();

        $tenantProduct->delete();
        $service->seedStorefrontDefaultsForTenants('delete-demo');

        $this->assertSoftDeleted('products', ['id' => $tenantProduct->id]);
        $this->assertSame(0, Product::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('sku', $source['product']->sku)
            ->whereNull('deleted_at')
            ->count());
        $this->assertDatabaseHas('products', [
            'id' => $source['product']->id,
            'tenant_id' => null,
            'deleted_at' => null,
        ]);
        $this->assertDatabaseHas('tenant_demo_content_seeds', [
            'tenant_id' => $tenant->id,
            'source_type' => Product::class,
            'source_id' => $source['product']->id,
            'target_id' => $tenantProduct->id,
        ]);
    }

    public function test_seeded_demo_catalog_is_visible_only_on_the_matching_storefront_host(): void
    {
        Storage::fake('public');

        $alpha = $this->createTenant('alpha-storefront');
        $beta = $this->createTenant('beta-storefront');
        $source = $this->createOwnerDemoCatalog();

        /** @var TenantProvisioningService $service */
        $service = app(TenantProvisioningService::class);
        $service->seedStorefrontDefaultsForTenants();

        $alphaProduct = Product::withoutGlobalScopes()
            ->where('tenant_id', $alpha->id)
            ->where('sku', $source['product']->sku)
            ->firstOrFail();
        $betaProduct = Product::withoutGlobalScopes()
            ->where('tenant_id', $beta->id)
            ->where('sku', $source['product']->sku)
            ->firstOrFail();

        $this
            ->withHeaders($this->jsonHeaders())
            ->getJson('http://alpha-storefront.company.com/api/frontend/product')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $alphaProduct->id);

        $this
            ->withHeaders($this->jsonHeaders())
            ->getJson('http://beta-storefront.company.com/api/frontend/product')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $betaProduct->id);

        $this->assertNotSame($alphaProduct->id, $betaProduct->id);
    }

    public function test_owner_demo_product_validation_ignores_tenant_copies_with_matching_name_and_sku(): void
    {
        $tenant = $this->createTenant('tenant-copy');
        Product::withoutGlobalScopes()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Shared Demo Item',
            'slug' => 'shared-demo-item',
            'sku' => '7700001',
            'buying_price' => 10,
            'selling_price' => 20,
            'variation_price' => 20,
            'status' => Status::ACTIVE,
            'can_purchasable' => Ask::YES,
            'show_stock_out' => Ask::YES,
            'maximum_purchase_quantity' => 5,
            'low_stock_quantity_warning' => 1,
            'refundable' => Ask::YES,
        ]);

        $ownerCategory = ProductCategory::withoutGlobalScopes()->create([
            'tenant_id' => null,
            'name' => 'Owner Category',
            'slug' => 'owner-category',
            'status' => Status::ACTIVE,
        ]);
        $ownerUnit = Unit::withoutGlobalScopes()->create([
            'tenant_id' => null,
            'name' => 'Owner Piece',
            'code' => 'owner-pc',
            'status' => Status::ACTIVE,
        ]);

        $request = ProductRequest::create('/admin/products', 'POST', [
            'name' => 'Shared Demo Item',
            'sku' => '7700001',
            'product_category_id' => $ownerCategory->id,
            'barcode_id' => 1,
            'buying_price' => 10,
            'selling_price' => 20,
            'product_brand_id' => null,
            'status' => Status::ACTIVE,
            'can_purchasable' => Ask::YES,
            'show_stock_out' => Ask::YES,
            'refundable' => Ask::YES,
            'maximum_purchase_quantity' => 5,
            'low_stock_quantity_warning' => 1,
            'unit_id' => $ownerUnit->id,
        ]);
        $request->setContainer($this->app);

        $validator = Validator::make($request->all(), $request->rules(), [], $request->attributes());
        $request->withValidator($validator);

        $this->assertFalse($validator->fails(), json_encode($validator->errors()->toArray()));
    }

    /**
     * @return array<string, mixed>
     */
    private function createOwnerDemoCatalog(): array
    {
        $category = ProductCategory::withoutGlobalScopes()->create([
            'tenant_id' => null,
            'name' => 'Demo Category',
            'slug' => 'demo-category',
            'description' => 'Owner demo category',
            'status' => Status::ACTIVE,
        ]);
        $brand = ProductBrand::withoutGlobalScopes()->create([
            'tenant_id' => null,
            'name' => 'Demo Brand',
            'slug' => 'demo-brand',
            'description' => 'Owner demo brand',
            'status' => Status::ACTIVE,
        ]);
        $unit = Unit::withoutGlobalScopes()->create([
            'tenant_id' => null,
            'name' => 'Piece',
            'code' => 'pc',
            'status' => Status::ACTIVE,
        ]);
        $tax = Tax::withoutGlobalScopes()->create([
            'tenant_id' => null,
            'name' => 'Demo VAT',
            'code' => 'demo-vat',
            'tax_rate' => '5',
            'status' => Status::ACTIVE,
        ]);
        $attribute = ProductAttribute::withoutGlobalScopes()->create([
            'tenant_id' => null,
            'name' => 'Size',
        ]);
        $attributeOption = ProductAttributeOption::withoutGlobalScopes()->create([
            'tenant_id' => null,
            'product_attribute_id' => $attribute->id,
            'name' => 'M',
        ]);
        $slider = Slider::withoutGlobalScopes()->create([
            'tenant_id' => null,
            'title' => 'Demo Hero',
            'link' => 'https://owner.example.test/demo',
            'description' => 'Owner demo banner',
            'status' => Status::ACTIVE,
        ]);
        $product = Product::withoutGlobalScopes()->create([
            'tenant_id' => null,
            'name' => 'Demo Product',
            'slug' => 'demo-product',
            'sku' => '8800001',
            'product_category_id' => $category->id,
            'product_brand_id' => $brand->id,
            'unit_id' => $unit->id,
            'buying_price' => 10,
            'selling_price' => 25,
            'variation_price' => 25,
            'status' => Status::ACTIVE,
            'order' => 1,
            'can_purchasable' => Ask::YES,
            'show_stock_out' => Ask::YES,
            'maximum_purchase_quantity' => 10,
            'low_stock_quantity_warning' => 2,
            'refundable' => Ask::YES,
            'description' => 'Owner demo product',
        ]);
        $section = ProductSection::withoutGlobalScopes()->create([
            'tenant_id' => null,
            'name' => 'Featured Demos',
            'slug' => 'featured-demos',
            'status' => Status::ACTIVE,
        ]);

        $slider->addMedia(UploadedFile::fake()->image('demo-hero.jpg', 1200, 480))->toMediaCollection('slider');
        $product->addMedia(UploadedFile::fake()->image('demo-product.jpg', 800, 800))->toMediaCollection('product');

        ProductTag::query()->create([
            'product_id' => $product->id,
            'name' => 'demo',
        ]);
        ProductTax::withoutGlobalScopes()->create([
            'tenant_id' => null,
            'product_id' => $product->id,
            'tax_id' => $tax->id,
        ]);
        ProductVariation::withoutGlobalScopes()->create([
            'tenant_id' => null,
            'product_id' => $product->id,
            'product_attribute_id' => $attribute->id,
            'product_attribute_option_id' => $attributeOption->id,
            'price' => 25,
            'sku' => '8800002',
            'order' => 1,
        ]);
        ProductSectionProduct::withoutGlobalScopes()->create([
            'tenant_id' => null,
            'product_section_id' => $section->id,
            'product_id' => $product->id,
        ]);

        return compact('category', 'brand', 'unit', 'tax', 'attribute', 'attributeOption', 'slider', 'product', 'section');
    }

    private function createTenant(string $slug): Tenant
    {
        $tenant = Tenant::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => Str::headline($slug),
            'slug' => $slug,
            'store_code' => Str::upper(Str::substr(Str::slug($slug, ''), 0, 6)).'01',
            'status' => 'active',
            'onboarding_status' => 'basic_complete',
            'primary_locale' => 'en',
            'primary_currency_code' => 'USD',
            'timezone' => 'UTC',
        ]);

        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'hostname' => "{$slug}.company.com",
            'domain_type' => 'subdomain',
            'is_primary' => true,
            'is_fallback' => true,
            'ssl_status' => 'active',
            'verification_status' => 'verified',
        ]);

        return $tenant;
    }

    /**
     * @return array<string, string>
     */
    private function jsonHeaders(): array
    {
        return [
            'x-api-key' => 'testing-key',
            'x-localization' => 'en',
        ];
    }
}
