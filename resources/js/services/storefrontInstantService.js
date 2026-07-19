import axios from "axios";
import appService from "./appService";
import cacheService from "./storefrontCacheService";
import statusEnum from "../enums/modules/statusEnum";
import askEnum from "../enums/modules/askEnum";
import promotionTypeEnum from "../enums/modules/promotionTypeEnum";

const prefetchedRoutes = new Set();
const prefetchedImages = new Set();
const preloadedImageLinks = new Set();
const loadedRouteComponents = new WeakSet();
let installed = false;
let imageInterceptorInstalled = false;
let observer = null;
let mutationObserver = null;
let scanTimer = null;

const HOME_PREFETCHES = [
    () => axios.get("frontend/slider" + appService.requestHandler({
        paginate: 0,
        order_column: "id",
        order_type: "desc",
        status: statusEnum.ACTIVE,
    })),
    () => axios.get("frontend/product-category" + appService.requestHandler({
        paginate: 0,
        order_column: "id",
        order_type: "asc",
        parent_id: null,
        status: statusEnum.ACTIVE,
    })),
    () => axios.get("frontend/promotion" + appService.requestHandler({
        paginate: 0,
        order_column: "id",
        order_type: "asc",
        type: promotionTypeEnum.SMALL,
        status: statusEnum.ACTIVE,
    })),
    () => axios.get("frontend/promotion" + appService.requestHandler({
        paginate: 0,
        order_column: "id",
        order_type: "asc",
        type: promotionTypeEnum.BIG,
        status: statusEnum.ACTIVE,
    })),
    () => axios.get("frontend/product-section"),
    () => axios.get("frontend/product/popular-products" + appService.requestHandler({
        paginate: 0,
        rand: 8,
    })),
    () => axios.get("frontend/product/flash-sale-products" + appService.requestHandler({
        paginate: 0,
        rand: 8,
    })),
    () => axios.get("frontend/product-brand" + appService.requestHandler({
        paginate: 0,
        order_column: "id",
        order_type: "asc",
        status: statusEnum.ACTIVE,
    })),
    () => axios.get("frontend/benefit" + appService.requestHandler({
        paginate: 0,
        order_column: "id",
        order_type: "asc",
        status: statusEnum.ACTIVE,
    })),
];

function idle(callback) {
    if (typeof window.requestIdleCallback === "function") {
        window.requestIdleCallback(callback, { timeout: 1500 });
        return;
    }

    window.setTimeout(callback, 80);
}

function routeKey(route) {
    return `${cacheService.currentScope()}|${route.fullPath || route.path || route.name || ""}`;
}

function isSameOriginHref(href) {
    try {
        const url = new URL(href, window.location.href);
        return url.origin === window.location.origin;
    } catch (error) {
        return false;
    }
}

function resolveHref(router, href) {
    if (!href || href === "#" || !isSameOriginHref(href)) {
        return null;
    }

    const url = new URL(href, window.location.href);
    return router.resolve(`${url.pathname}${url.search}${url.hash}`);
}

function isStorefrontRoute(route) {
    return route?.matched?.some((record) => record?.meta?.isFrontend === true);
}

function componentLoadersFromRoute(route) {
    const loaders = [];

    route.matched.forEach((record) => {
        const components = record.components || (record.component ? { default: record.component } : {});

        Object.keys(components).forEach((key) => {
            const component = components[key];

            if (typeof component === "function" && !loadedRouteComponents.has(component)) {
                loadedRouteComponents.add(component);
                loaders.push(component);
            }
        });
    });

    return loaders;
}

function runQuietly(task) {
    return Promise.resolve()
        .then(task)
        .then((response) => {
            preloadImagesFromData(response?.data, 32, 4);
            return response;
        })
        .catch(() => null);
}

