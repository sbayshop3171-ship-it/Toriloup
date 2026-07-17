<template>
    <section class="min-h-screen bg-[#F6F8FB] px-4 py-10 text-[#111827]" dir="ltr">
        <div class="mx-auto max-w-3xl rounded-3xl border border-[#E5E7EB] bg-white p-8 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-primary">Merchant Workspace</p>
            <h1 class="mt-3 text-3xl font-bold">Merchant panel is paused for setup</h1>
            <p class="mt-3 text-base leading-7 text-[#6B7280]">
                Your store account is created, but daily store-operation tools are being prepared by the owner team.
                For now, owner/admin controls remain on the owner workspace.
            </p>

            <div class="mt-6 rounded-2xl border border-[#E5E7EB] bg-[#F9FAFB] p-5">
                <h2 class="text-lg font-semibold">What works now</h2>
                <ul class="mt-3 space-y-2 text-sm text-[#374151]">
                    <li>Merchant login and registration can continue.</li>
                    <li>Tenant/account records stay separate for each merchant.</li>
                    <li>Store operation menus will be opened module-by-module when owner approves them.</li>
                </ul>
            </div>

            <button
                type="button"
                class="mt-6 inline-flex h-11 items-center justify-center rounded-xl bg-primary px-5 text-sm font-semibold text-white"
                @click="logout">
                Logout
            </button>
        </div>
    </section>
</template>

<script>
import { resolveGuestHomeRoute } from "../../services/workspaceService";

export default {
    name: "MerchantPausedComponent",
    methods: {
        logout: function () {
            this.$store.dispatch("logout").finally(() => {
                this.$router.push(resolveGuestHomeRoute());
            });
        },
    },
};
</script>
