<?php

namespace Tests\Feature;

use App\Enums\Role as LegacyRole;
use App\Enums\PaymentStatus;
use App\Models\Barcode;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PlatformRole;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeOption;
use App\Models\ProductCategory;
use App\Models\ProductVariation;
use App\Models\Purchase;
use App\Models\Damage;
use App\Models\ReturnOrder;
use App\Models\ReturnAndRefund;
use App\Models\ReturnReason;
use App\Models\Stock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\TenantDomain;
use App\Models\TenantMember;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MerchantTenantIsolationTest extends TestCase
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
        ]);
    }

    public function test_merchant_products_are_scoped_to_authenticated_tenant(): void
    {
        $context = $this->createMerchantContext('alpha-store');
        $otherTenant = $this->createTenant('beta-store');

        $categoryA = ProductCategory::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'name' => 'Alpha Category',
            'slug' => 'alpha-category',
            'status' => 1,
        ]);

        $unitA = Unit::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'name' => 'Piece',
            'code' => 'pc',
            'status' => 1,
        ]);

        $categoryB = ProductCategory::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Beta Category',
            'slug' => 'beta-category',
            'status' => 1,
        ]);

        $unitB = Unit::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Box',
            'code' => 'bx',
            'status' => 1,
        ]);

        $barcode = Barcode::query()->create(['name' => 'EAN 13']);

        $productA = Product::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'name' => 'Alpha Product',
            'slug' => 'alpha-product',
            'sku' => '1000001',
            'product_category_id' => $categoryA->id,
            'barcode_id' => $barcode->id,
            'unit_id' => $unitA->id,
            'buying_price' => 10,
            'selling_price' => 20,
            'variation_price' => 20,
            'status' => 5,
            'can_purchasable' => 1,
            'show_stock_out' => 1,
            'maximum_purchase_quantity' => 10,
            'low_stock_quantity_warning' => 2,
            'refundable' => 1,
        ]);

        $productB = Product::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Beta Product',
            'slug' => 'beta-product',
            'sku' => '1000002',
            'product_category_id' => $categoryB->id,
            'barcode_id' => $barcode->id,
            'unit_id' => $unitB->id,
            'buying_price' => 12,
            'selling_price' => 24,
            'variation_price' => 24,
            'status' => 5,
            'can_purchasable' => 1,
            'show_stock_out' => 1,
            'maximum_purchase_quantity' => 10,
            'low_stock_quantity_warning' => 2,
            'refundable' => 1,
        ]);

        Sanctum::actingAs($context['user'], ['surface:merchant']);

        $response = $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson('http://merchant.company.com/api/merchant/products');

        $response
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $productA->id);

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson("http://merchant.company.com/api/merchant/products/{$productB->id}")
            ->assertNotFound();
    }

    public function test_merchant_dashboard_setup_starts_empty_and_ignores_other_tenant_data(): void
    {
        $context = $this->createMerchantContext('fresh-dashboard-store');
        $otherTenant = $this->createTenant('busy-dashboard-store');
        $barcode = Barcode::query()->create(['name' => 'EAN 13']);
        $customerRole = $this->seedLegacyRole(LegacyRole::CUSTOMER, 'customer');
        $otherCustomer = User::factory()->create([
            'status' => 5,
            'username' => 'busy-dashboard-customer',
            'country_code' => '+880',
            'is_guest' => 0,
        ]);
        $otherCustomer->assignRole($customerRole);

        $otherCategory = ProductCategory::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Busy Category',
            'slug' => 'busy-category',
            'status' => 1,
        ]);
        $otherUnit = Unit::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Box',
            'code' => 'box',
            'status' => 1,
        ]);
        Product::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Busy Product',
            'slug' => 'busy-product',
            'sku' => 'BUSY-001',
            'product_category_id' => $otherCategory->id,
            'barcode_id' => $barcode->id,
            'unit_id' => $otherUnit->id,
            'buying_price' => 10,
            'selling_price' => 20,
            'variation_price' => 20,
            'status' => 5,
            'can_purchasable' => 1,
            'show_stock_out' => 1,
            'maximum_purchase_quantity' => 10,
            'low_stock_quantity_warning' => 2,
            'refundable' => 1,
        ]);
        Order::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherCustomer->id,
            'subtotal' => 200,
            'tax' => 0,
            'discount' => 0,
            'shipping_charge' => 0,
            'total' => 200,
            'payment_status' => PaymentStatus::PAID,
            'status' => 1,
            'active' => 1,
            'order_datetime' => now(),
        ]);

        Sanctum::actingAs($context['user'], ['surface:merchant']);

        $freshResponse = $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson('http://merchant.company.com/api/merchant/dashboard/setup')
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.metrics.total_sales_raw', 0)
            ->assertJsonPath('data.metrics.total_orders', 0)
            ->assertJsonPath('data.metrics.total_products', 0)
            ->assertJsonPath('data.metrics.recent_orders', [])
            ->assertJsonPath('data.metrics.fallback_domain.hostname', 'fresh-dashboard-store.company.com');

        $firstProductStep = collect($freshResponse->json('data.checklist'))
            ->firstWhere('key', 'first_product');
        $this->assertSame(false, $firstProductStep['completed']);

        $ownCustomer = User::factory()->create([
            'status' => 5,
            'username' => 'fresh-dashboard-customer',
            'country_code' => '+880',
            'is_guest' => 0,
        ]);
        $ownCustomer->assignRole($customerRole);

        $ownCategory = ProductCategory::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'name' => 'Fresh Category',
            'slug' => 'fresh-category',
            'status' => 1,
        ]);
        $ownUnit = Unit::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'name' => 'Piece',
            'code' => 'pc',
            'status' => 1,
        ]);
        Product::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'name' => 'Fresh Product',
            'slug' => 'fresh-product',
            'sku' => 'FRESH-001',
            'product_category_id' => $ownCategory->id,
            'barcode_id' => $barcode->id,
            'unit_id' => $ownUnit->id,
            'buying_price' => 10,
            'selling_price' => 25,
            'variation_price' => 25,
            'status' => 5,
            'can_purchasable' => 1,
            'show_stock_out' => 1,
            'maximum_purchase_quantity' => 10,
            'low_stock_quantity_warning' => 2,
            'refundable' => 1,
        ]);
        Order::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'user_id' => $ownCustomer->id,
            'order_serial_no' => 'FRESH-ORDER-001',
            'subtotal' => 125,
            'tax' => 0,
            'discount' => 0,
            'shipping_charge' => 0,
            'total' => 125,
            'payment_status' => PaymentStatus::PAID,
            'status' => 1,
            'active' => 1,
            'order_datetime' => now(),
        ]);

        $filledResponse = $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson('http://merchant.company.com/api/merchant/dashboard/setup')
            ->assertOk()
            ->assertJsonPath('data.metrics.total_sales_raw', 125)
            ->assertJsonPath('data.metrics.total_orders', 1)
            ->assertJsonPath('data.metrics.total_products', 1)
            ->assertJsonPath('data.metrics.recent_orders.0.order_serial_no', 'FRESH-ORDER-001');

        $firstProductStep = collect($filledResponse->json('data.checklist'))
            ->firstWhere('key', 'first_product');
        $this->assertSame(true, $firstProductStep['completed']);
    }

    public function test_merchant_can_create_same_product_sku_in_different_tenants(): void
    {
        $context = $this->createMerchantContext('gamma-store');
        $otherTenant = $this->createTenant('delta-store');

        $barcode = Barcode::query()->create(['name' => 'EAN 13']);

        $categoryA = ProductCategory::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'name' => 'Shared Category',
            'slug' => 'shared-category',
            'status' => 1,
        ]);

        $unitA = Unit::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'name' => 'Piece',
            'code' => 'pc',
            'status' => 1,
        ]);

        $categoryB = ProductCategory::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Shared Category',
            'slug' => 'shared-category',
            'status' => 1,
        ]);

        $unitB = Unit::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Piece',
            'code' => 'pc',
            'status' => 1,
        ]);

        Product::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Shared Product',
            'slug' => 'shared-product',
            'sku' => '5550001',
            'product_category_id' => $categoryB->id,
            'barcode_id' => $barcode->id,
            'unit_id' => $unitB->id,
            'buying_price' => 10,
            'selling_price' => 15,
            'variation_price' => 15,
            'status' => 5,
            'can_purchasable' => 1,
            'show_stock_out' => 1,
            'maximum_purchase_quantity' => 5,
            'low_stock_quantity_warning' => 1,
            'refundable' => 1,
        ]);

        Sanctum::actingAs($context['user'], ['surface:merchant']);

        $response = $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->postJson('http://merchant.company.com/api/merchant/products', [
                'name' => 'Shared Product',
                'sku' => '5550001',
                'product_category_id' => $categoryA->id,
                'barcode_id' => $barcode->id,
                'buying_price' => 12,
                'selling_price' => 18,
                'status' => 5,
                'can_purchasable' => 1,
                'show_stock_out' => 1,
                'refundable' => 1,
                'maximum_purchase_quantity' => 5,
                'low_stock_quantity_warning' => 1,
                'unit_id' => $unitA->id,
            ]);

        if ($response->getStatusCode() !== 201) {
            $this->fail(sprintf(
                'Merchant product create failed with status %s and body %s',
                $response->getStatusCode(),
                $response->getContent()
            ));
        }

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'Shared Product')
            ->assertJsonPath('data.sku', '5550001');

        $this->assertDatabaseHas('products', [
            'tenant_id' => $context['tenant']->id,
            'sku' => '5550001',
            'name' => 'Shared Product',
        ]);
    }

    public function test_merchant_orders_are_scoped_to_authenticated_tenant(): void
    {
        $context = $this->createMerchantContext('orders-store');
        $otherTenant = $this->createTenant('other-orders-store');

        $customerRole = $this->seedLegacyRole(LegacyRole::CUSTOMER, 'customer');
        $customerA = User::factory()->create([
            'status' => 5,
            'username' => 'customer-a',
            'country_code' => '+880',
            'is_guest' => 0,
        ]);
        $customerA->assignRole($customerRole);

        $customerB = User::factory()->create([
            'status' => 5,
            'username' => 'customer-b',
            'country_code' => '+880',
            'is_guest' => 0,
        ]);
        $customerB->assignRole($customerRole);

        $orderA = Order::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'user_id' => $customerA->id,
            'subtotal' => 100,
            'tax' => 0,
            'discount' => 0,
            'shipping_charge' => 0,
            'total' => 100,
            'status' => 1,
            'active' => 1,
            'order_datetime' => now(),
        ]);

        $orderB = Order::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $customerB->id,
            'subtotal' => 120,
            'tax' => 0,
            'discount' => 0,
            'shipping_charge' => 0,
            'total' => 120,
            'status' => 1,
            'active' => 1,
            'order_datetime' => now(),
        ]);

        Sanctum::actingAs($context['user'], ['surface:merchant']);

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson('http://merchant.company.com/api/merchant/orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $orderA->id);

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson("http://merchant.company.com/api/merchant/orders/{$orderB->id}")
            ->assertNotFound();
    }

    public function test_merchant_customers_are_scoped_to_shadow_customer_tenant(): void
    {
        $context = $this->createMerchantContext('customers-store');
        $otherTenant = $this->createTenant('other-customers-store');

        $customerRole = $this->seedLegacyRole(LegacyRole::CUSTOMER, 'customer');
        $legacyCustomerA = User::factory()->create([
            'status' => 5,
            'username' => 'legacy-customer-a',
            'country_code' => '+880',
            'is_guest' => 0,
        ]);
        $legacyCustomerA->assignRole($customerRole);

        $legacyCustomerB = User::factory()->create([
            'status' => 5,
            'username' => 'legacy-customer-b',
            'country_code' => '+880',
            'is_guest' => 0,
        ]);
        $legacyCustomerB->assignRole($customerRole);

        $shadowA = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'legacy_user_id' => $legacyCustomerA->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant A Customer',
            'email' => 'tenant-a@example.com',
            'status' => 1,
        ]);

        $shadowB = Customer::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'legacy_user_id' => $legacyCustomerB->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Tenant B Customer',
            'email' => 'tenant-b@example.com',
            'status' => 1,
        ]);

        Sanctum::actingAs($context['user'], ['surface:merchant']);

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson('http://merchant.company.com/api/merchant/customers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $shadowA->id);

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson("http://merchant.company.com/api/merchant/customers/{$shadowB->id}")
            ->assertNotFound();
    }

    public function test_merchant_suppliers_are_scoped_to_authenticated_tenant(): void
    {
        $context = $this->createMerchantContext('supplier-store');
        $otherTenant = $this->createTenant('supplier-other-store');

        $supplierA = Supplier::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'company' => 'Alpha Supply',
            'name' => 'Alpha Supplier',
            'email' => 'alpha@supplier.test',
        ]);

        $supplierB = Supplier::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'company' => 'Beta Supply',
            'name' => 'Beta Supplier',
            'email' => 'beta@supplier.test',
        ]);

        Sanctum::actingAs($context['user'], ['surface:merchant']);

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson('http://merchant.company.com/api/merchant/suppliers')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $supplierA->id);

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson("http://merchant.company.com/api/merchant/suppliers/{$supplierB->id}")
            ->assertNotFound();
    }

    public function test_merchant_variations_are_scoped_to_authenticated_tenant(): void
    {
        $context = $this->createMerchantContext('variation-store');
        $otherTenant = $this->createTenant('variation-other-store');

        $barcode = Barcode::query()->create(['name' => 'EAN 13']);

        $categoryA = ProductCategory::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'name' => 'Variation Category A',
            'slug' => 'variation-category-a',
            'status' => 1,
        ]);

        $unitA = Unit::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'name' => 'Piece',
            'code' => 'pc',
            'status' => 1,
        ]);

        $productA = Product::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'name' => 'Variation Product A',
            'slug' => 'variation-product-a',
            'sku' => '6100001',
            'product_category_id' => $categoryA->id,
            'barcode_id' => $barcode->id,
            'unit_id' => $unitA->id,
            'buying_price' => 10,
            'selling_price' => 20,
            'variation_price' => 20,
            'status' => 5,
            'can_purchasable' => 1,
            'show_stock_out' => 1,
            'maximum_purchase_quantity' => 10,
            'low_stock_quantity_warning' => 2,
            'refundable' => 1,
        ]);

        $attributeA = ProductAttribute::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'name' => 'Color',
        ]);

        $optionA = ProductAttributeOption::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'product_attribute_id' => $attributeA->id,
            'name' => 'Red',
        ]);

        $variationA = ProductVariation::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'product_id' => $productA->id,
            'product_attribute_id' => $attributeA->id,
            'product_attribute_option_id' => $optionA->id,
            'price' => 22,
            'sku' => '6101001',
            'order' => 1,
        ]);

        $categoryB = ProductCategory::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Variation Category B',
            'slug' => 'variation-category-b',
            'status' => 1,
        ]);

        $unitB = Unit::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Box',
            'code' => 'bx',
            'status' => 1,
        ]);

        $productB = Product::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Variation Product B',
            'slug' => 'variation-product-b',
            'sku' => '6200001',
            'product_category_id' => $categoryB->id,
            'barcode_id' => $barcode->id,
            'unit_id' => $unitB->id,
            'buying_price' => 12,
            'selling_price' => 24,
            'variation_price' => 24,
            'status' => 5,
            'can_purchasable' => 1,
            'show_stock_out' => 1,
            'maximum_purchase_quantity' => 10,
            'low_stock_quantity_warning' => 2,
            'refundable' => 1,
        ]);

        $attributeB = ProductAttribute::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Size',
        ]);

        $optionB = ProductAttributeOption::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'product_attribute_id' => $attributeB->id,
            'name' => 'Large',
        ]);

        $variationB = ProductVariation::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'product_id' => $productB->id,
            'product_attribute_id' => $attributeB->id,
            'product_attribute_option_id' => $optionB->id,
            'price' => 26,
            'sku' => '6201001',
            'order' => 1,
        ]);

        Sanctum::actingAs($context['user'], ['surface:merchant']);

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson("http://merchant.company.com/api/merchant/products/{$productA->id}/variations")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $variationA->id);

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson("http://merchant.company.com/api/merchant/products/{$productA->id}/variations/{$variationB->id}")
            ->assertNotFound();
    }

    public function test_merchant_stock_is_scoped_to_authenticated_tenant(): void
    {
        $context = $this->createMerchantContext('stock-store');
        $otherTenant = $this->createTenant('stock-other-store');

        $barcode = Barcode::query()->create(['name' => 'EAN 13']);

        $categoryA = ProductCategory::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'name' => 'Stock Category A',
            'slug' => 'stock-category-a',
            'status' => 1,
        ]);

        $unitA = Unit::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'name' => 'Piece',
            'code' => 'pc',
            'status' => 1,
        ]);

        $productA = Product::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'name' => 'Stock Product A',
            'slug' => 'stock-product-a',
            'sku' => '7100001',
            'product_category_id' => $categoryA->id,
            'barcode_id' => $barcode->id,
            'unit_id' => $unitA->id,
            'buying_price' => 10,
            'selling_price' => 20,
            'variation_price' => 20,
            'status' => 5,
            'can_purchasable' => 1,
            'show_stock_out' => 1,
            'maximum_purchase_quantity' => 10,
            'low_stock_quantity_warning' => 2,
            'refundable' => 1,
        ]);

        $categoryB = ProductCategory::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Stock Category B',
            'slug' => 'stock-category-b',
            'status' => 1,
        ]);

        $unitB = Unit::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Box',
            'code' => 'bx',
            'status' => 1,
        ]);

        $productB = Product::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Stock Product B',
            'slug' => 'stock-product-b',
            'sku' => '7200001',
            'product_category_id' => $categoryB->id,
            'barcode_id' => $barcode->id,
            'unit_id' => $unitB->id,
            'buying_price' => 12,
            'selling_price' => 24,
            'variation_price' => 24,
            'status' => 5,
            'can_purchasable' => 1,
            'show_stock_out' => 1,
            'maximum_purchase_quantity' => 10,
            'low_stock_quantity_warning' => 2,
            'refundable' => 1,
        ]);

        Stock::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'product_id' => $productA->id,
            'model_type' => Product::class,
            'model_id' => $productA->id,
            'item_type' => Product::class,
            'item_id' => $productA->id,
            'price' => 20,
            'quantity' => 5,
            'status' => 5,
        ]);

        Stock::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'product_id' => $productB->id,
            'model_type' => Product::class,
            'model_id' => $productB->id,
            'item_type' => Product::class,
            'item_id' => $productB->id,
            'price' => 24,
            'quantity' => 8,
            'status' => 5,
        ]);

        Sanctum::actingAs($context['user'], ['surface:merchant']);

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson('http://merchant.company.com/api/merchant/stock')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.product_id', $productA->id)
            ->assertJsonPath('data.0.stock', 5);
    }

    public function test_merchant_purchases_are_scoped_to_authenticated_tenant(): void
    {
        $context = $this->createMerchantContext('purchase-store');
        $otherTenant = $this->createTenant('purchase-other-store');

        $supplierA = Supplier::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'company' => 'Purchase Alpha',
            'name' => 'Purchase Supplier A',
        ]);

        $supplierB = Supplier::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'company' => 'Purchase Beta',
            'name' => 'Purchase Supplier B',
        ]);

        $purchaseA = Purchase::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'supplier_id' => $supplierA->id,
            'date' => now(),
            'reference_no' => 'PUR-A-001',
            'subtotal' => 100,
            'tax' => 0,
            'discount' => 0,
            'payment_status' => 5,
            'total' => 100,
            'note' => 'Tenant A purchase',
            'status' => 15,
        ]);

        $purchaseB = Purchase::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'supplier_id' => $supplierB->id,
            'date' => now(),
            'reference_no' => 'PUR-B-001',
            'subtotal' => 150,
            'tax' => 0,
            'discount' => 0,
            'payment_status' => 5,
            'total' => 150,
            'note' => 'Tenant B purchase',
            'status' => 15,
        ]);

        Sanctum::actingAs($context['user'], ['surface:merchant']);

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson('http://merchant.company.com/api/merchant/purchases')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $purchaseA->id);

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson("http://merchant.company.com/api/merchant/purchases/{$purchaseB->id}")
            ->assertNotFound();
    }

    public function test_merchant_damages_are_scoped_to_authenticated_tenant(): void
    {
        $context = $this->createMerchantContext('damage-store');
        $otherTenant = $this->createTenant('damage-other-store');

        $damageA = Damage::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'date' => now(),
            'reference_no' => 'DMG-A-001',
            'subtotal' => 20,
            'tax' => 0,
            'discount' => 0,
            'total' => 20,
            'note' => 'Tenant A damage',
        ]);

        $damageB = Damage::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'date' => now(),
            'reference_no' => 'DMG-B-001',
            'subtotal' => 25,
            'tax' => 0,
            'discount' => 0,
            'total' => 25,
            'note' => 'Tenant B damage',
        ]);

        Sanctum::actingAs($context['user'], ['surface:merchant']);

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson('http://merchant.company.com/api/merchant/damages')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $damageA->id);

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson("http://merchant.company.com/api/merchant/damages/{$damageB->id}")
            ->assertNotFound();
    }

    public function test_merchant_pos_customer_creation_creates_shadow_customer_in_current_tenant(): void
    {
        $context = $this->createMerchantContext('pos-store');

        Sanctum::actingAs($context['user'], ['surface:merchant']);

        $response = $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->postJson('http://merchant.company.com/api/merchant/pos/customers', [
                'name' => 'POS Customer',
                'email' => 'pos-customer@example.com',
                'password' => 'password',
                'password_confirmation' => 'password',
                'status' => 5,
                'country_code' => '+880',
                'phone' => '01700000001',
            ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.email', 'pos-customer@example.com');

        $legacyUserId = $response->json('data.id');

        $this->assertDatabaseHas('customers', [
            'tenant_id' => $context['tenant']->id,
            'legacy_user_id' => $legacyUserId,
            'email' => 'pos-customer@example.com',
        ]);
    }

    public function test_merchant_returns_are_scoped_to_authenticated_tenant(): void
    {
        $context = $this->createMerchantContext('return-store');
        $otherTenant = $this->createTenant('return-other-store');
        $returnReason = ReturnReason::query()->create([
            'title' => 'Damaged item',
            'status' => 5,
        ]);

        $customerRole = $this->seedLegacyRole(LegacyRole::CUSTOMER, 'customer');
        $legacyCustomerA = User::factory()->create([
            'status' => 5,
            'username' => 'return-customer-a',
            'country_code' => '+880',
            'is_guest' => 0,
        ]);
        $legacyCustomerA->assignRole($customerRole);

        $legacyCustomerB = User::factory()->create([
            'status' => 5,
            'username' => 'return-customer-b',
            'country_code' => '+880',
            'is_guest' => 0,
        ]);
        $legacyCustomerB->assignRole($customerRole);

        $returnOrderA = ReturnOrder::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'user_id' => $legacyCustomerA->id,
            'date' => now(),
            'reference_no' => 'RET-A-001',
            'subtotal' => 30,
            'tax' => 0,
            'discount' => 0,
            'total' => 30,
            'reason' => 'A',
        ]);

        $returnOrderB = ReturnOrder::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $legacyCustomerB->id,
            'date' => now(),
            'reference_no' => 'RET-B-001',
            'subtotal' => 40,
            'tax' => 0,
            'discount' => 0,
            'total' => 40,
            'reason' => 'B',
        ]);

        $orderA = Order::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'user_id' => $legacyCustomerA->id,
            'subtotal' => 100,
            'tax' => 0,
            'discount' => 0,
            'shipping_charge' => 0,
            'total' => 100,
            'status' => 1,
            'active' => 1,
            'order_datetime' => now(),
        ]);

        $orderB = Order::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $legacyCustomerB->id,
            'subtotal' => 120,
            'tax' => 0,
            'discount' => 0,
            'shipping_charge' => 0,
            'total' => 120,
            'status' => 1,
            'active' => 1,
            'order_datetime' => now(),
        ]);

        $returnAndRefundA = ReturnAndRefund::withoutGlobalScopes()->create([
            'tenant_id' => $context['tenant']->id,
            'return_reason_id' => $returnReason->id,
            'order_id' => $orderA->id,
            'user_id' => $legacyCustomerA->id,
            'order_serial_no' => 'RA-001',
            'status' => 5,
        ]);

        $returnAndRefundB = ReturnAndRefund::withoutGlobalScopes()->create([
            'tenant_id' => $otherTenant->id,
            'return_reason_id' => $returnReason->id,
            'order_id' => $orderB->id,
            'user_id' => $legacyCustomerB->id,
            'order_serial_no' => 'RB-001',
            'status' => 5,
        ]);

        Sanctum::actingAs($context['user'], ['surface:merchant']);

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson('http://merchant.company.com/api/merchant/return-orders')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $returnOrderA->id);

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson("http://merchant.company.com/api/merchant/return-orders/{$returnOrderB->id}")
            ->assertNotFound();

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson('http://merchant.company.com/api/merchant/returns')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $returnAndRefundA->id);

        $this
            ->withHeader('x-api-key', 'testing-key')
            ->withHeader('x-localization', 'en')
            ->withHeader('X-Tenant-Slug', $context['tenant']->slug)
            ->getJson("http://merchant.company.com/api/merchant/returns/{$returnAndRefundB->id}")
            ->assertNotFound();
    }

    private function createMerchantContext(string $slug): array
    {
        $role = $this->seedLegacyRole(LegacyRole::MANAGER, 'manager');
        $user = User::factory()->create([
            'status' => 5,
            'username' => $slug.'-merchant',
            'country_code' => '+880',
            'is_guest' => 0,
        ]);
        $user->assignRole($role);

        $tenant = $this->createTenant($slug);
        $platformRole = PlatformRole::query()->firstOrCreate(
            ['code' => 'merchant_owner'],
            ['name' => 'Merchant Owner', 'scope' => 'merchant', 'is_system' => true]
        );

        TenantMember::query()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'role_id' => $platformRole->id,
            'status' => 'active',
        ]);

        return compact('user', 'tenant');
    }

    private function createTenant(string $slug): Tenant
    {
        $tenant = Tenant::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => Str::headline($slug),
            'slug' => $slug,
            'store_code' => strtoupper(Str::substr(Str::slug($slug, ''), 0, 4)).strtoupper(Str::random(4)),
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

    private function seedLegacyRole(int $id, string $name): Role
    {
        $role = new Role();
        $role->id = $id;
        $role->name = $name;
        $role->guard_name = 'web';
        $role->save();

        return $role;
    }
}
