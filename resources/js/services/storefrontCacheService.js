const DEFAULT_TTL = 120000;
const LONG_TTL = 600000;
const USER_TTL = 30000;
const PRODUCT_TTL = 180000;

const cache = new Map();
const inflight = new Map();
const MAX_CACHE_ITEMS = 250;

function safeJsonParse(value, fallback = null) {
    try {
        return JSON.parse(value);
    } catch (error) {
        return fallback;
    }
}

function clone(value) {
    if (typeof structuredClone === "function") {
        try {
            return structuredClone(value);
        } catch (error) {}
    }

    if (typeof value === "undefined") {
        return value;
    }

    return safeJsonParse(JSON.stringify(value), value);
}

function normalize(value) {
    if (Array.isArray(value)) {
        return value.map((item) => normalize(item));
    }

    if (value && typeof value === "object") {
        return Object.keys(value)
            .sort()
            .reduce((result, key) => {
                if (typeof value[key] !== "undefined" && value[key] !== null && key !== "vuex" && key !== "_prefetch") {
                    result[key] = normalize(value[key]);
                }

                return result;
            }, {});
    }

    return value;
}

function normalizeData(data) {
    if (!data) {
        return null;
    }

    if (typeof data === "string") {
        return safeJsonParse(data, data);
    }

    return data;
}

function currentScope() {
    let vuex = {};

    try {
        vuex = safeJsonParse(localStorage.getItem("vuex"), {});
    } catch (error) {}

    const authInfo = vuex?.auth?.authInfo || {};

    return [
        window.location.hostname,
        authInfo?.current_tenant?.slug || "",
        vuex?.globalState?.lists?.language_code || "",
        authInfo?.surface || "",
        authInfo?.id || "",
    ].join("|");
}

function requestUrl(config) {
    try {
        const baseUrl = config.baseURL || window.location.origin;
        const url = new URL(config.url || "", baseUrl);
        const params = config.params || {};

        Object.keys(params).sort().forEach((key) => {
            if (typeof params[key] !== "undefined" && params[key] !== null) {
                url.searchParams.set(key, params[key]);
            }
        });

        return url.pathname.replace(/^\/api\/?/, "") + url.search;
    } catch (error) {
        return String(config.url || "");
    }
}

