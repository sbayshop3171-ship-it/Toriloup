import axios from 'axios'
import { isMerchantHost } from "../../services/workspaceService";

const shippingSetupEndpoint = function () {
    return isMerchantHost() ? 'merchant/settings/shipping' : 'admin/setting/shipping-setup';
};

export const shippingSetup = {
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
                axios.get(shippingSetupEndpoint()).then((res) => {
                    context.commit("lists", res.data.data);
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
        save: function (context, payload) {
            return new Promise((resolve, reject) => {
                axios.put(`/${shippingSetupEndpoint()}`, payload).then((res) => {
                    context.commit("lists", res.data.data ?? payload);
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        },
    },
    mutations: {
        lists: function (state, payload) {
            state.lists = payload
        }
    },
}
