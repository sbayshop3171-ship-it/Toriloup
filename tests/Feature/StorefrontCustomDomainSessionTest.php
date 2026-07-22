<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\TenantDomain;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontCustomDomainSessionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'saas.root_domain' => 'company.com',
            'saas.marketing_host' => 'company.com',
            'saas.owner_host' => 'owner.company.com',
            'saas.owner_host_aliases' => [],
            'saas.merchant_host' => 'merchant.company.com',
            'saas.fallback_subdomain_suffix' => 'company.com',
            'session.domain' => '.company.com',
        ]);
    }

    public function test_storefront_subdomain_keeps_shared_session_cookie_domain(): void
    {
        $tenant = $this->createTenant('cookie-subdomain-store');

        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'hostname' => 'cookie-subdomain-store.company.com',
            'domain_type' => 'subdomain',
            'is_primary' => true,
            'is_fallback' => true,
            'ssl_status' => 'active',
            'verification_status' => 'verified',
        ]);

        $response = $this->get('http://cookie-subdomain-store.company.com/_tenant/up');

        $response->assertOk();

        $this->assertSame('.company.com', $this->cookieDomain($response, config('session.cookie')));
        $this->assertSame('.company.com', $this->cookieDomain($response, 'XSRF-TOKEN'));
    }

    public function test_storefront_custom_domain_uses_host_only_session_cookies(): void
    {
        $tenant = $this->createTenant('cookie-custom-store');

        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'hostname' => 'cookie-custom-store.company.com',
            'domain_type' => 'subdomain',
            'is_primary' => false,
            'is_fallback' => true,
            'ssl_status' => 'active',
            'verification_status' => 'verified',
        ]);

        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'hostname' => 'brand.example.test',
            'domain_type' => 'custom',
            'is_primary' => true,
            'is_fallback' => false,
            'ssl_status' => 'active',
            'verification_status' => 'verified',
        ]);

        $response = $this->get('http://brand.example.test/_tenant/up');

        $response->assertOk();

        $this->assertContains($this->cookieDomain($response, config('session.cookie')), [null, '']);
        $this->assertContains($this->cookieDomain($response, 'XSRF-TOKEN'), [null, '']);
    }

    public function test_www_custom_domain_resolves_to_verified_apex_custom_domain(): void
    {
        $tenant = $this->createTenant('www-custom-store');

        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'hostname' => 'www-custom-store.company.com',
            'domain_type' => 'subdomain',
            'is_primary' => false,
            'is_fallback' => true,
            'ssl_status' => 'active',
            'verification_status' => 'verified',
        ]);

        TenantDomain::query()->create([
            'tenant_id' => $tenant->id,
            'hostname' => 'launchstore.test',
            'domain_type' => 'custom',
            'is_primary' => true,
            'is_fallback' => false,
            'ssl_status' => 'active',
            'verification_status' => 'verified',
        ]);

        $response = $this->get('http://www.launchstore.test/_tenant/up');

        $response->assertOk()
            ->assertJsonPath('tenant.slug', 'www-custom-store');
    }

    private function createTenant(string $slug): Tenant
    {
        return Tenant::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => Str::headline(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'store_code' => strtoupper(Str::substr(Str::replace('-', '', $slug), 0, 6)),
            'status' => 'active',
            'onboarding_status' => 'basic_complete',
            'primary_locale' => 'en',
            'primary_currency_code' => 'USD',
            'timezone' => 'UTC',
        ]);
    }

    private function cookieDomain(\Illuminate\Testing\TestResponse $response, string $cookieName): ?string
    {
        $cookie = collect($response->headers->getCookies())
            ->first(fn (\Symfony\Component\HttpFoundation\Cookie $cookie) => $cookie->getName() === $cookieName);

        $this->assertNotNull($cookie, "Cookie [{$cookieName}] was not set on the response.");

        return $cookie->getDomain();
    }
}
