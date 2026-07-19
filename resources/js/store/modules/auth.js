import axios from "axios";
import { detectWorkspaceHost } from "../../services/workspaceService";

const authEndpoint = function (context, action) {
    if (context === "platform") {
        return `platform/auth/${action}`;
    }

    if (context === "merchant") {
        return `merchant/auth/${action}`;
    }

    if (context === "storefront") {
        return `storefront/auth/${action}`;
    }

    return `auth/${action}`;
};

export const auth = {
    state: {
        authStatus: false,
        authToken: null,
        authInfo: {},
        authMenu: [],
        resetInfo: {
            email: null,
        },
        authPermission: {},
        authDefaultPermission: {},
        phone: {},
        email: {},
        authDefaultMenu: {},
    },
    getters: {
        authStatus: function (state) {
            return state.authStatus;
        },
        authToken: function (state) {
            return state.authToken;
        },
        authInfo: function (state) {
            return state.authInfo;
        },
        authMenu: function (state) {
            return state.authMenu;
        },
        authPermission: function (state) {
            return state.authPermission;
        },
        authDefaultPermission: function (state) {
            return state.authDefaultPermission;
        },
        resetInfo: function (state) {
            return state.resetInfo;
        },
        phone: function (state) {
            return state.phone;
        },
        email: function (state) {
            return state.email;
        },
        authDefaultMenu: function (state) {
            return state.authDefaultMenu;
        },
    },
    actions: {
        profile: function (context, payload) {
            return new Promise((resolve, reject) => {
                axios
                    .get("/profile", payload)
                    .then((res) => {
                        context.commit("authInfo", res.data.data);
                        resolve(res);
                    })
                    .catch((err) => {
                        reject(err);
                    });
            });
        },
        login: function (context, payload) {
            return new Promise((resolve, reject) => {
                axios
                    .post(authEndpoint(payload?.context, "login"), payload)
                    .then((res) => {
                        context.commit("authLogin", res.data);
                        resolve(res);
                    })
                    .catch((err) => {
                        reject(err);
                    });
            });
        },
        authcheck: function (context, payload) {
            return new Promise((resolve, reject) => {
                const rememberedSurface = context.state.authInfo?.surface || null;
                const workspace = detectWorkspaceHost();

                if (
                    rememberedSurface === "platform" ||
                    rememberedSurface === "merchant" ||
                    rememberedSurface === "storefront"
                ) {
                    axios
                        .get(authEndpoint(rememberedSurface, "me"), payload)
                        .then((res) => {
                            context.commit("authRefresh", res.data);
                            resolve({
                                data: {
                                    status: true,
                                    surface: res.data?.surface || rememberedSurface,
                                },
                            });
                        })
                        .catch((err) => {
                            const status = err?.response?.status;

                            if (status === 401 || status === 403 || status === 404) {
                                context.commit("authLogout");
                                resolve({ data: { status: false } });
                                return;
                            }

                            reject(err);
                        });

                    return;
                }

                if (workspace === "platform" || workspace === "merchant") {
                    context.commit("authLogout");
                    resolve({ data: { status: false } });
                    return;
                }

                axios
                    .post("auth/authcheck", payload)
                    .then((res) => {
                        if (res.data.status === false) {
                            context.commit("authLogout");
                        }
                        resolve(res);
                    })
                    .catch((err) => {
                        reject(err);
                    });
            });
        },
        logout: function (context) {
            return new Promise((resolve, reject) => {
                const surface = context.state.authInfo?.surface || context.state.authInfo?.auth_surface || detectWorkspaceHost();
                axios
                    .post(authEndpoint(surface, "logout"))
                    .then((res) => {
                        context.commit("authLogout");
                        resolve(res);
                    })
                    .catch((err) => {
                        reject(err);
                    });
            });
        },
        merchantRegister: function (context, payload) {
            return new Promise((resolve, reject) => {
                axios
                    .post("merchant/auth/register", payload)
                    .then((res) => {
                        context.commit("authLogin", res.data);
                        resolve(res);
                    })
                    .catch((err) => {
                        reject(err);
                    });
            });
        },
        forgotPassword: function (context, payload) {
            return new Promise((resolve, reject) => {
                axios
                    .post(authEndpoint(payload?.context, "forgot-password"), payload)
                    .then((res) => {
                        context.commit("email", payload);
                        context.commit("phone", payload);
                        resolve(res);
                    })
                    .catch((err) => {
                        reject(err);
                    });
            });
        },
        forgotPasswordVerifyPhone: function (context, payload) {
            return new Promise((resolve, reject) => {
                axios
                    .post(authEndpoint(payload?.context, "forgot-password/verify-phone"), payload)
                    .then((res) => {
                        resolve(res);
                    })
                    .catch((err) => {
                        reject(err);
                    });
            });
        },
        forgotPasswordVerifyEmail: function (context, payload) {
            return new Promise((resolve, reject) => {
                axios
                    .post(authEndpoint(payload?.context, "forgot-password/verify-email"), payload)
                    .then((res) => {
                        resolve(res);
                    })
                    .catch((err) => {
                        reject(err);
                    });
            });
        },
        otpPhone: function (context, payload) {
            return new Promise((resolve, reject) => {
                axios
                    .post(authEndpoint(payload?.context, "forgot-password/otp-phone"), payload)
                    .then((res) => {
                        context.commit("phone", payload);
                        resolve(res);
                    })
                    .catch((err) => {
                        reject(err);
                    });
            });
        },
        otpEmail: function (context, payload) {
            return new Promise((resolve, reject) => {
                axios
                    .post(authEndpoint(payload?.context, "forgot-password/otp-email"), payload)
                    .then((res) => {
                        context.commit("email", payload);
                        resolve(res);
                    })
                    .catch((err) => {
                        reject(err);
                    });
            });
        },
        resetPassword: function (context, payload) {
            return new Promise((resolve, reject) => {
                axios
                    .post(authEndpoint(payload?.context, "forgot-password/reset-password"), payload)
                    .then((res) => {
                        context.commit("authLogin", res.data);
                        resolve(res);
                    })
                    .catch((err) => {
                        reject(err);
                    });
            });
        },
        updateAuthInfo: function (context, payload) {
            return new Promise((resolve, reject) => {
                if (context.state.authInfo.id === payload.id) {
                    const mergedPayload = {
                        ...context.state.authInfo,
                        ...payload,
                        surface: payload.surface || context.state.authInfo?.surface || null,
                        tenants: payload.tenants || context.state.authInfo?.tenants || [],
                        current_tenant: payload.current_tenant || context.state.authInfo?.current_tenant || null,
                        impersonation: payload.impersonation || context.state.authInfo?.impersonation || null,
                    };
                    context.commit("authInfo", mergedPayload);
                    resolve(mergedPayload);
                } else {
                    reject("user data not match");
                }
            });
        },
        verifyPhone: function (context, payload) {
            return new Promise((resolve, reject) => {
                let url = authEndpoint(payload?.context, "signup/verify-phone");
                axios
                    .post(url, payload)
                    .then((res) => {
                        resolve(res);
                    })
                    .catch((err) => {
                        reject(err);
                    });
            });
        },
        verifyEmail: function (context, payload) {
            return new Promise((resolve, reject) => {
                let url = authEndpoint(payload?.context, "signup/verify-email");
                axios
                    .post(url, payload)
                    .then((res) => {
                        resolve(res);
                    })
                    .catch((err) => {
                        reject(err);
                    });
            });
        },
        signupLoginVerify: function (context, payload) {
            return new Promise((resolve, reject) => {
                axios
                    .post(authEndpoint(payload?.context, "signup/login-verify"), payload)
                    .then((res) => {
                        context.commit("authLogin", res.data);
                        resolve(res);
                    })
                    .catch((err) => {
                        reject(err);
                    });
            });
        },
        loginDataReset: function (context) {
            context.commit("authLogout");
        },
        reset: function (context) {
            context.commit("reset");
        },
    },
    mutations: {
        authLogin: function (state, payload) {
            state.authStatus = true;
            state.authToken = payload.token;
            state.authInfo = {
                ...(payload.user || {}),
                surface: payload.surface || payload.user?.surface || null,
                tenants: payload.tenants || [],
                current_tenant: payload.current_tenant || payload.tenant || null,
                impersonation: payload.impersonation || null,
            };
            state.authMenu = payload.menu;
            state.authPermission = payload.permission;
            state.authDefaultPermission = payload.defaultPermission;
            state.authDefaultMenu = payload.defaultMenu;
        },
        authLogout: function (state) {
            state.authStatus = false;
            state.authToken = null;
            state.authInfo = {};
            state.authMenu = [];
            state.authPermission = {};
            state.authDefaultPermission = {};
            state.authDefaultMenu = {};
        },
        authRefresh: function (state, payload) {
            state.authInfo = {
                ...state.authInfo,
                ...(payload.user || {}),
                surface: payload.surface || state.authInfo?.surface || null,
                tenants: payload.tenants || state.authInfo?.tenants || [],
                current_tenant: payload.current_tenant || state.authInfo?.current_tenant || null,
                impersonation: payload.impersonation || state.authInfo?.impersonation || null,
            };

            if (payload.menu) {
                state.authMenu = payload.menu;
            }

            if (payload.permission) {
                state.authPermission = payload.permission;
            }

            if (payload.defaultPermission) {
                state.authDefaultPermission = payload.defaultPermission;
            }

            if (payload.defaultMenu) {
                state.authDefaultMenu = payload.defaultMenu;
            }
        },
        forgotPassword: function (state, payload) {
            state.resetInfo = {
                email: payload.email,
            };
        },
        resetPassword: function (state) {
            state.resetInfo = {
                email: null,
            };
        },
        authInfo: function (state, payload) {
            state.authInfo = payload;
        },
        phone: function (state, payload) {
            state.phone.otp = payload;
        },
        email: function (state, payload) {
            state.email.otp = payload;
        },
        reset: function (state) {
            state.phone = {};
            state.email = {};
        },
    },
};
