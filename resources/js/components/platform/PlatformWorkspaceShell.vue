<template>
    <div class="min-h-screen bg-[#F6F8FB] text-[#111827]" dir="ltr">
        <header class="border-b border-[#E5E7EB] bg-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.24em] text-primary">Owner Control Tower</p>
                    <h1 class="text-xl font-semibold">{{ title }}</h1>
                    <p v-if="subtitle" class="text-sm text-[#6B7280]">{{ subtitle }}</p>
                </div>
                <div class="flex items-center gap-3">
                    <div class="hidden rounded-xl border border-[#E5E7EB] bg-[#F9FAFB] px-4 py-2 text-right sm:block">
                        <p class="text-xs uppercase tracking-[0.18em] text-[#6B7280]">Signed in</p>
                        <p class="text-sm font-semibold">{{ authInfo.name || "Platform Owner" }}</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center rounded-xl bg-primary px-4 text-sm font-semibold text-white transition hover:opacity-90"
                        @click="logout">
                        Logout
                    </button>
                </div>
            </div>
        </header>

        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
            <nav class="mb-6 flex flex-wrap gap-2">
                <router-link
                    v-for="item in navigation"
                    :key="item.name"
                    :to="{ name: item.name }"
                    class="inline-flex items-center rounded-full border px-4 py-2 text-sm font-medium transition"
                    :class="isActive(item.name) ? 'border-primary bg-primary text-white' : 'border-[#D1D5DB] bg-white text-[#374151] hover:border-primary hover:text-primary'">
                    {{ item.label }}
                </router-link>
            </nav>

            <slot />
        </div>
    </div>
</template>

<script>
import { resolveGuestHomeRoute } from "../../services/workspaceService";

export default {
    name: "PlatformWorkspaceShell",
    props: {
        title: {
            type: String,
            required: true,
        },
        subtitle: {
            type: String,
            default: "",
        },
    },
    computed: {
        authInfo: function () {
            return this.$store.getters.authInfo || {};
        },
        navigation: function () {
            return [
                { name: "platform.dashboard", label: "Main Admin" },
                { name: "platform.controlTower", label: "Control Tower" },
                { name: "platform.tenants", label: "Tenants" },
                { name: "platform.domains", label: "Domains" },
                { name: "platform.billing", label: "Plans & Billing" },
                { name: "platform.providers", label: "Providers" },
                { name: "platform.features", label: "Feature Control" },
                { name: "platform.support", label: "Support" },
                { name: "platform.audit", label: "Audit & Security" },
                { name: "platform.settings", label: "Platform Settings" },
            ];
        },
    },
    methods: {
        isActive: function (routeName) {
            return this.$route?.name === routeName;
        },
        logout: function () {
            this.$store.dispatch("logout").finally(() => {
                this.$router.push(resolveGuestHomeRoute());
            });
        },
    },
};
</script>
