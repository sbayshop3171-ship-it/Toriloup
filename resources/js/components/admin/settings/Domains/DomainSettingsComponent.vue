<template>
    <LoadingComponent :props="loading" />

    <div class="db-card mb-4">
        <div class="db-card-header">
            <div>
                <h3 class="db-card-title">Domains</h3>
                <p class="text-sm text-gray-500">Keep the fallback subdomain active while provisioning Cloudflare SaaS custom hostnames for your custom domains.</p>
            </div>
        </div>
        <div class="db-card-body">
            <form class="form-row settings-page-form" @submit.prevent="save">
                <div class="form-col-12 sm:form-col-8">
                    <label for="hostname" class="db-field-title required">Hostname</label>
                    <input id="hostname" v-model="form.hostname" type="text" class="db-field-control" :class="errors.hostname ? 'invalid' : ''" placeholder="store.yourdomain.com" />
                    <small v-if="errors.hostname" class="db-field-alert">{{ errors.hostname[0] }}</small>
                </div>
                <div class="form-col-12 sm:form-col-4">
                    <label for="dns_provider" class="db-field-title">DNS Provider</label>
                    <input id="dns_provider" v-model="form.dns_provider" type="text" class="db-field-control" placeholder="cloudflare" />
                </div>
                <div class="form-col-12 settings-sticky-submit">
                    <button type="submit" class="db-btn text-white bg-primary">
                        <i class="lab lab-fill-save"></i>
                        <span>Request Domain</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="grid gap-4">
        <div v-for="domain in domains" :key="domain.id" class="db-card">
            <div class="db-card-body">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div>
                        <div class="flex flex-wrap items-center gap-2">
                            <h4 class="text-base font-semibold text-gray-900">{{ domain.hostname }}</h4>
                            <span class="px-2 py-1 rounded-full text-xs font-medium bg-blue-50 text-blue-700 uppercase">{{ domain.domain_type }}</span>
                            <span v-if="domain.is_fallback" class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">Fallback</span>
                            <span v-if="domain.is_primary" class="px-2 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700">Primary</span>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">
                            Verification: <span class="font-medium capitalize">{{ domain.verification_status }}</span>
                            · SSL: <span class="font-medium capitalize">{{ domain.ssl_status }}</span>
                        </p>
                        <p v-if="domain.last_checked_at" class="mt-1 text-xs text-gray-400">
                            Last checked: {{ formatDate(domain.last_checked_at) }}
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="showCloudflareConnect(domain)"
                            type="button"
                            class="db-btn py-2 text-white bg-primary"
                            @click="connectCloudflare(domain)"
                        >
                            Provision Hostname
                        </button>
                        <button
                            v-if="showVerify(domain)"
                            type="button"
                            class="db-btn py-2 border border-gray-200 text-gray-700 bg-white"
                            @click="verifyDomain(domain)"
                        >
                            Check DNS
                        </button>
                        <button
                            v-if="showMakePrimary(domain)"
                            type="button"
                            class="db-btn py-2 text-white bg-primary"
                            @click="setPrimary(domain.id)"
                        >
                            Make Primary
                        </button>
                    </div>
                </div>

                <div v-if="domain.domain_type === 'custom'" class="mt-3 rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                    <p v-if="showCloudflareConnect(domain)">
                        One click provisions the Cloudflare custom hostname in the platform account. Add the CNAME at your DNS provider as DNS only.
                    </p>
                    <p v-else>
                        Add the CNAME below at your DNS provider, keep it DNS only if the provider is Cloudflare, then use Check DNS.
                    </p>
                </div>

                <div v-if="domain.domain_type === 'custom'" class="grid gap-3 mt-4 md:grid-cols-2">
                    <div class="p-3 rounded-lg bg-gray-50 border border-gray-200">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">CNAME Target</p>
                        <p class="mt-1 text-sm font-medium text-gray-900 break-all">{{ domain.dns_instructions?.cname_target || '-' }}</p>
                        <p class="mt-2 text-xs text-gray-500">Record type: {{ domain.dns_instructions?.record_type || 'CNAME' }} · Proxy: {{ domain.dns_instructions?.proxy_mode || 'DNS only' }}</p>
                    </div>
                    <div class="p-3 rounded-lg bg-gray-50 border border-gray-200">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Verification TXT</p>
                        <p class="mt-1 text-sm font-medium text-gray-900 break-all">{{ domain.dns_instructions?.verification_txt_value || '-' }}</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script>
import LoadingComponent from "../../components/LoadingComponent";
import alertService from "../../../../services/alertService";

export default {
    name: "DomainSettingsComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: {
                isActive: false,
            },
            form: {
                hostname: "",
                dns_provider: "cloudflare",
            },
            errors: {},
        };
    },
    computed: {
        domains: function () {
            return this.$store.getters["merchantDomain/lists"];
        },
    },
    mounted() {
        this.fetchDomains();
    },
    methods: {
        fetchDomains: function () {
            this.loading.isActive = true;
            this.$store.dispatch("merchantDomain/lists").then(() => {
                this.loading.isActive = false;
            }).catch(() => {
                this.loading.isActive = false;
            });
        },
        save: function () {
            this.loading.isActive = true;
            this.$store.dispatch("merchantDomain/save", this.form).then(() => {
                this.loading.isActive = false;
                this.form.hostname = "";
                this.errors = {};
                alertService.success("Domain request submitted successfully.");
            }).catch((err) => {
                this.loading.isActive = false;
                this.errors = err?.response?.data?.errors || {};
            });
        },
        setPrimary: function (id) {
            this.loading.isActive = true;
            this.$store.dispatch("merchantDomain/setPrimary", { id }).then(() => {
                this.loading.isActive = false;
                alertService.success("Primary domain updated.");
            }).catch((err) => {
                this.loading.isActive = false;
                alertService.error(err);
            });
        },
        connectCloudflare: function (domain) {
            this.loading.isActive = true;
            this.$store.dispatch("merchantDomain/connectCloudflare", { id: domain.id }).then((res) => {
                this.loading.isActive = false;
                if (res?.data?.meta?.verified) {
                    alertService.success("Cloudflare connected and storefront launched successfully.");
                    return;
                }

                alertService.info(res?.data?.meta?.message || "Cloudflare DNS connected. Waiting for storefront launch.");
            }).catch((err) => {
                this.loading.isActive = false;
                alertService.error(err);
            });
        },
        verifyDomain: function (domain) {
            this.loading.isActive = true;
            this.$store.dispatch("merchantDomain/verify", { id: domain.id }).then((res) => {
                this.loading.isActive = false;
                const verified = res?.data?.meta?.verified;
                if (verified) {
                    alertService.success("DNS verified successfully.");
                    return;
                }

                alertService.info(res?.data?.meta?.message || "DNS is still propagating. Please try again in a moment.");
            }).catch((err) => {
                this.loading.isActive = false;
                alertService.error(err);
            });
        },
        showCloudflareConnect: function (domain) {
            return domain.domain_type === "custom"
                && domain.cloudflare_connect_available
                && domain.verification_status !== "verified";
        },
        showVerify: function (domain) {
            return domain.domain_type === "custom" && domain.verification_status !== "verified";
        },
        showMakePrimary: function (domain) {
            return !domain.is_primary && domain.verification_status === "verified";
        },
        formatDate: function (value) {
            if (!value) {
                return "";
            }

            return new Date(value).toLocaleString();
        },
    },
};
</script>
