<?php

namespace App\Services\Saas;

use App\Enums\Ask;
use App\Enums\Role;
use App\Enums\Status;
use App\Models\Benefit;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Outlet;
use App\Models\Page;
use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductAttributeOption;
use App\Models\ProductBrand;
use App\Models\ProductCategory;
use App\Models\ProductSection;
use App\Models\ProductSectionProduct;
use App\Models\ProductSeo;
use App\Models\ProductTag;
use App\Models\ProductTax;
use App\Models\ProductVariation;
use App\Models\ProductVideo;
use App\Models\Slider;
use App\Models\Tax;
use App\Models\Tenant;
use App\Models\TenantDemoContentSeed;
use App\Models\TenantDomain;
use App\Models\TenantFeatureFlag;
use App\Models\TenantMember;
use App\Models\TenantPaymentMethod;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role as SpatieRole;

class TenantProvisioningService
{
    private ?bool $tenantDemoContentSeedTableExists = null;

    public function __construct(
        private readonly PlatformRoleRegistryService $platformRoleRegistryService,
        private readonly TenantSettingsService $tenantSettingsService,
        private readonly SubscriptionManagerService $subscriptionManagerService,
        private readonly MerchantPermissionBootstrapper $merchantPermissionBootstrapper,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{user: User, tenant: Tenant, domain: TenantDomain, checks: array<string, bool>}
     */
    public function registerMerchant(array $payload): array
    {
        return DB::transaction(function () use ($payload) {
            $merchantRole = $this->ensureManagerRole();
            $tenantRole = $this->platformRoleRegistryService->merchantOwnerRole();
            $storeSlug = $this->resolveStoreSlug($payload);

            $user = User::query()->create([
                'name' => $payload['owner_name'],
                'username' => $this->buildUsername($payload['owner_name'], $storeSlug),
                'email' => $payload['email'] ?? null,
                'phone' => $payload['phone'] ?? null,
                'country_code' => $payload['country_code'] ?? null,
                'email_verified_at' => now(),
                'is_guest' => Ask::NO,
                'status' => Status::ACTIVE,
                'password' => Hash::make((string) $payload['password']),
            ]);
            $user->assignRole($merchantRole);

            $tenant = Tenant::query()->create([
                'uuid' => (string) Str::uuid(),
                'name' => $payload['store_name'],
                'legal_name' => $payload['legal_name'] ?? null,
                'slug' => $storeSlug,
                'store_code' => $this->buildStoreCode($storeSlug),
                'status' => 'draft',
                'plan_code' => $payload['plan_code'] ?? 'starter',
                'onboarding_status' => 'pending',
                'primary_locale' => $payload['primary_locale'] ?? 'en',
                'primary_currency_code' => $payload['primary_currency_code'] ?? 'USD',
                'timezone' => $payload['timezone'] ?? 'UTC',
                'country_code' => $payload['country_code'] ?? null,
                'contact_email' => $payload['email'] ?? null,
                'contact_phone' => $payload['phone'] ?? null,
                'created_by_user_id' => $user->id,
            ]);

            $domain = TenantDomain::query()->create([
                'tenant_id' => $tenant->id,
                'hostname' => sprintf('%s.%s', $tenant->slug, config('saas.fallback_subdomain_suffix')),
                'domain_type' => 'subdomain',
                'is_primary' => true,
                'is_fallback' => true,
                'ssl_status' => 'active',
                'verification_status' => 'verified',
                'verified_at' => now(),
                'last_checked_at' => now(),
            ]);

            TenantMember::query()->create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'role_id' => $tenantRole->id,
                'status' => 'active',
                'joined_at' => now(),
            ]);

            $this->tenantSettingsService->seedDefaultsForTenant($tenant, [
                'company_name' => $tenant->name,
                'company_email' => $tenant->contact_email,
                'company_phone' => $tenant->contact_phone,
                'company_calling_code' => $payload['country_code'] ?? null,
                'company_country_code' => $payload['country_code'] ?? null,
                'site_online_payment_gateway' => 5,
                'site_cash_on_delivery' => 5,
                'shipping_setup_method' => 5,
            ]);

            $this->seedCommerceDefaults($tenant, $user);
            $this->seedStorefrontDefaults($tenant);
            $this->subscriptionManagerService->assignPlanToTenant(
                $tenant,
                $tenant->plan_code ?? 'starter',
                'monthly',
                $user,
                ['source' => 'merchant_register']
            );

            $checks = $this->evaluateAutoLiveChecks($tenant);

            if (!in_array(false, $checks, true)) {
                $tenant->forceFill([
                    'status' => 'active',
                    'onboarding_status' => 'basic_complete',
                    'approved_by_user_id' => $user->id,
                    'approved_at' => now(),
                    'launched_at' => now(),
                ])->save();
            }

            return [
                'user' => $user,
                'tenant' => $tenant->fresh(),
                'domain' => $domain->fresh(),
                'checks' => $checks,
            ];
        });
    }

    public function syncShadowCustomer(User $user, Tenant $tenant): Customer
    {
        $identity = ['tenant_id' => $tenant->id];

        if (filled($user->email)) {
            $identity['email'] = $user->email;
        } else {
            $identity['phone'] = $user->phone;
            $identity['country_code'] = $user->country_code;
        }

        return Customer::query()->updateOrCreate(
            $identity,
            [
                'legacy_user_id' => $user->id,
                'uuid' => (string) Str::uuid(),
                'name' => $user->name,
                'phone' => $user->phone,
                'country_code' => $user->country_code,
                'password' => $user->password,
                'status' => 1,
                'is_guest' => (bool) $user->is_guest,
                'email_verified_at' => $user->email_verified_at,
            ]
        );
    }

    /**
     * @return array<string, bool>
     */
    public function evaluateAutoLiveChecks(Tenant $tenant): array
    {
        $settings = $this->tenantSettingsService->mergedForTenant($tenant);

        return [
            'unique_store_slug' => filled($tenant->slug),
            'starter_preset_assigned' => $tenant->domains()->exists(),
            'payment_method_active' => TenantPaymentMethod::query()->where('tenant_id', $tenant->id)->where('status', true)->exists(),
            'shipping_active' => filled($settings['shipping_setup_method'] ?? null),
            'no_owner_suspension' => $tenant->status !== 'suspended',
            'no_fraud_block' => true,
            'verified_contact' => filled($tenant->contact_email) || filled($tenant->contact_phone),
        ];
    }

    private function ensureManagerRole(): SpatieRole
    {
        return $this->merchantPermissionBootstrapper->ensureManagerRoleHasStorePermissions();
    }

    private function buildUsername(string $ownerName, string $storeSlug): string
    {
        return Str::slug($ownerName).'-'.$storeSlug.'-'.Str::lower(Str::random(6));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveStoreSlug(array $payload): string
    {
        $baseSlug = Str::slug((string) $payload['store_name']);

        if ($baseSlug === '') {
            $baseSlug = 'store';
        }

        $baseSlug = Str::limit($baseSlug, 110, '');
        $slug = $baseSlug;
        $counter = 2;
        $reservedStoreSlugs = array_map(
            static fn (string $slug): string => Str::slug($slug),
            config('saas.reserved_store_slugs', [])
        );

        if (in_array($slug, $reservedStoreSlugs, true)) {
            throw ValidationException::withMessages([
                'store_name' => 'This store name is reserved for the platform. Please choose a different store name.',
            ]);
        }

        while (Tenant::query()->where('slug', $slug)->exists()) {
            $suffix = '-'.$counter;
            $slug = Str::limit($baseSlug, 120 - strlen($suffix), '').$suffix;
            $counter++;
        }

        return $slug;
    }

    private function buildStoreCode(string $storeSlug): string
    {
        return strtoupper(Str::substr(Str::slug($storeSlug, ''), 0, 6)).str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
    }

    private function seedCommerceDefaults(Tenant $tenant, User $actor): void
    {
        TenantPaymentMethod::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'provider_code' => 'cash_on_delivery',
            ],
            [
                'display_name' => 'Cash on Delivery',
                'status' => true,
                'checkout_label' => 'Pay with cash on delivery',
                'fee_type' => 'none',
                'fee_value' => null,
                'sort_order' => 1,
                'config_json' => ['managed_by' => 'owner'],
            ]
        );

        TenantFeatureFlag::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'feature_code' => 'simple_mode',
            ],
            [
                'status' => true,
                'source' => 'platform_default',
                'updated_by_user_id' => $actor->id,
            ]
        );

        TenantFeatureFlag::query()->updateOrCreate(
            [
                'tenant_id' => $tenant->id,
                'feature_code' => 'advanced_mode',
            ],
            [
                'status' => false,
                'source' => 'platform_default',
                'updated_by_user_id' => $actor->id,
            ]
        );
    }

    /**
     * @return array{tenants: int, seeded_records: int}
     */
    public function seedStorefrontDefaultsForTenants(?string $tenantSlug = null): array
    {
        $stats = [
            'tenants' => 0,
            'seeded_records' => 0,
        ];

        Tenant::query()
            ->when(filled($tenantSlug), fn ($query) => $query->where('slug', $tenantSlug))
            ->orderBy('id')
            ->get()
            ->each(function (Tenant $tenant) use (&$stats): void {
                $trackingEnabled = $this->tenantDemoSeedTrackingEnabled();
                $before = $trackingEnabled
                    ? TenantDemoContentSeed::query()->where('tenant_id', $tenant->id)->count()
                    : 0;

                DB::transaction(fn () => $this->seedStorefrontDefaults($tenant));

                $after = $trackingEnabled
                    ? TenantDemoContentSeed::query()->where('tenant_id', $tenant->id)->count()
                    : $before;
                $stats['tenants']++;
                $stats['seeded_records'] += max($after - $before, 0);
            });

        return $stats;
    }

    public function seedStorefrontDefaults(Tenant $tenant): void
    {
        $this->seedTenantCopies($tenant, Slider::class, ['title', 'link', 'description', 'status'], ['title'], ['slider']);
        $this->seedTenantCopies($tenant, Page::class, ['title', 'slug', 'description', 'menu_section_id', 'menu_template_id', 'status'], ['slug'], ['page-image']);
        $this->seedTenantCopies($tenant, Benefit::class, ['title', 'description', 'status', 'sort'], ['title'], ['benefit']);
        $this->seedTenantCopies($tenant, Currency::class, ['name', 'symbol', 'code', 'is_cryptocurrency', 'exchange_rate'], ['code']);
        $this->seedTenantCopies($tenant, Tax::class, ['name', 'code', 'tax_rate', 'status'], ['code']);
        $this->seedTenantCopies($tenant, Outlet::class, ['name', 'email', 'phone', 'country_code', 'latitude', 'longitude', 'city', 'state', 'zip_code', 'address', 'status'], ['name']);
        $this->seedProductCatalogDefaults($tenant);
    }

    private function seedProductCatalogDefaults(Tenant $tenant): void
    {
        $categoryMap = $this->seedProductCategoryCopies($tenant);
        $brandMap = $this->seedTenantCopies($tenant, ProductBrand::class, ['name', 'slug', 'description', 'status'], ['slug'], ['product-brand']);
        $unitMap = $this->seedTenantCopies($tenant, Unit::class, ['name', 'code', 'status'], ['code']);
        $taxMap = $this->seedTenantCopies($tenant, Tax::class, ['name', 'code', 'tax_rate', 'status'], ['code']);
        $attributeMap = $this->seedTenantCopies($tenant, ProductAttribute::class, ['name'], ['name']);
        $attributeOptionMap = $this->seedProductAttributeOptionCopies($tenant, $attributeMap);
        $productMap = $this->seedProductCopies($tenant, $categoryMap, $brandMap, $unitMap, $taxMap, $attributeMap, $attributeOptionMap);
        $sectionMap = $this->seedTenantCopies($tenant, ProductSection::class, ['name', 'slug', 'status'], ['slug']);

        $this->seedProductSectionProductCopies($tenant, $sectionMap, $productMap);
    }

    /**
     * @return array<int, int>
     */
    private function seedProductCategoryCopies(Tenant $tenant): array
    {
        $categoryMap = [];

        $this->templateQuery(ProductCategory::class)
            ->get()
            ->each(function (ProductCategory $source) use ($tenant, &$categoryMap): void {
                $this->seedProductCategoryCopy($tenant, $source, $categoryMap);
            });

        return array_filter($categoryMap);
    }

    /**
     * @param  array<int, int|null>  $categoryMap
     */
    private function seedProductCategoryCopy(Tenant $tenant, ProductCategory $source, array &$categoryMap): ?ProductCategory
    {
        if (array_key_exists($source->id, $categoryMap)) {
            $targetId = $categoryMap[$source->id];

            return $targetId === null
                ? null
                : ProductCategory::withoutGlobalScopes()->whereKey($targetId)->first();
        }

        $parentId = null;

        if ($source->parent_id !== null) {
            $sourceParent = ProductCategory::withoutGlobalScopes()
                ->whereNull('tenant_id')
                ->whereKey($source->parent_id)
                ->first();

            if ($sourceParent !== null) {
                $parentId = $this->seedProductCategoryCopy($tenant, $sourceParent, $categoryMap)?->id;
            }
        }

        $attributes = $this->copyAttributes($tenant, $source, ['name', 'slug', 'description', 'status']);
        $attributes['parent_id'] = $parentId;

        $copy = $this->seededCopy(
            $tenant,
            $source,
            $attributes,
            ['tenant_id' => $tenant->id, 'slug' => $source->slug],
            ['product-category']
        );

        $categoryMap[$source->id] = $copy?->id;

        return $copy instanceof ProductCategory ? $copy : null;
    }

    /**
     * @param  array<int, int>  $attributeMap
     * @return array<int, int>
     */
    private function seedProductAttributeOptionCopies(Tenant $tenant, array $attributeMap): array
    {
        $optionMap = [];

        $this->templateQuery(ProductAttributeOption::class)
            ->get()
            ->each(function (ProductAttributeOption $source) use ($tenant, $attributeMap, &$optionMap): void {
                $attributeId = $attributeMap[$source->product_attribute_id] ?? null;

                if ($attributeId === null) {
                    return;
                }

                $attributes = $this->copyAttributes($tenant, $source, ['name']);
                $attributes['product_attribute_id'] = $attributeId;

                $copy = $this->seededCopy(
                    $tenant,
                    $source,
                    $attributes,
                    [
                        'tenant_id' => $tenant->id,
                        'product_attribute_id' => $attributeId,
                        'name' => $source->name,
                    ]
                );

                if ($copy !== null) {
                    $optionMap[$source->id] = $copy->id;
                }
            });

        return $optionMap;
    }

    /**
     * @param  array<int, int>  $categoryMap
     * @param  array<int, int>  $brandMap
     * @param  array<int, int>  $unitMap
     * @param  array<int, int>  $taxMap
     * @param  array<int, int>  $attributeMap
     * @param  array<int, int>  $attributeOptionMap
     * @return array<int, int>
     */
    private function seedProductCopies(
        Tenant $tenant,
        array $categoryMap,
        array $brandMap,
        array $unitMap,
        array $taxMap,
        array $attributeMap,
        array $attributeOptionMap
    ): array {
        $productMap = [];

        $this->templateQuery(Product::class)
            ->get()
            ->each(function (Product $source) use (
                $tenant,
                $categoryMap,
                $brandMap,
                $unitMap,
                $taxMap,
                $attributeMap,
                $attributeOptionMap,
                &$productMap
            ): void {
                $attributes = $this->copyAttributes($tenant, $source, [
                    'name',
                    'slug',
                    'sku',
                    'barcode_id',
                    'buying_price',
                    'selling_price',
                    'variation_price',
                    'status',
                    'order',
                    'can_purchasable',
                    'show_stock_out',
                    'maximum_purchase_quantity',
                    'low_stock_quantity_warning',
                    'weight',
                    'warranty',
                    'refundable',
                    'description',
                    'shipping_and_return',
                    'add_to_flash_sale',
                    'discount',
                    'offer_start_date',
                    'offer_end_date',
                    'shipping_type',
                    'shipping_cost',
                    'is_product_quantity_multiply',
                ]);

                $attributes['product_category_id'] = $source->product_category_id === null
                    ? null
                    : ($categoryMap[$source->product_category_id] ?? null);
                $attributes['product_brand_id'] = $source->product_brand_id === null
                    ? null
                    : ($brandMap[$source->product_brand_id] ?? null);
                $attributes['unit_id'] = $source->unit_id === null
                    ? null
                    : ($unitMap[$source->unit_id] ?? null);

                $copy = $this->seededCopy(
                    $tenant,
                    $source,
                    $attributes,
                    ['tenant_id' => $tenant->id, 'sku' => $source->sku],
                    ['product', 'product-barcode']
                );

                if (!$copy instanceof Product) {
                    return;
                }

                $productMap[$source->id] = $copy->id;

                $this->seedProductTagCopies($tenant, $source, $copy);
                $this->seedProductTaxCopies($tenant, $source, $copy, $taxMap);
                $this->seedProductVideoCopies($tenant, $source, $copy);
                $this->seedProductSeoCopy($tenant, $source, $copy);
                $this->seedProductVariationCopies($tenant, $source, $copy, $attributeMap, $attributeOptionMap);
            });

        return $productMap;
    }

    private function seedProductTagCopies(Tenant $tenant, Product $sourceProduct, Product $copyProduct): void
    {
        ProductTag::query()
            ->where('product_id', $sourceProduct->id)
            ->orderBy('id')
            ->get()
            ->each(function (ProductTag $source) use ($tenant, $copyProduct): void {
                $this->seededCopy(
                    $tenant,
                    $source,
                    ['product_id' => $copyProduct->id, 'name' => $source->name],
                    ['product_id' => $copyProduct->id, 'name' => $source->name]
                );
            });
    }

    /**
     * @param  array<int, int>  $taxMap
     */
    private function seedProductTaxCopies(Tenant $tenant, Product $sourceProduct, Product $copyProduct, array $taxMap): void
    {
        $this->templateQuery(ProductTax::class)
            ->where('product_id', $sourceProduct->id)
            ->get()
            ->each(function (ProductTax $source) use ($tenant, $copyProduct, $taxMap): void {
                $taxId = $source->tax_id === null ? null : ($taxMap[$source->tax_id] ?? null);

                if ($source->tax_id !== null && $taxId === null) {
                    return;
                }

                $attributes = [
                    'tenant_id' => $tenant->id,
                    'product_id' => $copyProduct->id,
                    'tax_id' => $taxId,
                ];

                $this->seededCopy($tenant, $source, $attributes, $attributes);
            });
    }

    private function seedProductVideoCopies(Tenant $tenant, Product $sourceProduct, Product $copyProduct): void
    {
        $this->templateQuery(ProductVideo::class)
            ->where('product_id', $sourceProduct->id)
            ->get()
            ->each(function (ProductVideo $source) use ($tenant, $copyProduct): void {
                $attributes = $this->copyAttributes($tenant, $source, ['video_provider', 'link']);
                $attributes['product_id'] = $copyProduct->id;

                $this->seededCopy(
                    $tenant,
                    $source,
                    $attributes,
                    [
                        'tenant_id' => $tenant->id,
                        'product_id' => $copyProduct->id,
                        'link' => $source->link,
                    ]
                );
            });
    }

    private function seedProductSeoCopy(Tenant $tenant, Product $sourceProduct, Product $copyProduct): void
    {
        $source = $this->templateQuery(ProductSeo::class)
            ->where('product_id', $sourceProduct->id)
            ->orderBy('id')
            ->first();

        if (!$source instanceof ProductSeo) {
            return;
        }

        $attributes = $this->copyAttributes($tenant, $source, ['title', 'description', 'meta_keyword']);
        $attributes['product_id'] = $copyProduct->id;

        $this->seededCopy(
            $tenant,
            $source,
            $attributes,
            ['tenant_id' => $tenant->id, 'product_id' => $copyProduct->id],
            ['product-seo']
        );
    }

    /**
     * @param  array<int, int>  $attributeMap
     * @param  array<int, int>  $attributeOptionMap
     */
    private function seedProductVariationCopies(
        Tenant $tenant,
        Product $sourceProduct,
        Product $copyProduct,
        array $attributeMap,
        array $attributeOptionMap
    ): void {
        $variationMap = [];

        $this->templateQuery(ProductVariation::class)
            ->where('product_id', $sourceProduct->id)
            ->get()
            ->each(function (ProductVariation $source) use (
                $tenant,
                $copyProduct,
                $attributeMap,
                $attributeOptionMap,
                &$variationMap
            ): void {
                $this->seedProductVariationCopy($tenant, $source, $copyProduct, $attributeMap, $attributeOptionMap, $variationMap);
            });
    }

    /**
     * @param  array<int, int>  $attributeMap
     * @param  array<int, int>  $attributeOptionMap
     * @param  array<int, int|null>  $variationMap
     */
    private function seedProductVariationCopy(
        Tenant $tenant,
        ProductVariation $source,
        Product $copyProduct,
        array $attributeMap,
        array $attributeOptionMap,
        array &$variationMap
    ): ?ProductVariation {
        if (array_key_exists($source->id, $variationMap)) {
            $targetId = $variationMap[$source->id];

            return $targetId === null
                ? null
                : ProductVariation::withoutGlobalScopes()->whereKey($targetId)->first();
        }

        $attributeId = $attributeMap[$source->product_attribute_id] ?? null;
        $attributeOptionId = $attributeOptionMap[$source->product_attribute_option_id] ?? null;

        if ($attributeId === null || $attributeOptionId === null) {
            $variationMap[$source->id] = null;

            return null;
        }

        $parentId = null;

        if ($source->parent_id !== null) {
            $sourceParent = ProductVariation::withoutGlobalScopes()
                ->whereNull('tenant_id')
                ->whereKey($source->parent_id)
                ->first();

            if ($sourceParent !== null) {
                $parentId = $this->seedProductVariationCopy(
                    $tenant,
                    $sourceParent,
                    $copyProduct,
                    $attributeMap,
                    $attributeOptionMap,
                    $variationMap
                )?->id;
            }
        }

        $attributes = $this->copyAttributes($tenant, $source, ['price', 'sku', 'order']);
        $attributes['product_id'] = $copyProduct->id;
        $attributes['product_attribute_id'] = $attributeId;
        $attributes['product_attribute_option_id'] = $attributeOptionId;
        $attributes['parent_id'] = $parentId;

        $identity = [
            'tenant_id' => $tenant->id,
            'product_id' => $copyProduct->id,
            'product_attribute_id' => $attributeId,
            'product_attribute_option_id' => $attributeOptionId,
            'parent_id' => $parentId,
        ];

        if (filled($source->sku)) {
            $identity['sku'] = $source->sku;
        }

        $copy = $this->seededCopy($tenant, $source, $attributes, $identity, ['product-variation-barcode']);
        $variationMap[$source->id] = $copy?->id;

        return $copy instanceof ProductVariation ? $copy : null;
    }

    /**
     * @param  array<int, int>  $sectionMap
     * @param  array<int, int>  $productMap
     */
    private function seedProductSectionProductCopies(Tenant $tenant, array $sectionMap, array $productMap): void
    {
        $this->templateQuery(ProductSectionProduct::class)
            ->get()
            ->each(function (ProductSectionProduct $source) use ($tenant, $sectionMap, $productMap): void {
                $sectionId = $sectionMap[$source->product_section_id] ?? null;
                $productId = $productMap[$source->product_id] ?? null;

                if ($sectionId === null || $productId === null) {
                    return;
                }

                $attributes = [
                    'tenant_id' => $tenant->id,
                    'product_section_id' => $sectionId,
                    'product_id' => $productId,
                ];

                $this->seededCopy($tenant, $source, $attributes, $attributes);
            });
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string>  $columns
     * @param  array<int, string>  $identityColumns
     * @param  array<int, string>  $mediaCollections
     * @return array<int, int>
     */
    private function seedTenantCopies(
        Tenant $tenant,
        string $modelClass,
        array $columns,
        array $identityColumns,
        array $mediaCollections = []
    ): array {
        $map = [];

        $this->templateQuery($modelClass)
            ->get()
            ->each(function (Model $source) use ($tenant, $columns, $identityColumns, $mediaCollections, &$map): void {
                $attributes = $this->copyAttributes($tenant, $source, $columns);
                $identity = ['tenant_id' => $tenant->id];

                foreach ($identityColumns as $column) {
                    $identity[$column] = $attributes[$column] ?? null;
                }

                $copy = $this->seededCopy($tenant, $source, $attributes, $identity, $mediaCollections);

                if ($copy !== null) {
                    $map[(int) $source->getKey()] = (int) $copy->getKey();
                }
            });

        return $map;
    }

    /**
     * @param  array<int, string>  $columns
     * @return array<string, mixed>
     */
    private function copyAttributes(Tenant $tenant, Model $source, array $columns): array
    {
        $attributes = ['tenant_id' => $tenant->id];

        foreach ($columns as $column) {
            $attributes[$column] = $source->getAttribute($column);
        }

        return $attributes;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $identity
     * @param  array<int, string>  $mediaCollections
     */
    private function seededCopy(
        Tenant $tenant,
        Model $source,
        array $attributes,
        array $identity,
        array $mediaCollections = []
    ): ?Model {
        $modelClass = $source::class;

        if (!$this->tenantDemoSeedTrackingEnabled()) {
            /** @var Model $copy */
            $copy = $modelClass::withoutGlobalScopes()->firstOrCreate($identity, $attributes);

            $this->copyMediaCollections($source, $copy, $mediaCollections);

            return $copy;
        }

        $seed = TenantDemoContentSeed::query()
            ->where('tenant_id', $tenant->id)
            ->where('source_type', $modelClass)
            ->where('source_id', $source->getKey())
            ->first();

        if ($seed instanceof TenantDemoContentSeed) {
            return $this->seedTarget($seed);
        }

        /** @var Model $copy */
        $copy = $modelClass::withoutGlobalScopes()->firstOrCreate($identity, $attributes);

        $this->copyMediaCollections($source, $copy, $mediaCollections);

        TenantDemoContentSeed::query()->create([
            'tenant_id' => $tenant->id,
            'source_type' => $modelClass,
            'source_id' => $source->getKey(),
            'target_type' => $copy::class,
            'target_id' => $copy->getKey(),
        ]);

        return $copy;
    }

    private function tenantDemoSeedTrackingEnabled(): bool
    {
        if ($this->tenantDemoContentSeedTableExists !== null) {
            return $this->tenantDemoContentSeedTableExists;
        }

        return $this->tenantDemoContentSeedTableExists = Schema::hasTable((new TenantDemoContentSeed())->getTable());
    }

    private function seedTarget(TenantDemoContentSeed $seed): ?Model
    {
        if (!filled($seed->target_type) || !filled($seed->target_id)) {
            return null;
        }

        /** @var class-string<Model> $targetType */
        $targetType = $seed->target_type;
        $target = $targetType::withoutGlobalScopes()->whereKey($seed->target_id)->first();

        if (!$target instanceof Model || $this->isDeletedCopy($target)) {
            return null;
        }

        return $target;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function templateQuery(string $modelClass)
    {
        /** @var Model $model */
        $model = new $modelClass();
        $query = $modelClass::withoutGlobalScopes()
            ->whereNull($model->qualifyColumn('tenant_id'))
            ->orderBy($model->qualifyColumn('id'));

        if (in_array(SoftDeletes::class, class_uses_recursive($modelClass), true)) {
            $query->whereNull($model->qualifyColumn($model->getDeletedAtColumn()));
        }

        return $query;
    }

    /**
     * @param  array<int, string>  $collections
     */
    private function copyMediaCollections(Model $source, Model $copy, array $collections): void
    {
        if ($collections === [] || !method_exists($source, 'getMedia') || !method_exists($copy, 'addMedia')) {
            return;
        }

        foreach ($collections as $collection) {
            if ($copy->getMedia($collection)->isNotEmpty()) {
                continue;
            }

            $source->getMedia($collection)->each(function ($media) use ($copy, $collection): void {
                try {
                    $copy->addMedia($media->getPath())
                        ->preservingOriginal()
                        ->toMediaCollection($collection);
                } catch (\Throwable) {
                    // Default demo content still works with fallback images if a media file is missing.
                }
            });
        }
    }

    private function isDeletedCopy(Model $model): bool
    {
        return method_exists($model, 'trashed') && $model->trashed();
    }
}
