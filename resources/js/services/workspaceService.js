import ENV from "../config/env";

const normalizeHost = function (host) {
    return String(host || "").trim().toLowerCase();
};

export const detectWorkspaceHost = function (hostname = window.location.hostname) {
    const host = normalizeHost(hostname);
    const ownerHost = normalizeHost(ENV.OWNER_HOST);
    const merchantHost = normalizeHost(ENV.MERCHANT_HOST);

    if (ownerHost && host === ownerHost) {
        return "platform";
    }

    if (merchantHost && host === merchantHost) {
        return "merchant";
    }

    return "storefront";
};

export const isPlatformHost = function (hostname = window.location.hostname) {
    return detectWorkspaceHost(hostname) === "platform";
};

export const isMerchantHost = function (hostname = window.location.hostname) {
    return detectWorkspaceHost(hostname) === "merchant";
};

export const isAdminSurfaceHost = function (hostname = window.location.hostname) {
    const workspace = detectWorkspaceHost(hostname);

    return workspace === "platform" || workspace === "merchant";
};

export const resolveGuestHomeRoute = function (hostname = window.location.hostname) {
    const workspace = detectWorkspaceHost(hostname);

    if (workspace === "platform" || workspace === "merchant") {
        return { name: "auth.login" };
    }

    return { name: "frontend.home" };
};

export const resolveWorkspaceDashboardRoute = function (surface = null, hostname = window.location.hostname) {
    const workspace = surface || detectWorkspaceHost(hostname);

    if (workspace === "platform") {
        return { name: "platform.dashboard" };
    }

    if (workspace === "merchant") {
        return { name: "merchant.dashboard" };
    }

    return { name: "frontend.home" };
};

export const resolveAuthenticatedHomeRoute = function (authInfo = {}, hostname = window.location.hostname) {
    const surface = authInfo?.surface || null;

    if (surface === "platform") {
        return { name: "platform.dashboard" };
    }

    if (surface === "merchant") {
        return { name: "merchant.dashboard" };
    }

    if (surface === "storefront") {
        return { name: "frontend.account.overview" };
    }

    return resolveGuestHomeRoute(hostname);
};
