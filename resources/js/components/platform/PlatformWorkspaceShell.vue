<template>
    <div class="min-h-screen bg-[#F6F8FB] text-[#111827]" dir="ltr">
        <div class="mx-auto flex min-h-screen max-w-[1600px] flex-col lg:flex-row">
            <aside class="border-b border-[#E5E7EB] bg-white lg:sticky lg:top-0 lg:h-screen lg:w-[290px] lg:flex-none lg:border-b-0 lg:border-r">
                <div class="flex h-full flex-col px-5 py-6">
                    <div class="rounded-3xl bg-[#111827] px-5 py-6 text-white">
                        <p class="text-xs font-semibold uppercase tracking-[0.24em] text-[#FBBF24]">Owner Panel</p>
                        <h2 class="mt-3 text-2xl font-semibold">Hybrid Control Tower</h2>
                        <p class="mt-2 text-sm leading-6 text-[#D1D5DB]">
                            Global oversight for merchants, billing, domains, providers, support, and audit.
                        </p>
                    </div>

                    <nav class="mt-6 space-y-2">
                        <router-link
                            v-for="item in navigation"
                            :key="item.name"
                            :to="{ name: item.name }"
                            class="flex items-start justify-between gap-4 rounded-2xl px-4 py-3 text-sm transition"
                            :class="isActive(item.name) ? 'bg-[#FFF1EB] text-[#C2410C]' : 'text-[#374151] hover:bg-[#F9FAFB]'">
                            <div>
                                <p class="font-semibold">{{ item.label }}</p>
                                <p class="mt-1 text-xs" :class="isActive(item.name) ? 'text-[#EA580C]' : 'text-[#6B7280]'">
                                    {{ item.caption }}
                                </p>
                            </div>
                        </router-link>
                    </nav>

                    <div class="mt-auto rounded-3xl border border-[#E5E7EB] bg-[#F9FAFB] p-4">
                        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-[#6B7280]">Signed in</p>
                        <p class="mt-2 text-base font-semibold text-[#111827]">{{ authInfo.name || "Platform Owner" }}</p>
                        <p class="text-sm text-[#6B7280]">{{ authInfo.email || "owner@platform" }}</p>
                        <button
                            type="button"
                            class="mt-4 inline-flex h-11 w-full items-center justify-center rounded-2xl bg-primary px-4 text-sm font-semibold text-white transition hover:opacity-90"
                            @click="logout">
                            Logout
                        </button>
                    </div>
                </div>
            </aside>

            <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                <header class="rounded-3xl border border-[#E5E7EB] bg-white px-6 py-6 shadow-sm">
                    <div class="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-primary">Owner Control Tower</p>
                            <h1 class="mt-2 text-3xl font-semibold">{{ title }}</h1>
                            <p v-if="subtitle" class="mt-2 max-w-3xl text-sm leading-6 text-[#6B7280]">{{ subtitle }}</p>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2 xl:min-w-[360px]">
                            <div class="rounded-2xl bg-[#F9FAFB] px-4 py-4">
                                <p class="text-xs uppercase tracking-[0.16em] text-[#6B7280]">Workspace</p>
                                <p class="mt-2 font-semibold text-[#111827]">Global command center</p>
                            </div>
                            <div class="rounded-2xl bg-[#F9FAFB] px-4 py-4">
                                <p class="text-xs uppercase tracking-[0.16em] text-[#6B7280]">Principle</p>
                                <p class="mt-2 font-semibold text-[#111827]">Merchants stay isolated</p>
                            </div>
                        </div>
                    </div>
                </header>

                <div class="mt-6">
                    <slot />
                </div>
            </main>
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
                { name: "platform.dashboard", label: "Dashboard", caption: "Platform metrics and alerts" },
                { name: "platform.tenants", label: "Merchants", caption: "Directory, plans, support entry" },
                { name: "platform.customers", label: "Customers", caption: "Master customer relationships" },
                { name: "platform.domains", label: "Domains", caption: "Verification and SSL oversight" },
                { name: "platform.billing", label: "Billing", caption: "Plans, subscriptions, invoices" },
                { name: "platform.providers", label: "Providers", caption: "Payment, SMS, email, push" },
                { name: "platform.features", label: "Features", caption: "Plan and merchant gating" },
                { name: "platform.support", label: "Support", caption: "Impersonation and issue workflow" },
                { name: "platform.audit", label: "Audit", caption: "Owner-wide event trail" },
                { name: "platform.settings", label: "Platform Settings", caption: "Defaults and governance" },
            ];
        },
    },
    methods: {
        isActive: function (routeName) {
            if (
                (routeName === "platform.dashboard" && this.$route?.name === "platform.controlTower")
                || (routeName === "platform.controlTower" && this.$route?.name === "platform.dashboard")
            ) {
                return true;
            }

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
