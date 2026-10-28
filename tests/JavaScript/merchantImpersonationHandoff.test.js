import assert from "node:assert/strict";
import test from "node:test";
import {
    isMerchantImpersonationHandoffPath,
    isMerchantImpersonationHandoffRoute,
    shouldForceAuthLogoutForResponse,
    shouldVerifyAdminSurfaceAuth,
} from "../../resources/js/services/merchantImpersonationHandoff.js";

test("recognizes the resolved merchant handoff route", () => {
    assert.equal(isMerchantImpersonationHandoffRoute({ name: "merchant.impersonation" }), true);
    assert.equal(isMerchantImpersonationHandoffRoute({ name: "auth.login" }), false);
});

test("recognizes a handoff URL before Vue Router has resolved its route", () => {
    assert.equal(isMerchantImpersonationHandoffPath("/admin/impersonation/one-time-token"), true);
    assert.equal(isMerchantImpersonationHandoffPath("/admin/impersonation/one-time-token/"), true);
    assert.equal(isMerchantImpersonationHandoffPath("/admin/impersonation/"), false);
    assert.equal(isMerchantImpersonationHandoffPath("/admin/login"), false);
});

test("skips the first auth check only for a merchant handoff", () => {
    assert.equal(
        shouldVerifyAdminSurfaceAuth({ name: undefined }, "/admin/impersonation/one-time-token", true, undefined),
        false
    );
    assert.equal(
        shouldVerifyAdminSurfaceAuth({ name: "auth.login" }, "/login", true, undefined),
        true
    );
    assert.equal(
        shouldVerifyAdminSurfaceAuth({ name: "auth.login" }, "/login", false, "true"),
        true
    );
});

test("keeps startup 401 responses from interrupting the merchant handoff", () => {
    assert.equal(shouldForceAuthLogoutForResponse({
        status: 401,
        message: "Unauthenticated.",
        requestUrl: "frontend/setting/lists",
        pathname: "/admin/impersonation/one-time-token",
    }), false);
    assert.equal(shouldForceAuthLogoutForResponse({
        status: 401,
        message: "Unauthenticated.",
        requestUrl: "merchant/auth/impersonate",
        pathname: "/admin/impersonation/one-time-token",
    }), false);
});

test("preserves normal auth failure handling outside the merchant handoff", () => {
    assert.equal(shouldForceAuthLogoutForResponse({
        status: 401,
        message: "Unauthenticated.",
        requestUrl: "merchant/auth/me",
        pathname: "/dashboard",
    }), true);
    assert.equal(shouldForceAuthLogoutForResponse({
        status: 403,
        message: "Forbidden. Merchant token required.",
        requestUrl: "merchant/auth/me",
        pathname: "/dashboard",
    }), true);
    assert.equal(shouldForceAuthLogoutForResponse({
        status: 403,
        message: "Forbidden.",
        requestUrl: "merchant/auth/me",
        pathname: "/dashboard",
    }), false);
});
