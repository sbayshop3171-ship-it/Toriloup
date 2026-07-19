const storageKey = "shopking_admin_notifications";
const syncEventName = "shopking-admin-notification-updated";
const maxItems = 30;

const categoryRoutes = {
    order: "online-orders",
    pos_order: "pos-orders",
    return_order: "return-orders",
    refund: "return-and-refunds",
    review: "reviews",
    subscriber: "subscribers",
    customer: "customers",
    promotion: "push-notifications",
    support: "",
    message: "",
    error: "",
};

const categoryMenuLanguages = {
    order: "online_orders",
    pos_order: "pos_orders",
    return_order: "return_orders",
    refund: "return_and_refunds",
    review: "reviews",
    subscriber: "subscribers",
    customer: "customers",
    promotion: "push_notifications",
};

const categoryLabelLanguages = {
    support: "support",
    message: "message",
};

const categoryAliases = {
    new_order_found: "order",
    new_order: "order",
    online_order: "order",
    order_notification: "order",
    pos_order: "pos_order",
    return: "return_order",
    returns: "return_order",
    return_order: "return_order",
    return_request: "return_order",
    return_and_refund: "refund",
    refund: "refund",
    review: "review",
    rating: "review",
    subscriber: "subscriber",
    subscription: "subscriber",
    customer: "customer",
    user: "customer",
    promotion: "promotion",
    campaign: "promotion",
    push_notification: "promotion",
    support: "support",
    conversation: "support",
    chat: "support",
    ticket: "support",
    message: "message",
    error: "error",
    failed: "error",
};

const routeCategories = Object.entries(categoryRoutes).reduce((routes, [category, route]) => {
    if (route) {
        routes[route] = category;
    }

    return routes;
}, {});

const categoryMatchers = [
    { category: "refund", terms: ["return-and-refund", "return and refund", "refund"] },
    { category: "return_order", terms: ["return-order", "return order", "return request", "returned"] },
    { category: "pos_order", terms: ["pos-order", "pos order"] },
    { category: "review", terms: ["product review", "review", "rating"] },
    { category: "support", terms: ["support", "conversation", "chat", "ticket", "inbox"] },
    { category: "subscriber", terms: ["subscriber", "newsletter", "subscription"] },
    { category: "customer", terms: ["new customer", "customer", "user registration"] },
    { category: "promotion", terms: ["push notification", "promotion", "campaign", "coupon"] },
    { category: "order", terms: ["new-order-found", "order notification", "online order", "new order", "order"] },
];

const safeWindow = function () {
    return typeof window !== "undefined" ? window : null;
};

const normalizeCategory = function (value) {
    return String(value || "")
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "_")
        .replace(/^_+|_+$/g, "");
};

