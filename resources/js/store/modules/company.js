import axios from 'axios'
import { isMerchantHost } from "../../services/workspaceService";

const companyEndpoint = function () {
    return isMerchantHost() ? 'merchant/settings/company' : 'admin/setting/company';
};


export const company = {
    namespaced: true,
    state: {
        lists: [],
    },

    getters: {
        lists: function (state) {
            return state.lists;
        }
    },

    actions: {
        lists: function (context) {
            return new Promise((resolve, reject) => {
                axios.get(companyEndpoint()).then((res) => {
                    context.commit('lists', res.data.data);
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
        save: function (context, payload) {
            return new Promise((resolve, reject) => {
                const merchantHost = isMerchantHost();
                const method = merchantHost || payload instanceof FormData ? axios.post : axios.put;
                const headers = payload instanceof FormData
                    ? { 'Content-Type': 'multipart/form-data' }
                    : {};

                if (!merchantHost && payload instanceof FormData) {
                    payload.append('_method', 'PUT');
                }

                method(`/${companyEndpoint()}`, payload, {
                    headers: payload instanceof FormData
                        ? headers
                        : {},
                }).then(res => {
                    context.commit('lists', res.data.data);
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        }
    },

    mutations: {
        lists: function (state, payload) {
            state.lists = payload
        }
    },
}
