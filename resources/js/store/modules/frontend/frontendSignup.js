import axios from "axios";

const storefrontSignupEndpoint = function (action) {
    return `storefront/auth/signup/${action}`;
};

export const frontendSignup = {
    namespaced: true,
    state: {
        phone: {},
        email: {},
        formData:{},   
    },
    getters: {
        phone: function (state) {
            return state.phone;
        },
        email: function (state) {
            return state.email;
        },
        formData: function (state) {
            return state.formData;
        }
    },
    actions: {
        otpPhone: function (context, payload) {
            return new Promise((resolve, reject) => {
                let url = storefrontSignupEndpoint("otp-phone");
                axios.post(url,payload).then((res) => {
                    context.commit("phone", payload);
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
        otpEmail: function (context, payload) {
            return new Promise((resolve, reject) => {
                let url = storefrontSignupEndpoint("otp-email");
                axios.post(url,payload).then((res) => {
                    context.commit("email", payload);
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
        signupValidation: function (context, payload) {
            return new Promise((resolve, reject) => {
                let url = storefrontSignupEndpoint("register-validation");
                axios.post(url,payload).then((res) => {
                    context.commit("formData", payload);
                    context.commit("phone", payload);
                    context.commit("email", payload);
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
        signup: function (context, payload) {
            return new Promise((resolve, reject) => {
                let url = storefrontSignupEndpoint("register");
                axios.post(url,payload).then((res) => {
                    context.commit("phone", payload);
                    context.commit("email", payload);
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
        reset: function (context) {
            context.commit('reset');
        },
    },
    mutations: {
        phone: function (state, payload) {
            state.phone.otp = payload;
        },
        email: function (state, payload) {
            state.email.otp = payload;
        },
        formData: function(state, payload) {
            state.formData = payload;
        },
        verify: function (state, payload) {
            state.phone.status = payload;
        },
        reset: function(state) {
            state.phone = {};
            state.email = {};
            state.formData = {};
        }
    },
};
