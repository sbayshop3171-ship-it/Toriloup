import axios from "axios";

export const merchantDashboard = {
    namespaced: true,
    state: {
        setup: null,
    },
    getters: {
        setup: function (state) {
            return state.setup;
        },
    },
    actions: {
        setup: function (context) {
            return new Promise((resolve, reject) => {
                axios.get("merchant/dashboard/setup").then((res) => {
                    context.commit("setup", res.data.data);
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
    },
    mutations: {
        setup: function (state, payload) {
            state.setup = payload;
        },
    },
};
