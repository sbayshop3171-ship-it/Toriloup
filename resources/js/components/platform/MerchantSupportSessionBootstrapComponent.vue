<template>
    <div class="flex min-h-screen items-center justify-center bg-[#F6F8FB] px-4 py-10">
        <LoadingComponent :props="loading" />

        <section class="w-full max-w-xl rounded-3xl border border-[#E5E7EB] bg-white p-8 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-primary">Support Session</p>
            <h1 class="mt-3 text-3xl font-semibold text-[#111827]">Opening merchant workspace</h1>
            <p class="mt-3 text-sm leading-6 text-[#6B7280]">
                This session is audited and time-bounded. The merchant workspace will load with a visible support banner after access is granted.
            </p>

            <div v-if="errorMessage" class="mt-6 rounded-2xl border border-[#FECACA] bg-[#FEF2F2] px-4 py-4 text-sm text-[#B91C1C]">
                {{ errorMessage }}
            </div>

            <div v-else class="mt-6 rounded-2xl bg-[#F9FAFB] px-4 py-4 text-sm text-[#374151]">
                Validating session handoff and creating a merchant-only access token.
            </div>

            <div class="mt-6 flex flex-wrap gap-3">
                <button
                    type="button"
                    class="rounded-2xl bg-primary px-5 py-3 text-sm font-semibold text-white transition hover:opacity-90"
                    :disabled="loading.isActive"
                    @click="consumeSession">
                    Try again
                </button>
                <router-link
                    :to="{ name: 'auth.login' }"
                    class="rounded-2xl border border-[#D1D5DB] px-5 py-3 text-sm font-semibold text-[#374151] transition hover:border-primary hover:text-primary">
                    Go to merchant login
                </router-link>
            </div>
        </section>
    </div>
</template>

<script>
import axios from "axios";
import LoadingComponent from "../frontend/components/LoadingComponent.vue";

export default {
    name: "MerchantSupportSessionBootstrapComponent",
    components: {
        LoadingComponent,
    },
    data() {
        return {
            loading: {
                isActive: false,
            },
            errorMessage: "",
        };
    },
    mounted() {
        this.consumeSession();
    },
    methods: {
        consumeSession: function () {
            const handoffCode = this.$route.params.handoffCode;

            if (!handoffCode) {
                this.errorMessage = "Support session link is missing its handoff code.";
                return;
            }

            this.loading.isActive = true;
            this.errorMessage = "";

            axios.post("merchant/auth/support-sessions/consume", {
                handoff_code: handoffCode,
            }).then((res) => {
                this.$store.commit("authLogin", res.data);
                this.$router.replace({ name: "merchant.dashboard" });
            }).catch((error) => {
                this.errorMessage = error?.response?.data?.errors?.handoff_code?.[0]
                    || error?.response?.data?.message
                    || "The support session could not be opened.";
            }).finally(() => {
                this.loading.isActive = false;
            });
        },
    },
};
</script>
