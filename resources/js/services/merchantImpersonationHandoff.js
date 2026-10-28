const HANDOFF_PATH_PATTERN = /^\/admin\/impersonation\/[^/]+\/?$/;

export const isMerchantImpersonationHandoffRoute = function (route) {
    return route?.name === "merchant.impersonation";
};

export const isMerchantImpersonationHandoffPath = function (pathname) {
    return HANDOFF_PATH_PATTERN.test(String(pathname || ""));
};

export const shouldVerifyAdminSurfaceAuth = function (route, pathname, isAdminSurfaceHost, demoMode) {
    if (isMerchantImpersonationHandoffRoute(route) || isMerchantImpersonationHandoffPath(pathname)) {
        return false;
    }

    return Boolean(isAdminSurfaceHost || demoMode === "true" || demoMode === true || demoMode === "1" || demoMode === 1);
};
