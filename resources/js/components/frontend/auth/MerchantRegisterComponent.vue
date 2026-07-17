<template>
    <LoadingComponent :props="loading" />
    <section class="w-full min-h-screen flex items-center justify-center px-4 py-8 sm:py-10" dir="ltr">
        <div class="w-full max-w-3xl rounded-2xl flex overflow-hidden gap-y-6 bg-white shadow-card" dir="ltr">
            <img :src="APP_URL + '/images/required/auth.jpg'" alt="banners"
                class="w-full hidden sm:block sm:max-w-xs md:max-w-sm flex-shrink-0">
        <form class="w-full p-6" dir="ltr" @submit.prevent="register">
            <div class="text-center mb-8">
                <h3 class="capitalize text-2xl mb-2 font-bold text-primary">Create Merchant Store</h3>
                <p class="font-medium text-text">Create your account and default storefront.</p>
                <div v-if="errors.validation"
                    class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 mt-5 rounded relative" role="alert">
                    <span class="block sm:inline">{{ errors.validation }}</span>
                    <span class="absolute top-0 bottom-0 right-0 px-4 py-3 cursor-pointer" @click="errors = {}">
                        <i class="lab lab-close-circle-line margin-top-5-px"></i>
                    </span>
                </div>
            </div>

            <div class="mb-5">
                <label for="ownerName" class="text-sm font-medium capitalize mb-1 field-title required">Owner name</label>
                <input v-model="form.owner_name" id="ownerName" type="text" dir="ltr"
                    :class="errors.owner_name ? 'invalid' : ''"
                    class="w-full h-12 px-4 rounded-lg text-base text-left border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500" />
                <small class="db-field-alert" v-if="errors.owner_name">{{ errors.owner_name[0] }}</small>
            </div>

            <div class="mb-5">
                <label for="storeName" class="text-sm font-medium capitalize mb-1 field-title required">Store name</label>
                <input v-model="form.store_name" id="storeName" type="text" dir="ltr"
                    :class="errors.store_name ? 'invalid' : ''"
                    class="w-full h-12 px-4 rounded-lg text-base text-left border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500" />
                <small class="db-field-alert" v-if="errors.store_name">{{ errors.store_name[0] }}</small>
                <small class="db-field-alert" v-if="errors.store_slug">{{ errors.store_slug[0] }}</small>
                <small class="block mt-1 text-xs text-text text-left" dir="ltr" v-if="generatedStoreSlug">
                    Default storefront: {{ generatedStoreSlug }}.{{ storefrontSuffix }}
                </small>
            </div>

            <div class="mb-5">
                <label for="email" class="text-sm font-medium capitalize mb-1 field-title required">Email</label>
                <input v-model="form.email" id="email" type="email" dir="ltr"
                    :class="errors.email ? 'invalid' : ''"
                    class="w-full h-12 px-4 rounded-lg text-base text-left border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500" />
                <small class="db-field-alert" v-if="errors.email">{{ errors.email[0] }}</small>
            </div>

            <div class="mb-6">
                <label for="password" class="text-sm font-medium capitalize mb-1 field-title required">Password</label>
                <input v-model="form.password" id="password" type="password" dir="ltr"
                    :class="errors.password ? 'invalid' : ''"
                    class="w-full h-12 px-4 rounded-lg text-base text-left border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500" />
                <small class="db-field-alert" v-if="errors.password">{{ errors.password[0] }}</small>
            </div>

            <button type="submit"
                class="font-bold text-center w-full h-12 leading-12 rounded-full bg-primary text-white capitalize mb-6">
                Create Store
            </button>
            <div class="flex items-center justify-center gap-1.5">
                <span class="font-medium text-text">Already have a merchant account?</span>
                <router-link class="capitalize font-bold text-primary" :to="{ name: 'auth.login' }">
                    Sign in
                </router-link>
            </div>
        </form>
        </div>
    </section>
</template>

<script>
import LoadingComponent from "../components/LoadingComponent";
import alertService from "../../../services/alertService";
import ENV from "../../../config/env";
import { resolveWorkspaceDashboardRoute } from "../../../services/workspaceService";

export default {
    name: "MerchantRegisterComponent",
    components: { LoadingComponent },
    data() {
        return {
            loading: {
                isActive: false,
            },
            form: {
                owner_name: "",
                store_name: "",
                email: "",
                password: "",
                primary_locale: "en",
                primary_currency_code: "USD",
                timezone: "UTC",
                plan_code: "starter",
            },
            errors: {},
            storefrontSuffix: ENV.STOREFRONT_SUFFIX || ENV.MARKETING_HOST || "toriloup.com",
            APP_URL: ENV.API_URL,
        };
    },
    computed: {
        generatedStoreSlug: function () {
            return this.slugify(this.form.store_name);
        },
    },
    methods: {
        slugify(value) {
            const slug = String(value || "")
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9]+/g, "-")
                .replace(/^-+|-+$/g, "")
                .slice(0, 120);

            return slug || (String(value || "").trim() ? "store" : "");
        },
        register() {
            this.loading.isActive = true;
            this.errors = {};

            this.$store.dispatch("merchantRegister", this.form).then((res) => {
                this.loading.isActive = false;
                alertService.success(res.data.message || "Store created successfully.");
                this.$router.push(resolveWorkspaceDashboardRoute("merchant"));
            }).catch((err) => {
                this.loading.isActive = false;
                this.errors = err?.response?.data?.errors || {
                    validation: err?.response?.data?.message || "Store registration failed.",
                };
            });
        },
    },
};
</script>
