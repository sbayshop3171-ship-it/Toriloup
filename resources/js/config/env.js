const runtimeConfig = globalThis || window;

const ENV = {
    API_URL: runtimeConfig.APP_URL || import.meta.env.VITE_HOST || window.location.origin,
    DEMO: runtimeConfig.APP_DEMO || import.meta.env.VITE_DEMO,
    API_KEY: runtimeConfig.APP_KEY || import.meta.env.VITE_API_KEY,
    OWNER_HOST: runtimeConfig.APP_OWNER_HOST || import.meta.env.VITE_OWNER_HOST || "",
    OWNER_HOST_ALIASES: runtimeConfig.APP_OWNER_HOST_ALIASES || import.meta.env.VITE_OWNER_HOST_ALIASES || "",
    MERCHANT_HOST: runtimeConfig.APP_MERCHANT_HOST || import.meta.env.VITE_MERCHANT_HOST || "",
    MARKETING_HOST: runtimeConfig.APP_MARKETING_HOST || import.meta.env.VITE_MARKETING_HOST || "",
    STOREFRONT_SUFFIX: runtimeConfig.APP_STOREFRONT_SUFFIX || import.meta.env.VITE_STOREFRONT_SUFFIX || "",
    MAPBOX_ACCESS_TOKEN: import.meta.env.VITE_MAPBOX_ACCESS_TOKEN || ""
};
export default ENV;
