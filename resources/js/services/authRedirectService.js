const blockedAuthRedirectPaths = [
    "/login",
    "/signup",
    "/signup/verify",
    "/forgot-password",
    "/forgot-password/verify",
    "/forgot-password/reset-password",
];

export const isSafeAuthRedirect = function (redirect) {
    if (typeof redirect !== "string") {
        return false;
    }

    const value = redirect.trim();

    if (!value.startsWith("/") || value.startsWith("//")) {
        return false;
    }

    const path = value.split("?")[0].split("#")[0];

    return !blockedAuthRedirectPaths.includes(path);
};

export const authRedirectQuery = function (route) {
    const redirect = route?.query?.redirect;

    return isSafeAuthRedirect(redirect) ? { redirect } : {};
};

export const resolvePostAuthRedirect = function (route, carts = []) {
    const redirect = route?.query?.redirect;

    if (isSafeAuthRedirect(redirect)) {
        return redirect;
    }

    if (Array.isArray(carts) && carts.length > 0) {
        return { name: "frontend.checkout" };
    }

    return { name: "frontend.home" };
};
