<template>
    <LoadingComponent :props="loading" />
    <div class="w-full max-w-xl mx-auto rounded-2xl overflow-hidden bg-white shadow-card mb-24 !sm:mb-0">
        <form class="w-full p-6" @submit.prevent="register">
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
                <input v-model="form.owner_name" id="ownerName" type="text"
                    :class="errors.owner_name ? 'invalid' : ''"
                    class="w-full h-12 px-4 rounded-lg text-base border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500" />
                <small class="db-field-alert" v-if="errors.owner_name">{{ errors.owner_name[0] }}</small>
            </div>

            <div class="mb-5">
                <label for="storeName" class="text-sm font-medium capitalize mb-1 field-title required">Store name</label>
                <input v-model="form.store_name" id="storeName" type="text"
                    :class="errors.store_name ? 'invalid' : ''"
                    class="w-full h-12 px-4 rounded-lg text-base border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500" />
                <small class="db-field-alert" v-if="errors.store_name">{{ errors.store_name[0] }}</small>
            </div>

            <div class="mb-5">
                <label for="storeSlug" class="text-sm font-medium capitalize mb-1 field-title required">Store slug</label>
                <input v-model="form.store_slug" id="storeSlug" type="text"
                    :class="errors.store_slug ? 'invalid' : ''"
                    class="w-full h-12 px-4 rounded-lg text-base border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500" />
                <small class="db-field-alert" v-if="errors.store_slug">{{ errors.store_slug[0] }}</small>
                <small class="block mt-1 text-xs text-text" v-if="form.store_slug">
                    Default storefront: {{ form.store_slug }}.{{ storefrontSuffix }}
                </small>
            </div>

            <div class="mb-5">
                <label for="email" class="text-sm font-medium capitalize mb-1 field-title required">Email</label>
                <input v-model="form.email" id="email" type="email"
                    :class="errors.email ? 'invalid' : ''"
                    class="w-full h-12 px-4 rounded-lg text-base border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500" />
                <small class="db-field-alert" v-if="errors.email">{{ errors.email[0] }}</small>
            </div>

            <div class="mb-6">
                <label for="password" class="text-sm font-medium capitalize mb-1 field-title required">Password</label>
                <input v-model="form.password" id="password" type="password"
                    :class="errors.password ? 'invalid' : ''"
                    class="w-full h-12 px-4 rounded-lg text-base border border-[#D9DBE9] hover:border-primary/30 focus-within:border-primary/30 transition-all duration-500" />
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
</template>

<script>
import LoadingComponent from "../components/LoadingComponent";
import alertService from "../../../services/alertService";
import ENV from "../../../config/env";

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
                store_slug: "",
                email: "",
                password: "",
                primary_locale: "en",
                primary_currency_code: "USD",
                timezone: "UTC",
                plan_code: "starter",
            },
            errors: {},
            storefrontSuffix: ENV.MARKETING_HOST || "toriloup.com",
        };
    },
    watch: {
        "form.store_name": function (value) {
            if (this.form.store_slug) {
                return;
            }

            this.form.store_slug = this.slugify(value);
        },
    },
    methods: {
        slugify(value) {
            return String(value || "")
                .toLowerCase()
                .trim()
                .replace(/[^a-z0-9]+/g, "-")
                .replace(/^-+|-+$/g, "")
                .slice(0, 120);
        },
        register() {
            this.loading.isActive = true;
            this.errors = {};

            this.$store.dispatch("merchantRegister", this.form).then((res) => {
                this.loading.isActive = false;
                alertService.success(res.data.message || "Store created successfully.");
                this.$router.push({ name: "admin.dashboard" });
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
