import axios from "axios";

export const merchantBilling = {
    namespaced: true,
    state: {
        summary: null,
        invoices: [],
    },
    getters: {
        summary: function (state) {
            return state.summary;
        },
        invoices: function (state) {
            return state.invoices;
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
    },
    mutations: {
        summary: function (state, payload) {
            state.summary = payload;
        },
        invoices: function (state, payload) {
            state.invoices = payload;
        },
    },
};
