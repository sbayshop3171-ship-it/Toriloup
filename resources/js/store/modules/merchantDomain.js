import axios from "axios";

export const merchantDomain = {
    namespaced: true,
    state: {
        lists: [],
    },
    getters: {
        lists: function (state) {
            return state.lists;
        },
    },
    actions: {
        lists: function (context) {
            return new Promise((resolve, reject) => {
                axios.get("merchant/domains").then((res) => {
                    context.commit("lists", res.data.data);
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
        save: function (context, payload) {
            return new Promise((resolve, reject) => {
                axios.post("merchant/domains", payload).then((res) => {
                    context.dispatch("lists").then().catch();
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
        setPrimary: function (context, payload) {
            return new Promise((resolve, reject) => {
                axios.post(`merchant/domains/${payload.id}/primary`).then((res) => {
                    context.dispatch("lists").then().catch();
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
        connectCloudflare: function (context, payload) {
            return new Promise((resolve, reject) => {
                axios.post(`merchant/domains/${payload.id}/cloudflare/connect`).then((res) => {
                    context.dispatch("lists").then().catch();
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
        verify: function (context, payload) {
            return new Promise((resolve, reject) => {
                axios.post(`merchant/domains/${payload.id}/verify`).then((res) => {
                    context.dispatch("lists").then().catch();
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
    },
    mutations: {
        lists: function (state, payload) {
            state.lists = payload;
        },
    },
};
