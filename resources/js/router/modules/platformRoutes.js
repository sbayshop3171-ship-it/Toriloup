const LegacyAdminDashboardComponent = () => import("../../components/admin/dashboard/DashboardComponent");
const PlatformDashboardComponent = () => import("../../components/platform/PlatformDashboardComponent.vue");
const PlatformOperationsComponent = () => import("../../components/platform/PlatformOperationsComponent.vue");
const PlatformTenantsComponent = () => import("../../components/platform/PlatformTenantsComponent.vue");

const operationRoutes = [
    {
        path: "/owner/domains",
        name: "platform.domains",
        section: "domains",
        title: "Domain Center",
        subtitle: "Requested domains, verification, SSL status, DNS instructions, and fallback visibility.",
    },
    {
        path: "/owner/billing",
        name: "platform.billing",
        section: "billing",
        title: "Plans & Billing",
        subtitle: "Plan catalog, subscriptions, invoice oversight, renewal state, and owner override workflow.",
    },
    {
        path: "/owner/providers",
        name: "platform.providers",
        section: "providers",
        title: "Provider Center",
        subtitle: "Master payment, SMS, email, push, and API provider control.",
    },
    {
        path: "/owner/features",
        name: "platform.features",
        section: "features",
        title: "Feature Control",
        subtitle: "Tenant modules, merchant modes, presets, and controlled rollouts.",
    },
    {
        path: "/owner/support",
        name: "platform.support",
        section: "support",
        title: "Support Desk",
        subtitle: "Merchant visibility, support workflow, and audited impersonation policy.",
    },
    {
        path: "/owner/audit",
        name: "platform.audit",
        section: "audit",
        title: "Audit & Security",
        subtitle: "Owner actions, impersonation trail, domain changes, and subscription changes.",
    },
    {
        path: "/owner/settings",
        name: "platform.settings",
        section: "settings",
        title: "Platform Settings",
        subtitle: "Brand defaults, legal defaults, localization defaults, and release controls.",
    },
];

export default [
    {
        path: "/owner/dashboard",
        component: LegacyAdminDashboardComponent,
        name: "platform.dashboard",
        meta: {
            isFrontend: false,
            auth: true,
            permissionUrl: "dashboard",
            breadcrumb: "dashboard",
        },
    },
    {
        path: "/owner/control-tower",
        component: PlatformDashboardComponent,
        name: "platform.controlTower",
        meta: {
            isFrontend: false,
            auth: true,
            workspace: "platform",
        },
    },
    {
        path: "/owner/tenants",
        component: PlatformTenantsComponent,
        name: "platform.tenants",
        meta: {
            isFrontend: false,
            auth: true,
            workspace: "platform",
        },
    },
].concat(operationRoutes.map((route) => ({
    path: route.path,
    component: PlatformOperationsComponent,
    name: route.name,
    meta: {
        isFrontend: false,
        auth: true,
        workspace: "platform",
        section: route.section,
        title: route.title,
        subtitle: route.subtitle,
    },
})));
