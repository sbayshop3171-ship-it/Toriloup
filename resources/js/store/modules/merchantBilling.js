import axios from "axios";

export const merchantBilling = {
    namespaced: true,
    state: {
        summary: null,
        invoices: [],
        plans: [],
        checkoutResult: null,
    },
    getters: {
        summary: function (state) {
            return state.summary;
        },
        invoices: function (state) {
            return state.invoices;
        },
        plans: function (state) {
            return state.plans;
        },
        checkoutResult: function (state) {
            return state.checkoutResult;
        },
    },
    actions: {
        summary: function (context) {
            return new Promise((resolve, reject) => {
                axios.get("merchant/billing/summary").then((res) => {
                    context.commit("summary", res.data);
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
        plans: function (context) {
            return new Promise((resolve, reject) => {
                axios.get("merchant/billing/plans").then((res) => {
                    context.commit("plans", res.data.data);
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
        invoices: function (context) {
            return new Promise((resolve, reject) => {
                axios.get("merchant/billing/invoices").then((res) => {
                    context.commit("invoices", res.data.data);
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
        checkout: function (context, payload) {
            return new Promise((resolve, reject) => {
                axios.post("merchant/billing/checkout", payload).then((res) => {
                    context.commit("checkoutResult", res.data.data);
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
    },
    mutations: {
        summary: function (state, payload) {
            state.summary = payload;
        },
        invoices: function (state, payload) {
            state.invoices = payload;
        },
        plans: function (state, payload) {
            state.plans = payload;
        },
        checkoutResult: function (state, payload) {
            state.checkoutResult = payload;
        },
    },
};
