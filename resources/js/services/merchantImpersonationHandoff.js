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

export const shouldForceAuthLogoutForResponse = function ({ status, message, requestUrl, pathname }) {
    if (isMerchantImpersonationHandoffPath(pathname)) {
        return false;
    }

    if (/(?:^|\/)merchant\/auth\/impersonate(?:$|\?)/.test(String(requestUrl || ""))) {
        return false;
    }

    return status === 401 || (status === 403 && (/admin access only/i.test(message) || /token required/i.test(message)));
};
