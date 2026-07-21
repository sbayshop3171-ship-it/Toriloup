import axios from "axios";
import appService from "./appService";
import cacheService from "./storefrontCacheService";
import statusEnum from "../enums/modules/statusEnum";
import askEnum from "../enums/modules/askEnum";
import promotionTypeEnum from "../enums/modules/promotionTypeEnum";
import orderTypeEnum from "../enums/modules/orderTypeEnum";
import roleEnum from "../enums/modules/roleEnum";
import { isMerchantHost } from "./workspaceService";

const prefetchedRoutes = new Set();
const prefetchedAdminDataAt = new Map();
const prefetchedImages = new Set();
const preloadedImageLinks = new Set();
const loadedRouteComponents = new WeakSet();
let installed = false;
let imageInterceptorInstalled = false;
let merchantAdminWarmupStarted = false;
let observer = null;
let mutationObserver = null;
let scanTimer = null;
const ADMIN_DATA_PREFETCH_RETRY_MS = 12000;

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

const MERCHANT_ADMIN_ROUTE_NAMES = {
    "/dashboard": "merchant.dashboard",
    "/admin/dashboard": "admin.dashboard",
    "/admin/products": "admin.products.list",
    "/admin/purchase": "admin.purchase.list",
    "/admin/damages": "admin.damage.list",
    "/admin/stock": "admin.stock.list",
    "/admin/reviews": "admin.review.list",
    "/admin/pos": "admin.pos",
    "/admin/pos-orders": "admin.pos.orders.list",
    "/admin/online-orders": "admin.order.list",
    "/admin/return-orders": "admin.return-order.list",
    "/admin/return-and-refunds": "admin.returnAndRefund.list",
    "/admin/coupons": "admin.coupons.list",
    "/admin/promotions": "admin.promotions.list",
    "/admin/product-sections": "admin.product-sections.list",
    "/admin/push-notifications": "admin.push-notification.list",
    "/admin/subscribers": "admin.subscribers.list",
    "/admin/wallet": "merchant.wallet",
    "/admin/transactions": "admin.transactions.list",
    "/admin/sales-report": "admin.sales-report.list",
    "/admin/products-report": "admin.products-report.list",
    "/admin/settings": "admin.settings.company",
    "/admin/settings/company": "admin.settings.company",
};

const MERCHANT_ADMIN_PREFETCH_PATHS = [
    "/dashboard",
    "/admin/dashboard",
    "/admin/products",
    "/admin/purchase",
    "/admin/damages",
    "/admin/stock",
    "/admin/reviews",
    "/admin/pos",
    "/admin/pos-orders",
    "/admin/online-orders",
    "/admin/return-orders",
    "/admin/return-and-refunds",
    "/admin/coupons",
    "/admin/promotions",
    "/admin/product-sections",
    "/admin/push-notifications",
    "/admin/subscribers",
    "/admin/wallet",
    "/admin/transactions",
    "/admin/sales-report",
    "/admin/products-report",
    "/admin/settings/company",
];

const MERCHANT_ADMIN_PREFIXES = Object.keys(MERCHANT_ADMIN_ROUTE_NAMES)
    .sort((a, b) => b.length - a.length);

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

function normalizedPath(route) {
    const path = String(route?.path || route?.fullPath || "").split("?")[0].replace(/\/+$/, "");

    return path || "/";
}

function merchantAdminBasePath(route) {
    if (!isMerchantHost()) {
        return null;
    }

    const path = normalizedPath(route);

    return MERCHANT_ADMIN_PREFIXES.find((prefix) => {
        return path === prefix || path.startsWith(`${prefix}/`);
    }) || null;
}

function isMerchantAdminRoute(route) {
    return Boolean(merchantAdminBasePath(route));
}

function isInstantRoute(route) {
    return isStorefrontRoute(route) || isMerchantAdminRoute(route);
}