function isFrontendRequest(config) {
    return requestUrl(config).replace(/^\//, "").startsWith("frontend/");
}

function isCacheable(config) {
    if (!config || config.cache === false || config.headers?.["X-Skip-Cache"]) {
        return false;
    }

    const method = String(config.method || "get").toLowerCase();
    const url = requestUrl(config).replace(/^\//, "");

    if (!url.startsWith("frontend/")) {
        return false;
    }

    if (url.startsWith("frontend/setting")) {
        return false;
    }

    if (method === "get") {
        return true;
    }

    return method === "post" && url.startsWith("frontend/product/category-wise-products");
}

function ttlFor(config) {
    const url = requestUrl(config);

    if (/frontend\/(slider|benefit|product-category|product-brand|product-section|promotion|page|outlet|order-area|payment-gateway)/.test(url)) {
        return LONG_TTL;
    }

    if (/frontend\/(order|overview|address|wishlist)/.test(url)) {
        return USER_TTL;
    }

    if (/frontend\/product/.test(url)) {
        return PRODUCT_TTL;
    }

    return DEFAULT_TTL;
}

function keyForConfig(config) {
    const method = String(config.method || "get").toLowerCase();
    const data = normalizeData(config.data);

    return JSON.stringify({
        scope: currentScope(),
        method,
        url: requestUrl(config),
        data: normalize(data),
    });
}

function get(key) {
    const entry = cache.get(key);

    if (!entry) {
        return null;
    }

    if (entry.expiresAt <= Date.now()) {
        cache.delete(key);
        return null;
    }

    return clone(entry.response);
}

function set(key, response, ttl = DEFAULT_TTL) {
    cache.set(key, {
        expiresAt: Date.now() + ttl,
        response: clone(response),
    });

    while (cache.size > MAX_CACHE_ITEMS) {
        cache.delete(cache.keys().next().value);
    }
}

function remember(key, ttl, fetcher) {
    const cached = get(key);

    if (cached) {
        return Promise.resolve(cached);
    }

    if (inflight.has(key)) {
        return inflight.get(key).then((value) => clone(value));
    }

    const request = Promise.resolve()
        .then(fetcher)
        .then((response) => {
            set(key, response, ttl);
            return clone(response);
        })
        .finally(() => {
            inflight.delete(key);
        });

    inflight.set(key, request);
    return request.then((value) => clone(value));
}

function clearCurrentScope() {
    const scope = currentScope();

    cache.forEach((entry, key) => {
        if (key.includes(`"scope":"${scope}`)) {
            cache.delete(key);
        }
    });
}

function shouldInvalidate(config) {
    if (!isFrontendRequest(config)) {
        return false;
    }

    const method = String(config.method || "get").toLowerCase();
    const url = requestUrl(config).replace(/^\//, "");

    if (method === "get") {
        return false;
    }

    return !(method === "post" && url.startsWith("frontend/product/category-wise-products"));
}

function installAxiosCache(axios) {
    axios.interceptors.request.use((config) => {
        if (!isCacheable(config)) {
            return config;
        }

        const key = keyForConfig(config);
        const cached = get(key);

        if (!cached) {
            config.storefrontCacheKey = key;

            if (typeof axios.getAdapter === "function") {
                const adapter = axios.getAdapter(config.adapter || axios.defaults.adapter);

                config.adapter = (adapterConfig) => {
                    if (inflight.has(key)) {
                        return inflight.get(key).then((response) => ({
                            data: clone(response.data),
                            status: response.status || 200,
                            statusText: response.statusText || "OK",
                            headers: response.headers || {},
                            config: adapterConfig,
                            request: null,
                            cached: true,
                        }));
                    }

                    const request = adapter(adapterConfig)
                        .then((response) => {
                            const cachedResponse = {
                                data: response.data,
                                status: response.status,
                                statusText: response.statusText,
                                headers: response.headers,
                            };

                            set(key, cachedResponse, ttlFor(adapterConfig));

                            return cachedResponse;
                        })
                        .finally(() => {
                            inflight.delete(key);
                        });

                    inflight.set(key, request);

                    return request.then((response) => ({
                        data: clone(response.data),
                        status: response.status || 200,
                        statusText: response.statusText || "OK",
                        headers: response.headers || {},
                        config: adapterConfig,
                        request: null,
                        storefrontCacheResolved: true,
                    }));
                };
            }

            return config;
        }

        config.adapter = () => Promise.resolve({
            data: cached.data,
            status: cached.status || 200,
            statusText: cached.statusText || "OK",
            headers: cached.headers || {},
            config,
            request: null,
            cached: true,
        });

        return config;
    });

    axios.interceptors.response.use((response) => {
        if (response?.cached || response?.storefrontCacheResolved) {
            return response;
        }

        if (response?.config?.storefrontCacheKey && isCacheable(response.config)) {
            set(response.config.storefrontCacheKey, {
                data: response.data,
                status: response.status,
                statusText: response.statusText,
                headers: response.headers,
            }, ttlFor(response.config));
        } else if (shouldInvalidate(response?.config)) {
            clearCurrentScope();
        }

        return response;
    }, (error) => {
        if (shouldInvalidate(error?.config)) {
            clearCurrentScope();
        }

        return Promise.reject(error);
    });
}

export default {
    clearCurrentScope,
    clone,
    currentScope,
    get,
    installAxiosCache,
    isCacheable,
    keyForConfig,
    remember,
    requestUrl,
    set,
    ttlFor,
};
