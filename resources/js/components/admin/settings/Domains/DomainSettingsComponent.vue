<template>
    <LoadingComponent :props="loading" />

    <div class="db-card mb-4">
        <div class="db-card-header">
            <div>
                <h3 class="db-card-title">Domains</h3>
                <p class="text-sm text-gray-500">Connect a root domain with Cloudflare nameservers, or keep the CNAME flow for subdomains.</p>
            </div>
        </div>
        <div class="db-card-body">
            <form class="form-row settings-page-form" @submit.prevent="save">
                <div class="form-col-12">
                    <label class="db-field-title">Connection Type</label>
                    <div class="inline-flex flex-wrap gap-2 rounded-lg border border-gray-200 bg-gray-50 p-1">
                        <button
                            type="button"
                            class="rounded-md px-3 py-2 text-sm font-medium transition"
                            :class="form.dns_setup_mode === 'full_zone' ? 'bg-white text-primary shadow-sm' : 'text-gray-600'"
                            @click="selectSetupMode('full_zone')"
                        >
                            Full domain
                        </button>
                        <button
                            type="button"
                            class="rounded-md px-3 py-2 text-sm font-medium transition"
                            :class="form.dns_setup_mode === 'cname' ? 'bg-white text-primary shadow-sm' : 'text-gray-600'"
                            @click="selectSetupMode('cname')"
                        >
                            Subdomain CNAME
                        </button>
                    </div>
                </div>
                <div class="form-col-12 sm:form-col-8">
                    <label for="hostname" class="db-field-title required">Hostname</label>
                    <input id="hostname" v-model="form.hostname" type="text" class="db-field-control" :class="errors.hostname ? 'invalid' : ''" :placeholder="hostnamePlaceholder" />
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
                        <p v-if="isAutoCheckCandidate(domain)" class="mt-1 text-xs font-medium text-blue-600">
                            Auto-checking every 15 seconds until this domain is live.
                        </p>
                    </div>

                    <div class="flex flex-wrap gap-2">
                        <button
                            v-if="showCloudflareConnect(domain)"
                            type="button"
                            class="db-btn py-2 text-white bg-primary"
                            @click="connectCloudflare(domain)"
                        >
                            {{ cloudflareButtonLabel(domain) }}
                        </button>
                        <button
                            v-if="showVerify(domain)"
                            type="button"
                            class="db-btn py-2 border border-gray-200 text-gray-700 bg-white"
                            @click="verifyDomain(domain)"
                        >
                            {{ verifyButtonLabel(domain) }}
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

                <div v-if="domain.domain_type === 'custom' && isFullZone(domain)" class="mt-3 rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                    <p v-if="domain.cloudflare_name_servers?.length">
                        Use these exact nameservers at your domain registrar. Do not use old Cloudflare nameservers from another zone.
                    </p>
                    <p v-else>
                        Create the Cloudflare DNS zone to receive the exact nameservers for this domain.
                    </p>
                </div>

                <div v-else-if="domain.domain_type === 'custom'" class="mt-3 rounded-lg border border-dashed border-gray-200 bg-gray-50 px-3 py-2 text-xs text-gray-600">
                    <p v-if="showCloudflareConnect(domain)">
                        One click provisions the Cloudflare custom hostname in the platform account. Add the CNAME at your DNS provider as DNS only.
                    </p>
                    <p v-else>
                        Add the CNAME below at your DNS provider, keep it DNS only if the provider is Cloudflare, then use Check DNS.
                    </p>
                </div>

                <div v-if="domain.domain_type === 'custom' && isFullZone(domain)" class="grid gap-3 mt-4 md:grid-cols-2">
                    <div class="p-3 rounded-lg bg-gray-50 border border-gray-200">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Cloudflare Nameservers</p>
                        <div v-if="domain.cloudflare_name_servers?.length" class="mt-2 space-y-2">
                            <div
                                v-for="nameserver in domain.cloudflare_name_servers"
                                :key="nameserver"
                                class="flex items-center justify-between gap-2 rounded-md border border-gray-200 bg-white px-3 py-2"
                            >
                                <p class="min-w-0 text-sm font-medium text-gray-900 break-all">{{ nameserver }}</p>
                                <button
                                    type="button"
                                    class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-gray-200 bg-white text-gray-600 transition hover:border-primary hover:text-primary"
                                    :title="copiedNameserver === nameserver ? 'Copied' : 'Copy nameserver'"
                                    @click="copyNameserver(nameserver)"
                                >
                                    <i :class="copiedNameserver === nameserver ? 'fa-solid fa-check' : 'fa-regular fa-copy'"></i>
                                </button>
                            </div>
                            <ul class="mt-3 space-y-1 text-xs text-gray-500">
                                <li>DNS update may take up to 48 hours.</li>
                                <li>Do not use old Cloudflare nameservers.</li>
                                <li>If you use email, old MX/TXT records need to be added in Cloudflare.</li>
                            </ul>
                        </div>
                        <p v-else class="mt-1 text-sm font-medium text-gray-900">Create DNS zone first</p>
                    </div>
                    <div class="p-3 rounded-lg bg-gray-50 border border-gray-200">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Zone Status</p>
                        <p class="mt-1 text-sm font-medium text-gray-900 capitalize">{{ domain.cloudflare_zone_status || 'not created' }}</p>
                        <p class="mt-2 text-xs text-gray-500">Records: apex and www route to {{ domain.dns_instructions?.cname_target || 'storefront fallback' }}</p>
                    </div>
                </div>

                <div v-else-if="domain.domain_type === 'custom'" class="grid gap-3 mt-4 md:grid-cols-2">
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
                dns_setup_mode: "full_zone",
            },
            errors: {},
            copiedNameserver: "",
            autoCheckTimer: null,
            autoCheckingIds: {},
        };
    },
    computed: {
        domains: function () {
            return this.$store.getters["merchantDomain/lists"];
        },
        hostnamePlaceholder: function () {
            return this.form.dns_setup_mode === "full_zone" ? "yourdomain.com" : "store.yourdomain.com";
        },
    },
    mounted() {
        this.fetchDomains().then(() => {
            this.startAutoCheck();
        }).catch(() => {
            this.startAutoCheck();
        });
    },
    beforeUnmount() {
        this.stopAutoCheck();
    },
    methods: {
        fetchDomains: function (options = {}) {
            const silent = Boolean(options.silent);

            if (!silent) {
                this.loading.isActive = true;
            }

            return this.$store.dispatch("merchantDomain/lists").then((res) => {
                if (!silent) {
                    this.loading.isActive = false;
                }

                return res;
            }).catch((err) => {
                if (!silent) {
                    this.loading.isActive = false;
                }

                throw err;
            });
        },
        save: function () {
            this.loading.isActive = true;
            this.$store.dispatch("merchantDomain/save", this.form).then(() => {
                this.loading.isActive = false;
                this.form.hostname = "";
                this.errors = {};
                alertService.success("Domain request submitted successfully.");
                this.scheduleAutoCheck();
            }).catch((err) => {
                this.loading.isActive = false;
                this.errors = err?.response?.data?.errors || {};
            });
        },
        selectSetupMode: function (mode) {
            this.form.dns_setup_mode = mode;
            this.form.dns_provider = "cloudflare";
            this.errors = {};
        },
        setPrimary: function (id) {
            this.loading.isActive = true;
            this.$store.dispatch("merchantDomain/setPrimary", { id }).then(() => {
                this.loading.isActive = false;
                alertService.success("Primary domain updated.");
            }).catch((err) => {
                this.loading.isActive = false;
                alertService.error(this.extractMessage(err));
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
                this.scheduleAutoCheck();
            }).catch((err) => {
                this.loading.isActive = false;
                alertService.error(this.extractMessage(err));
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
                this.scheduleAutoCheck();
            }).catch((err) => {
                this.loading.isActive = false;
                alertService.error(this.extractMessage(err));
            });
        },
        startAutoCheck: function () {
            this.stopAutoCheck();
            this.runAutoCheck();
            this.autoCheckTimer = window.setInterval(() => {
                this.runAutoCheck();
            }, 15000);
        },
        stopAutoCheck: function () {
            if (this.autoCheckTimer) {
                window.clearInterval(this.autoCheckTimer);
                this.autoCheckTimer = null;
            }
        },
        scheduleAutoCheck: function () {
            window.setTimeout(() => {
                this.runAutoCheck();
            }, 3000);
        },
        runAutoCheck: function () {
            this.domains
                .filter((domain) => this.isAutoCheckCandidate(domain))
                .forEach((domain) => this.autoVerifyDomain(domain));
        },
        autoVerifyDomain: function (domain) {
            if (this.autoCheckingIds[domain.id]) {
                return;
            }

            this.autoCheckingIds = {
                ...this.autoCheckingIds,
                [domain.id]: true,
            };

            this.$store.dispatch("merchantDomain/verify", { id: domain.id }).then((res) => {
                if (res?.data?.meta?.verified) {
                    alertService.success(`${domain.hostname} is live now.`);
                }
            }).catch(() => {
                // Manual checks still show detailed errors; background checks stay quiet.
            }).finally(() => {
                const autoCheckingIds = { ...this.autoCheckingIds };
                delete autoCheckingIds[domain.id];
                this.autoCheckingIds = autoCheckingIds;
            });
        },
        showCloudflareConnect: function (domain) {
            return domain.domain_type === "custom"
                && domain.cloudflare_connect_available
                && domain.verification_status !== "verified";
        },
        showVerify: function (domain) {
            return domain.domain_type === "custom"
                && domain.verification_status !== "verified"
                && (!this.isFullZone(domain) || Boolean(domain.cloudflare_zone_id));
        },
        showMakePrimary: function (domain) {
            return !domain.is_primary && domain.verification_status === "verified";
        },
        isFullZone: function (domain) {
            return domain.dns_setup_mode === "full_zone";
        },
        isAutoCheckCandidate: function (domain) {
            return domain.domain_type === "custom"
                && this.isFullZone(domain)
                && Boolean(domain.cloudflare_zone_id)
                && domain.verification_status !== "verified";
        },
        cloudflareButtonLabel: function (domain) {
            if (!this.isFullZone(domain)) {
                return "Provision Hostname";
            }

            return domain.cloudflare_zone_id ? "Refresh Zone" : "Get Nameservers";
        },
        verifyButtonLabel: function (domain) {
            return this.isFullZone(domain) ? "Check Nameservers" : "Check DNS";
        },
        copyNameserver: function (nameserver) {
            if (!nameserver) {
                return;
            }

            const done = () => {
                this.copiedNameserver = nameserver;
                alertService.success("Nameserver copied.");
                window.setTimeout(() => {
                    if (this.copiedNameserver === nameserver) {
                        this.copiedNameserver = "";
                    }
                }, 1800);
            };

            if (navigator?.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(nameserver).then(done).catch(() => {
                    this.copyWithTextarea(nameserver, done);
                });
                return;
            }

            this.copyWithTextarea(nameserver, done);
        },
        copyWithTextarea: function (value, done) {
            const textarea = document.createElement("textarea");
            textarea.value = value;
            textarea.setAttribute("readonly", "readonly");
            textarea.style.position = "fixed";
            textarea.style.opacity = "0";
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand("copy");
            document.body.removeChild(textarea);
            done();
        },
        formatDate: function (value) {
            if (!value) {
                return "";
            }

            return new Date(value).toLocaleString();
        },
        extractMessage: function (err) {
            return err?.response?.data?.message
                || err?.response?.data?.meta?.message
                || Object.values(err?.response?.data?.errors || {}).flat()?.[0]
                || err?.message
                || "Operation failed. Please try again.";
        },
    },
};
</script>