function prefetchRequests(tasks, limit = 4) {
    const queue = tasks.filter(Boolean);
    let active = 0;
    let index = 0;

    return new Promise((resolve) => {
        const next = () => {
            if (index >= queue.length && active === 0) {
                resolve();
                return;
            }

            while (active < limit && index < queue.length) {
                const task = queue[index++];
                active++;
                runQuietly(task).finally(() => {
                    active--;
                    next();
                });
            }
        };

        next();
    });
}

function cleanString(value) {
    return String(value || "").trim();
}

function productListPayload(query = {}) {
    return {
        page: 1,
        status: statusEnum.ACTIVE,
        sort_by: null,
        category: cleanString(query.category) || null,
        name: cleanString(query.name) || null,
        brand: cleanString(query.brand) ? JSON.stringify([query.brand]) : [],
        variation: [],
        min_price: null,
        max_price: null,
    };
}

function pagedPayload(page = 1, perPage = 32) {
    return {
        paginate: 1,
        page,
        per_page: perPage,
        order_column: "name",
        order_type: "asc",
    };
}

function prefetchProductDetails(slug) {
    const payload = { slug, review_limit: 3 };
    const query = appService.requestHandler(payload);

    return runQuietly(() => axios.get(`frontend/product/show/${slug}${query}`))
        .then((response) => {
            const product = response?.data?.data;

            if (!product) {
                return null;
            }

            const tasks = [
                () => axios.get(`frontend/product/related-products/${slug}${appService.requestHandler({ slug, rand: 8 })}`),
            ];

            if (product.category_slug) {
                tasks.push(() => axios.get(`frontend/product-category/ancestors-and-self/${product.category_slug}`));
            }

            if (product.id) {
                tasks.push(() => axios.get(`frontend/product/initial-variation/${product.id}`));
            }

            return prefetchRequests(tasks, 3);
        });
}

function routePrefetchTasks(route, store) {
    const name = route.name;
    const params = route.params || {};
    const query = route.query || {};
    const loggedIn = store?.getters?.authStatus === true;

    if (name === "frontend.home") {
        return HOME_PREFETCHES;
    }

    if (name === "frontend.product") {
        return [
            () => axios.post("frontend/product/category-wise-products", productListPayload(query)),
            ...(query.category ? [() => axios.get(`frontend/product-category/ancestors-and-self/${query.category}`)] : []),
        ];
    }

    if (name === "frontend.product.details" && params.slug) {
        return [() => prefetchProductDetails(params.slug)];
    }

    if (name === "frontend.offers") {
        return [() => axios.get("frontend/product/offer-products" + appService.requestHandler(pagedPayload()))];
    }

    if (name === "frontend.mostPopular.products") {
        return [() => axios.get("frontend/product/popular-products" + appService.requestHandler(pagedPayload()))];
    }

    if (name === "frontend.flashSale.products") {
        return [() => axios.get("frontend/product/flash-sale-products" + appService.requestHandler(pagedPayload()))];
    }

    if (name === "frontend.promotion.products" && params.slug) {
        return [
            () => axios.get(`frontend/promotion/show/${params.slug}`),
            () => axios.get(`frontend/promotion/products/${params.slug}${appService.requestHandler({ slug: params.slug })}`),
        ];
    }

    if (name === "frontend.productSection.products" && params.slug) {
        return [
            () => axios.get(`frontend/product-section/show/${params.slug}`),
            () => axios.get(`frontend/product-section/products/${params.slug}${appService.requestHandler({ slug: params.slug, per_page: 32 })}`),
        ];
    }

    if (name === "frontend.checkout" || name === "frontend.checkout.cartList" || name === "frontend.checkout.checkout") {
        return [
            () => axios.get("frontend/order-area"),
            () => axios.get("frontend/outlet" + appService.requestHandler({ status: statusEnum.ACTIVE })),
            ...(loggedIn ? [() => axios.get("frontend/address" + appService.requestHandler({
                paginate: 0,
                order_column: "id",
                order_type: "asc",
            }))] : []),
        ];
    }

    if (name === "frontend.checkout.payment") {
        return [
            () => axios.get("frontend/payment-gateway" + appService.requestHandler({ status: statusEnum.ACTIVE })),
        ];
    }

    if (name === "frontend.wishlist" && loggedIn) {
        return [() => axios.get("frontend/wishlist")];
    }

    if (name === "frontend.account.overview" && loggedIn) {
        const search = {
            paginate: 1,
            page: 1,
            per_page: 3,
            order_column: "id",
            order_by: "desc",
            active: askEnum.YES,
        };

        return [
            () => axios.get("frontend/order" + appService.requestHandler(search)),
            () => axios.get("frontend/overview/total-orders"),
            () => axios.get("frontend/overview/total-complete-orders"),
            () => axios.get("frontend/overview/total-return-orders"),
            () => axios.get("frontend/overview/wallet-balance"),
        ];
    }

    if (name === "frontend.account.orderHistory" && loggedIn) {
        return [() => axios.get("frontend/order" + appService.requestHandler({
            paginate: 1,
            page: 1,
            per_page: 10,
            order_column: "id",
            order_by: "desc",
            active: askEnum.YES,
        }))];
    }

    if (name === "frontend.account.address" && loggedIn) {
        return [() => axios.get("frontend/address" + appService.requestHandler({
            paginate: 0,
            order_column: "id",
            order_type: "asc",
        }))];
    }

    if ((name === "frontend.account.orderDetails" || name === "frontend.account.orderTracking" || name === "frontend.account.orderSuccess") && params.id && loggedIn) {
        return [() => axios.get(`frontend/order/show/${params.id}`)];
    }

    if (name === "frontend.page" && params.slug) {
        return [() => axios.get(`frontend/page/show/${params.slug}`)];
    }

    return [];
}