const normalizeRouteUrl = function (url) {
    return String(url || "")
        .trim()
        .replace(/^https?:\/\/[^/]+/i, "")
        .replace(/^#\/?/, "")
        .replace(/^\/+|\/+$/g, "")
        .replace(/^admin\/?/, "");
};

const compactText = function (parts) {
    return parts
        .filter((part) => part !== undefined && part !== null && part !== "")
        .map((part) => String(part).toLowerCase())
        .join(" ");
};

const firstAvailableRoute = function (payloadOrItem) {
    const data = payloadOrItem?.data || {};

    return normalizeRouteUrl(
        data.routeUrl ||
        data.route_url ||
        data.url ||
        data.path ||
        payloadOrItem?.routeUrl ||
        payloadOrItem?.route_url ||
        payloadOrItem?.url ||
        payloadOrItem?.path ||
        ""
    );
};

const categoryFromRoute = function (routeUrl) {
    const route = normalizeRouteUrl(routeUrl);
    const match = Object.keys(routeCategories).find((menuUrl) => {
        return route === menuUrl || route.startsWith(menuUrl + "/");
    });

    return match ? routeCategories[match] : "";
};

const textForMatching = function (payloadOrItem) {
    const data = payloadOrItem?.data || {};
    const notification = payloadOrItem?.notification || {};

    return compactText([
        data.topicName,
        data.topic_name,
        data.type,
        data.category,
        data.routeUrl,
        data.route_url,
        data.url,
        data.path,
        notification.title,
        notification.body,
        payloadOrItem?.category,
        payloadOrItem?.type,
        payloadOrItem?.title,
        payloadOrItem?.body,
        payloadOrItem?.routeUrl,
    ]);
};

const resolveCategory = function (payloadOrItem) {
    const routeCategory = categoryFromRoute(firstAvailableRoute(payloadOrItem));
    if (routeCategory) {
        return routeCategory;
    }

    const data = payloadOrItem?.data || {};
    const explicitCategory = [
        data.category,
        data.type,
        data.topicName,
        data.topic_name,
        payloadOrItem?.category,
        payloadOrItem?.type,
    ]
        .map((value) => categoryAliases[normalizeCategory(value)])
        .find(Boolean);

    if (explicitCategory) {
        return explicitCategory;
    }

    const searchableText = textForMatching(payloadOrItem);
    const match = categoryMatchers.find((matcher) => {
        return matcher.terms.some((term) => searchableText.includes(term));
    });

    return match?.category || "message";
};

const categoryRoute = function (category) {
    return categoryRoutes[resolveCategory({ category })] || "";
};

const routeMatchesMenuUrl = function (routeUrl, menuUrl) {
    const route = normalizeRouteUrl(routeUrl);
    const menu = normalizeRouteUrl(menuUrl);

    if (!route || !menu) {
        return false;
    }

    return route === menu || route.startsWith(menu + "/");
};

const routeTargetsPath = function (routeUrl, path) {
    const route = normalizeRouteUrl(routeUrl);
    const currentPath = normalizeRouteUrl(path);

    if (!route || !currentPath) {
        return false;
    }

    return route === currentPath || currentPath.startsWith(route + "/") || route.startsWith(currentPath + "/");
};

const canAccessRoute = function (routeUrl, permissions = []) {
    const route = normalizeRouteUrl(routeUrl);

    if (!route) {
        return false;
    }

    if (!Array.isArray(permissions) || permissions.length === 0) {
        return true;
    }

    const routeBase = route.split("/")[0];
    const permission = permissions.find((item) => {
        const permissionUrl = normalizeRouteUrl(item?.url || "");
        const permissionName = normalizeRouteUrl(item?.name || "");

        return [permissionUrl, permissionName].some((value) => {
            return value === route || value === routeBase || route.startsWith(value + "/") || value.startsWith(routeBase + "/");
        });
    });

    return !permission || permission.access !== false;
};

const notificationItemRouteUrl = function (item) {
    const explicitRoute = firstAvailableRoute(item);
    if (explicitRoute) {
        return explicitRoute;
    }

    return categoryRoute(resolveCategory(item));
};

const notificationItemMenuLanguage = function (item) {
    const category = resolveCategory(item);
    return categoryMenuLanguages[category] || "";
};

const notificationItemLabelLanguage = function (item) {
    const category = resolveCategory(item);
    return categoryLabelLanguages[category] || "";
};

const unreadCount = function (items) {
    return Array.isArray(items) ? items.filter((item) => !item.read).length : 0;
};

const unreadCountForMenu = function (items, menuUrl) {
    if (!Array.isArray(items)) {
        return 0;
    }

    return items.filter((item) => {
        return !item.read && routeMatchesMenuUrl(notificationItemRouteUrl(item), menuUrl);
    }).length;
};

const loadItems = function () {
    const browserWindow = safeWindow();
    if (!browserWindow) {
        return [];
    }

    try {
        const rawData = browserWindow.localStorage.getItem(storageKey);
        if (!rawData) {
            return [];
        }

        const parsedItems = JSON.parse(rawData);
        return Array.isArray(parsedItems) ? parsedItems.slice(0, maxItems) : [];
    } catch (error) {
        return [];
    }
};

const emitSyncEvent = function () {
    const browserWindow = safeWindow();
    if (browserWindow) {
        browserWindow.dispatchEvent(new CustomEvent(syncEventName));
    }
};

const persistItems = function (items) {
    const browserWindow = safeWindow();
    if (!browserWindow) {
        return;
    }

    try {
        browserWindow.localStorage.setItem(storageKey, JSON.stringify((items || []).slice(0, maxItems)));
        emitSyncEvent();
    } catch (error) {
        // Local storage may be unavailable in private or restricted browser contexts.
    }
};

const createItemFromPayload = function (payload, options = {}) {
    const category = resolveCategory(payload);
    const explicitRoute = firstAvailableRoute(payload);
    const routeUrl = explicitRoute || categoryRoute(category);
    const allowedRouteUrl = canAccessRoute(routeUrl, options.permissions) ? routeUrl : "";
    const title = payload?.notification?.title || payload?.data?.title || options.fallbackTitle || "Notification";
    const body = payload?.notification?.body || payload?.data?.body || "";

    return {
        id: payload?.messageId || payload?.message_id || Date.now().toString(36) + Math.random().toString(36).slice(2, 8),
        title: title,
        body: body,
        type: category,
        category: category,
        routeUrl: allowedRouteUrl,
        read: false,
        createdAt: new Date().toISOString(),
    };
};

const markItemAsRead = function (items, id) {
    let changed = false;
    const nextItems = (items || []).map((item) => {
        if (item.id === id && !item.read) {
            changed = true;
            return { ...item, read: true };
        }

        return item;
    });

    return { items: nextItems, changed };
};

const markItemsAsReadByRoute = function (items, path) {
    let changed = false;
    const nextItems = (items || []).map((item) => {
        if (!item.read && routeTargetsPath(notificationItemRouteUrl(item), path)) {
            changed = true;
            return { ...item, read: true };
        }

        return item;
    });

    return { items: nextItems, changed };
};

export default {
    storageKey,
    syncEventName,
    maxItems,
    normalizeRouteUrl,
    resolveCategory,
    categoryRoute,
    notificationItemRouteUrl,
    notificationItemMenuLanguage,
    notificationItemLabelLanguage,
    unreadCount,
    unreadCountForMenu,
    loadItems,
    persistItems,
    createItemFromPayload,
    markItemAsRead,
    markItemsAsReadByRoute,
};
