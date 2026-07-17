const PlatformDashboardComponent = () => import("../../components/platform/PlatformDashboardComponent.vue");
const PlatformTenantsComponent = () => import("../../components/platform/PlatformTenantsComponent.vue");

export default [
    {
        path: "/owner/dashboard",
        component: PlatformDashboardComponent,
        name: "platform.dashboard",
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
];
