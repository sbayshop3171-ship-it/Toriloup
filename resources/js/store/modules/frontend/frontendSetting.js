import axios from "axios";
import appService from "../../../services/appService";
import { isStorefrontHost } from "../../../services/workspaceService";

const defaultThemeLogoPaths = [
    "/images/required/theme-logo.png",
    "/images/required/theme-footer-logo.png",
];

const isDefaultThemeLogo = function (value) {
    return typeof value === "string" && defaultThemeLogoPaths.some((path) => value.includes(path));
};

const normalizeStorefrontSetting = function (payload) {
    if (!isStorefrontHost() || !payload || Array.isArray(payload)) {
        return payload;
    }

    return {
        ...payload,
        theme_logo: isDefaultThemeLogo(payload.theme_logo) ? null : payload.theme_logo,
        theme_footer_logo: isDefaultThemeLogo(payload.theme_footer_logo) ? null : payload.theme_footer_logo,
    };
};

export const frontendSetting = {
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
        lists: function (context, payload) {
            return new Promise((resolve, reject) => {
                let url = "frontend/setting";
                if (payload) {
                    url = url + appService.requestHandler(payload);
                }
                axios.get(url, { cache: false }).then((res) => {
                    context.commit("lists", res.data.data);
                    resolve(res);
                }).catch((err) => {
                    reject(err);
                });
            });
        }
    },
    mutations: {
        lists: function (state, payload) {
            state.lists = normalizeStorefrontSetting(payload);
        }
    },
};