function prefetchRoute(router, store, to) {
    const route = typeof to === "string" ? router.resolve(to) : router.resolve(to);

    if (!isStorefrontRoute(route)) {
        return Promise.resolve();
    }

    const key = routeKey(route);

    if (prefetchedRoutes.has(key)) {
        return Promise.resolve();
    }

    prefetchedRoutes.add(key);

    const componentTasks = componentLoadersFromRoute(route).map((loader) => () => loader());
    const dataTasks = routePrefetchTasks(route, store);

    return prefetchRequests([...componentTasks, ...dataTasks], 4);
}

function looksLikeImageUrl(value, key = "") {
    const stringValue = String(value || "");

    if (!stringValue || stringValue.startsWith("data:")) {
        return false;
    }

    const imageKey = /image|images|cover|thumb|preview|avatar|logo|banner|not_found|cart/i.test(key);

    return imageKey || /\.(png|jpe?g|webp|gif|svg|avif)(\?.*)?$/i.test(stringValue);
}

function collectImageUrls(value, limit = 24, key = "", result = []) {
    if (result.length >= limit || value === null || typeof value === "undefined") {
        return result;
    }

    if (typeof value === "string") {
        if (looksLikeImageUrl(value, key)) {
            result.push(value);
        }

        return result;
    }

    if (Array.isArray(value)) {
        value.forEach((item) => collectImageUrls(item, limit, key, result));
        return result;
    }

    if (typeof value === "object") {
        Object.keys(value).forEach((childKey) => {
            collectImageUrls(value[childKey], limit, childKey, result);
        });
    }

    return result;
}

function preloadImage(src) {
    if (!src || prefetchedImages.has(src)) {
        return;
    }

    prefetchedImages.add(src);

    const image = new Image();
    image.decoding = "async";
    image.loading = "eager";
    image.src = src;
}

function preloadImageLink(src, fetchPriority = "high") {
    if (!src || preloadedImageLinks.has(src) || typeof document === "undefined") {
        return;
    }

    preloadedImageLinks.add(src);

    const link = document.createElement("link");
    link.rel = "preload";
    link.as = "image";
    link.href = src;

    if ("fetchPriority" in link) {
        link.fetchPriority = fetchPriority;
    }

    document.head.appendChild(link);
}

