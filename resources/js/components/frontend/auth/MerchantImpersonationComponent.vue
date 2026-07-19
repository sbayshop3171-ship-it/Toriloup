<template>
    <div class="flex min-h-screen items-center justify-center bg-[#F6F8FB] px-4">
        <LoadingComponent :props="loading" />
        <div class="w-full max-w-md rounded-2xl border border-[#E5E7EB] bg-white p-6 text-center shadow-sm">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-xl border border-[#DDD6FE] bg-[#F5F3FF] text-[#6D28D9]">
                <i class="fa-solid fa-arrow-right-to-bracket text-xl"></i>
            </div>
            <h1 class="mt-4 text-xl font-semibold text-[#111827]">Opening Merchant Workspace</h1>
            <p class="mt-2 text-sm leading-6 text-[#6B7280]">{{ message }}</p>
            <button
                v-if="failed"
                type="button"
                class="mt-5 inline-flex h-10 items-center justify-center rounded-xl bg-primary px-4 text-sm font-semibold text-white"
                @click="$router.replace({ name: 'auth.login' })">
                Back to login
            </button>
        </div>
    </div>
</template>

<script>
import axios from "axios";
import LoadingComponent from "../components/LoadingComponent.vue";

export default {
    name: "MerchantImpersonationComponent",
    components: {
        LoadingComponent,
    },
    data() {
        return {
            loading: {
                isActive: true,
            },
            failed: false,
            message: "Verifying the secure admin handoff.",
        };
    },
    mounted() {
        this.exchangeToken();
    },
    methods: {
        exchangeToken: function () {
            axios.post("merchant/auth/impersonate", {
                token: this.$route.params.token,
            }).then((res) => {
                this.$store.commit("authLogin", res.data);
                this.message = "Session ready. Redirecting...";
                this.$router.replace({ name: "merchant.dashboard" });
            }).catch((error) => {
                this.failed = true;
                this.message = error?.response?.data?.message || "This handoff link is no longer valid.";
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
    },
};
</script>