function routesForComponentPrefetch(router, route) {
    const routes = [route];
    const adminPath = merchantAdminBasePath(route);
    const routeName = adminPath ? MERCHANT_ADMIN_ROUTE_NAMES[adminPath] : null;

    if (routeName && route.name !== routeName) {
        routes.push(router.resolve({ name: routeName }));
    }

    return routes;
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

function adminIdDescSearch(overrides = {}) {
    return {
        paginate: 1,
        page: 1,
        per_page: 10,
        order_column: "id",
        order_type: "desc",
        ...overrides,
    };
}

function adminOrderIdDescSearch(overrides = {}) {
    return {
        paginate: 1,
        page: 1,
        per_page: 10,
        order_column: "id",
        order_by: "desc",
        ...overrides,
    };
}

function adminIdAscPayload(overrides = {}) {
    return {
        order_column: "id",
        order_type: "asc",
        ...overrides,
    };
}

function activeUsersPayload(overrides = {}) {
    return adminIdAscPayload({
        status: statusEnum.ACTIVE,
        ...overrides,
    });
}

function dispatchTask(store, action, payload = undefined) {
    return () => {
        if (!store?.dispatch) {
            return Promise.resolve(null);
        }

        return typeof payload === "undefined"
            ? store.dispatch(action)
            : store.dispatch(action, payload);
    };
}

function merchantDashboardSetupTask(store) {
    return () => {
        if (!store?.dispatch || store.getters?.["merchantDashboard/setup"]) {
            return Promise.resolve(null);
        }

        return store.dispatch("merchantDashboard/setup");
    };
}

function merchantAdminRoutePrefetchTasks(route, store) {
    if (store?.getters?.authStatus !== true) {
        return [];
    }

    const adminPath = merchantAdminBasePath(route);
    const supportIdAscPayload = {
        paginate: 0,
        order_column: "id",
        order_type: "asc",
    };
    const productSearch = adminIdDescSearch({
        name: "",
        sku: "",
        buying_price: "",
        selling_price: "",
        product_category_id: null,
        product_brand_id: null,
        barcode_id: null,
        tax_id: null,
        unit_id: null,
        status: null,
        refundable: null,
    });
    const purchaseSearch = adminIdDescSearch({
        supplier_id: null,
        date: "",
        reference_no: "",
        status: null,
        total: null,
        note: "",
    });
    const damageSearch = adminIdDescSearch({
        date: "",
        reference_no: "",
        total: null,
        note: "",
    });
    const stockSearch = adminIdDescSearch({
        product_name: "",
        status: null,
    });
    const reviewSearch = adminIdDescSearch({
        user_id: null,
        product_id: null,
    });
    const activeUserList = activeUsersPayload();
    const onlineOrderSearch = adminOrderIdDescSearch({
        order_serial_no: "",
        user_id: null,
        excepts: orderTypeEnum.POS,
        status: null,
        active: statusEnum.ACTIVE,
        from_date: "",
        to_date: "",
    });
    const posOrderSearch = adminOrderIdDescSearch({
        order_serial_no: "",
        order_type: orderTypeEnum.POS,
        excepts: `${orderTypeEnum.DELIVERY}|${orderTypeEnum.PICK_UP}`,
        user_id: null,
        status: null,
        from_date: "",
        to_date: "",
    });
    const returnOrderSearch = adminIdDescSearch({
        user_id: null,
        date: "",
        reference_no: "",
        total: null,
        reason: "",
    });
    const returnAndRefundSearch = adminOrderIdDescSearch({
        order_serial_no: "",
        user_id: null,
        status: null,
        from_date: "",
        to_date: "",
    });
    const couponSearch = adminIdDescSearch({
        name: "",
        code: "",
        discount: "",
        discount_type: null,
        start_date: "",
        end_date: "",
    });
    const promotionSearch = adminIdDescSearch({
        name: "",
        type: null,
        status: null,
    });
    const productSectionSearch = adminIdDescSearch({
        name: "",
        status: null,
    });
    const pushNotificationSearch = adminIdDescSearch({
        title: "",
        role_id: null,
        user_id: null,
    });
    const subscriberSearch = adminIdDescSearch({
        email: "",
        from_date: "",
        to_date: "",
    });
    const transactionSearch = adminOrderIdDescSearch({
        order_serial_no: "",
        transaction_no: "",
        payment_method: null,
        from_date: "",
        to_date: "",
    });
    const paymentGatewaySearch = activeUsersPayload();
    const salesReportSearch = {
        paginate: 1,
        page: 1,
        per_page: 10,
        order_column: "id",
        payment_status: null,
        payment_method: null,
        order_serial_no: "",
        status: null,
        from_date: "",
        to_date: "",
        source: null,
    };
    const productsReportSearch = {
        paginate: 1,
        page: 1,
        per_page: 10,
        order_column: "id",
        name: null,
        product_category_id: null,
        from_date: "",
        to_date: "",
    };
    const posProductSearch = {
        paginate: 0,
        order_column: "id",
        order_type: "asc",
        name: "",
        product_category_id: "",
        product_brand_id: "",
        status: statusEnum.ACTIVE,
    };

    const tasksByPath = {
        "/dashboard": [
            merchantDashboardSetupTask(store),
        ],
        "/admin/dashboard": [
            merchantDashboardSetupTask(store),
        ],
        "/admin/products": [
            dispatchTask(store, "site/lists"),
            dispatchTask(store, "productCategory/depthTrees"),
            dispatchTask(store, "productBrand/lists", supportIdAscPayload),
            dispatchTask(store, "tax/lists", supportIdAscPayload),
            dispatchTask(store, "unit/lists", supportIdAscPayload),
            dispatchTask(store, "barcode/lists", supportIdAscPayload),
            dispatchTask(store, "product/lists", productSearch),
        ],
        "/admin/purchase": [
            dispatchTask(store, "supplier/lists", { page: 1 }),
            dispatchTask(store, "purchase/lists", purchaseSearch),
        ],
        "/admin/damages": [
            dispatchTask(store, "damage/lists", damageSearch),
        ],
        "/admin/stock": [
            dispatchTask(store, "stock/lists", stockSearch),
        ],
        "/admin/reviews": [
            dispatchTask(store, "product/getSimpleProduct", {
                paginate: 0,
                page: 1,
                order_column: "id",
            }),
            dispatchTask(store, "user/lists", activeUserList),
            dispatchTask(store, "review/lists", reviewSearch),
        ],
        "/admin/pos": [
            dispatchTask(store, "productCategory/depthTrees"),
            dispatchTask(store, "productBrand/lists", activeUsersPayload({ paginate: 0 })),
            dispatchTask(store, "user/lists", activeUsersPayload({ role_id: roleEnum.CUSTOMER })),
            dispatchTask(store, "company/lists"),
            dispatchTask(store, "product/lists", posProductSearch),
        ],
        "/admin/pos-orders": [
            dispatchTask(store, "user/lists", activeUserList),
            dispatchTask(store, "posOrder/lists", posOrderSearch),
        ],
        "/admin/online-orders": [
            dispatchTask(store, "user/lists", activeUserList),
            dispatchTask(store, "onlineOrder/lists", onlineOrderSearch),
        ],
        "/admin/return-orders": [
            dispatchTask(store, "user/lists", { vuex: true, order_type: "asc" }),
            dispatchTask(store, "returnOrder/lists", returnOrderSearch),
        ],
        "/admin/return-and-refunds": [
            dispatchTask(store, "user/lists", activeUserList),
            dispatchTask(store, "returnAndRefund/lists", returnAndRefundSearch),
        ],
        "/admin/coupons": [
            dispatchTask(store, "coupon/lists", couponSearch),
        ],
        "/admin/promotions": [
            dispatchTask(store, "promotion/lists", promotionSearch),
        ],
        "/admin/product-sections": [
            dispatchTask(store, "productSection/lists", productSectionSearch),
        ],
        "/admin/push-notifications": [
            dispatchTask(store, "role/lists", adminIdAscPayload()),
            dispatchTask(store, "user/lists", activeUserList),
            dispatchTask(store, "pushNotification/lists", pushNotificationSearch),
        ],
        "/admin/subscribers": [
            dispatchTask(store, "subscriber/lists", subscriberSearch),
        ],
        "/admin/wallet": [
            () => axios.get("merchant/wallet/summary"),
            () => axios.get("merchant/wallet/transactions", { params: { per_page: 12 } }),
            () => axios.get("merchant/wallet/withdrawals", { params: { per_page: 12 } }),
            () => axios.get("merchant/wallet/payout-methods"),
        ],
        "/admin/transactions": [
            dispatchTask(store, "paymentGateway/lists", activeUsersPayload({ excepts: 1 })),
            dispatchTask(store, "transaction/lists", transactionSearch),
        ],
        "/admin/sales-report": [
            dispatchTask(store, "paymentGateway/lists", paymentGatewaySearch),
            dispatchTask(store, "salesReport/lists", salesReportSearch),
            dispatchTask(store, "salesReport/salesReportOverview", salesReportSearch),
        ],
        "/admin/products-report": [
            dispatchTask(store, "product/getSimpleProduct", {
                paginate: 0,
                page: 1,
                order_column: "id",
            }),
            dispatchTask(store, "productCategory/depthTrees"),
            dispatchTask(store, "productsReport/lists", productsReportSearch),
            dispatchTask(store, "productsReport/productsReportOverview", productsReportSearch),
        ],
        "/admin/settings": [
            dispatchTask(store, "countryCode/lists"),
            dispatchTask(store, "company/lists"),
        ],
        "/admin/settings/company": [
            dispatchTask(store, "countryCode/lists"),
            dispatchTask(store, "company/lists"),
        ],
    };

    return tasksByPath[adminPath] || [];
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

    if (isMerchantAdminRoute(route)) {
        return merchantAdminRoutePrefetchTasks(route, store);
    }

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

    if (!isInstantRoute(route)) {
        return Promise.resolve();
    }

    const key = routeKey(route);
    const merchantAdminRoute = isMerchantAdminRoute(route);

    if (!merchantAdminRoute && prefetchedRoutes.has(key)) {
        return Promise.resolve();
    }

    if (!merchantAdminRoute) {
        prefetchedRoutes.add(key);
    }

    const componentKey = `${key}|components`;
    const componentTasks = prefetchedRoutes.has(componentKey)
        ? []
        : routesForComponentPrefetch(router, route)
            .flatMap((prefetchRoute) => componentLoadersFromRoute(prefetchRoute))
            .map((loader) => () => loader());

    prefetchedRoutes.add(componentKey);

    let dataTasks = routePrefetchTasks(route, store);

    if (merchantAdminRoute) {
        const dataKey = `${key}|data`;
        const lastPrefetchAt = prefetchedAdminDataAt.get(dataKey) || 0;
        const now = Date.now();

        if (now - lastPrefetchAt < ADMIN_DATA_PREFETCH_RETRY_MS) {
            dataTasks = [];
        } else {
            prefetchedAdminDataAt.set(dataKey, now);
        }
    }

    return prefetchRequests([...componentTasks, ...dataTasks], 4);
}

function scheduleMerchantAdminWarmup(router, store) {
    if (merchantAdminWarmupStarted || !isMerchantHost() || store?.getters?.authStatus !== true) {
        return;
    }

    merchantAdminWarmupStarted = true;

    idle(() => {
        prefetchRequests(MERCHANT_ADMIN_PREFETCH_PATHS.map((path) => {
            return () => prefetchRoute(router, store, path);
        }), 2);
    });
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

        if (route && isInstantRoute(route)) {
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
        scheduleMerchantAdminWarmup(router, store);
    });

    router.isReady().then(() => {
        scheduleScan(router, store);
        prefetchRoute(router, store, router.currentRoute.value);
        scheduleMerchantAdminWarmup(router, store);
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