function preloadImagesFromData(data, limit = 24, criticalLimit = 2) {
    collectImageUrls(data, limit).forEach((src, index) => {
        if (index < criticalLimit) {
            preloadImageLink(src, "high");
        }

        preloadImage(src);
    });
}

function tuneImageElement(image) {
    if (!image || image.dataset.instantImage === "1") {
        return;
    }

    image.dataset.instantImage = "1";
    image.decoding = "async";

    const rect = image.getBoundingClientRect();
    const nearViewport = rect.top < window.innerHeight * 1.5;

    if (nearViewport) {
        image.loading = "eager";

        if ("fetchPriority" in image) {
            image.fetchPriority = "high";
        }
    }

    if (!nearViewport) {
        image.loading = image.loading || "lazy";
    }
}

function tuneImages(root = document) {
    root.querySelectorAll?.("img").forEach(tuneImageElement);
}

function scanLinks(router, store) {
    if (observer) {
        observer.disconnect();
    }

    if (typeof IntersectionObserver !== "function") {
        return;
    }

    observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (!entry.isIntersecting) {
                return;
            }

            observer.unobserve(entry.target);
            const route = resolveHref(router, entry.target.getAttribute("href"));

            if (route) {
                idle(() => prefetchRoute(router, store, route));
            }
        });
    }, {
        rootMargin: "420px 0px",
        threshold: 0,
    });

    document.querySelectorAll("a[href]").forEach((link) => {
        const route = resolveHref(router, link.getAttribute("href"));

        if (route && isStorefrontRoute(route)) {
            observer.observe(link);
        }
    });
}

function scheduleScan(router, store) {
    window.clearTimeout(scanTimer);
    scanTimer = window.setTimeout(() => {
        tuneImages();
        scanLinks(router, store);
    }, 120);
}

function handleIntent(router, store, event) {
    const link = event.target?.closest?.("a[href]");

    if (!link) {
        return;
    }

    const route = resolveHref(router, link.getAttribute("href"));

    if (route) {
        prefetchRoute(router, store, route);
    }
}

function shouldPreloadResponseImages(config) {
    if (!config) {
        return false;
    }

    const url = cacheService.requestUrl(config).replace(/^\//, "");

    if (!url.startsWith("frontend/") || url.startsWith("frontend/setting")) {
        return false;
    }

    return /^frontend\/(slider|product-category|promotion|product-section|product-brand|benefit|payment-gateway|order-area|outlet|page\/show)(\/|\?|$)/.test(url) ||
        /^frontend\/product(\/|\?|$)/.test(url);
}

function installAxiosImagePreload(axiosInstance) {
    if (imageInterceptorInstalled || !axiosInstance?.interceptors?.response) {
        return;
    }

    imageInterceptorInstalled = true;

    axiosInstance.interceptors.response.use((response) => {
        if (shouldPreloadResponseImages(response?.config)) {
            preloadImagesFromData(response?.data, 40, 5);
        }

        return response;
    }, (error) => Promise.reject(error));
}

function install(router, store) {
    if (installed || typeof window === "undefined" || typeof document === "undefined") {
        return;
    }

    installed = true;

    ["pointerover", "touchstart", "focusin", "mousedown"].forEach((eventName) => {
        document.addEventListener(eventName, (event) => handleIntent(router, store, event), {
            capture: true,
            passive: true,
        });
    });

    mutationObserver = new MutationObserver(() => scheduleScan(router, store));
    mutationObserver.observe(document.documentElement, {
        childList: true,
        subtree: true,
    });

    router.afterEach((to) => {
        scheduleScan(router, store);
        prefetchRoute(router, store, to);
    });

    router.isReady().then(() => {
        scheduleScan(router, store);
        prefetchRoute(router, store, router.currentRoute.value);
    });
}

export default {
    collectImageUrls,
    install,
    installAxiosImagePreload,
    prefetchRoute,
    preloadImage,
    preloadImageLink,
    preloadImagesFromData,
    tuneImages,
};
