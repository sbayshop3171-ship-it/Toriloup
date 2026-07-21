import VueSimpleAlert from "vue3-simple-alert";
import askEnum from "../enums/modules/askEnum";
import currencyPositionEnum from "../enums/modules/currencyPositionEnum";
import orderStatusEnum from "../enums/modules/orderStatusEnum";
import purchasePaymentStatusEnum from "../enums/modules/purchasePaymentStatusEnum";
import purchaseStatusEnum from "../enums/modules/purchaseStatusEnum";
import returnStatusEnum from "../enums/modules/returnStatusEnum";
import statusEnum from "../enums/modules/statusEnum";
import taxTypeEnum from "../enums/modules/taxTypeEnum";
import store from "../store";

export default {
    phoneNumber: function (e) {
        let char = String.fromCharCode(e.keyCode);
        if (/^[+]?[0-9]*$/.test(char)) return true;
        else e.preventDefault();
    },

    onlyNumber: function (e) {
        let res =
            (e.charCode !== 8 && e.charCode === 0) ||
            (e.charCode >= 48 && e.charCode <= 57);
        if (res) return true;
        else e.preventDefault();
    },

    floatNumber: function (e) {
        let char = String.fromCharCode(e.keyCode);
        if (/^[.]?[0-9]*$/.test(char)) return true;
        else e.preventDefault();
    },

    currencyFormat(amount, decimal = 2, currency = "", position = currencyPositionEnum.LEFT) {
        const parsedAmount = parseFloat(amount);
        const safeAmount = Number.isFinite(parsedAmount) ? parsedAmount : 0;
        const parsedDecimal = parseInt(decimal, 10);
        const safeDecimal = Number.isFinite(parsedDecimal) ? parsedDecimal : 2;
        const symbol = typeof currency === "object" && currency !== null
            ? (currency.symbol || currency.code || "")
            : (currency || "");

        if (position === currencyPositionEnum.LEFT) {
            return symbol + safeAmount.toFixed(safeDecimal);
        } else {
            return safeAmount.toFixed(safeDecimal) + symbol;
        }
    },

    setCurrencyPreference(currencyCode) {
        const code = String(currencyCode || "").trim().toUpperCase();

        if (!code) {
            return;
        }

        try {
            const maxAge = 60 * 60 * 24 * 365;
            document.cookie = `toriloup_currency=${encodeURIComponent(code)}; path=/; max-age=${maxAge}; SameSite=Lax`;
        } catch (e) {}
    },

    floatFormat(amount, decimal = 2) {
        return parseFloat(amount).toFixed(decimal);
    },

    sideDrawerShow: function (id = "sideDrawer") {
        const drawerDivs = document?.querySelectorAll(".drawer");
        const drawerSets = document?.querySelectorAll("[data-drawer]");
        drawerSets?.forEach((drawerSet) => {
            const targetElm = document?.querySelector(
                drawerSet?.dataset?.drawer
            );
            drawerSets?.forEach((drawerBtn) =>
                drawerBtn?.classList?.remove("active")
            );
            drawerDivs?.forEach((drawerDiv) =>
                drawerDiv?.classList?.remove("active")
            );
            targetElm?.classList?.add("active");
            drawerSet?.classList?.add("active");
            document.body.style.overflowY = "hidden";
            document?.querySelector(".backdrop")?.classList?.add("active");
        });
    },
    sideDrawerHide: function (id = "sideDrawer") {
        const drawerDivs = document?.querySelectorAll(".drawer");
        const drawerSets = document?.querySelectorAll("[data-drawer]");
        document?.querySelectorAll("#sidebar")?.forEach((closeBtn) => {
            drawerSets?.forEach((drawerBtn) =>
                drawerBtn?.classList?.remove("active")
            );
            drawerDivs?.forEach((drawerDiv) =>
                drawerDiv?.classList?.remove("active")
            );
            document?.querySelector(".backdrop")?.classList?.remove("active");
            document.body.style.overflowY = "auto";
        });
    },

    modalShow: function (id = ".modal") {
        let modalButton = document?.querySelectorAll("[data-modal]");
        modalButton?.forEach((modalBtn) => {
            const modalTarget = document?.querySelector(id);
            modalTarget?.classList?.add("active");
            document.body.style.overflowY = "hidden";
        });
    },

    modalHide: function (id = ".modal") {
        let modalDivs = document?.querySelectorAll(id);
        document.body.style.overflowY = "auto";
        modalDivs?.forEach((modalDiv) => modalDiv?.classList?.remove("active"));
    },

    recursiveRouter: function (routes, permission) {
        if (!routes || !Array.isArray(routes) || routes.length === 0) {
            return;
        }

        if (
            !permission ||
            !Array.isArray(permission) ||
            permission.length === 0
        ) {
            return;
        }

        try {
            const routesToUpdate = [];

            const collectRoutes = (routeArray) => {
                if (!Array.isArray(routeArray)) return;

                routeArray.forEach((route) => {
                    if (route && typeof route === "object") {
                        if (
                            route.meta?.permissionUrl &&
                            typeof route.meta.permissionUrl === "string"
                        ) {
                            routesToUpdate.push(route);
                        }

                        if (
                            route.children &&
                            Array.isArray(route.children) &&
                            route.children.length > 0
                        ) {
                            collectRoutes(route.children);
                        }
                    }
                });
            };

            collectRoutes(routes);

            if (routesToUpdate.length === 0) {
                return;
            }

            const permissionMap = new Map();
            permission.forEach((perm) => {
                if (
                    perm &&
                    typeof perm === "object" &&
                    perm.url &&
                    typeof perm.url === "string"
                ) {
                    permissionMap.set(perm.url, perm);
                }
            });

            if (permissionMap.size === 0) {
                return;
            }

            routesToUpdate.forEach((route) => {
                try {
                    const perm = permissionMap.get(route.meta.permissionUrl);
                    if (perm) {
                        if (typeof perm.access !== "undefined") {
                            route.meta.access = perm.access;
                        }
                        if (typeof perm.title !== "undefined") {
                            route.meta.title = perm.title;
                        }
                    }
                } catch (routeError) {}
            });
        } catch (error) {}
    },

    textShortener: function (text, number = 30) {
        if (text) {
            if (!(text.length < number)) {
                return text.substring(0, number) + "..";
            }
        }
        return text;
    },
    htmlTagRemover: function (text) {
        if (text != null && text !== "" && isNaN(text)) {
            return text.replace(/(<([^>]+)>)/gi, "");
        }
        return text;
    },
    statusClass: function (status) {
        if (status === statusEnum.ACTIVE) {
            return "db-table-badge text-green-600 bg-green-100";
        } else {
            return "db-table-badge text-red-600 bg-red-100";
        }
    },

    askClass: function (ask) {
        if (ask === askEnum.YES) {
            return "db-table-badge text-green-600 bg-green-100";
        } else {
            return "db-table-badge text-red-600 bg-red-100";
        }
    },

    requestHandler: function (requests) {
        let i = 1;
        let what = "?";
        let response = "";

        for (let request in requests) {
            if (requests[request] !== "" && requests[request] !== null) {
                if (i !== 1) {
                    response += "&";
                }
                response += request + "=" + requests[request];
            }
            i++;
        }

        if (response) {
            response = what + response;
        }

        return response;
    },

    responsiveLoad: function () {
        const mainHeader = document?.querySelector(".db-header");
        const subHeader = document?.querySelector(".sub-header");
        const mainHeight = mainHeader?.scrollHeight || 0;

        if (subHeader) {
            subHeader.style.top = `${mainHeight}px`;
        }

        document?.documentElement?.style?.setProperty("--db-header-height", `${mainHeight}px`);
    },

    permissionChecker: function (permissionName) {
        let i,
            permissions = store.getters.authPermission;
        for (i = 0; i < permissions.length; i++) {
            if (
                typeof permissions[i].name !== "undefined" &&
                permissions[i].name
            ) {
                if (permissions[i].name === permissionName) {
                    return permissions[i].access;
                }
            }
        }
    },

    formDataShow: function (formData) {
        for (let pair of formData.entries()) {
            console.log(pair[0] + " : " + pair[1]);
        }
    },
    destroyConfirmation: function () {
        return new VueSimpleAlert.confirm(
            "You will not be able to recover the deleted record!",
            "Are you sure?",
            "warning",
            {
                confirmButtonText: "Yes, Delete it!",
                cancelButtonText: "No, Cancel!",
                confirmButtonColor: "#696cff",
                cancelButtonColor: "#8592a3",
            }
        );
    },
    submitConfirmation: function () {
        return new VueSimpleAlert.confirm(
            "The action cannot be reversed!",
            "Are you sure?",
            "warning",
            {
                confirmButtonText: "Yes, Confirm!",
                cancelButtonText: "No, Cancel!",
                confirmButtonColor: "#696cff",
                cancelButtonColor: "#8592a3",
            }
        );
    },
    taxTypeClass: function (type) {
        if (type === taxTypeEnum.FIXED) {
            return "db-table-badge text-blue-500 bg-blue-100";
        } else {
            return "db-table-badge text-orange-500 bg-orange-100";
        }
    },
    underscoreToSpace: function (str) {
        return str.replace(/_/g, " ");
    },
    decimalPoint: function (num, length = 2) {
        return Number.parseFloat(num).toFixed(length);
    },
    orderStatusClass: function (status) {
        if (status === orderStatusEnum.PENDING) {
            return "bg-amber-100 text-amber-500";
        } else if (status === orderStatusEnum.CONFIRMED) {
            return "bg-indigo-100 text-indigo-500";
        } else if (status === orderStatusEnum.ON_THE_WAY) {
            return "bg-cyan-100 text-cyan-500";
        } else if (status === orderStatusEnum.DELIVERED) {
            return "bg-green-100 text-green-500";
        } else if (status === orderStatusEnum.CANCELED) {
            return "bg-red-100 text-red-500";
        } else {
            return "bg-red-100 text-red-500";
        }
    },

    purchasePaymentStatusClass: function (status) {
        if (status === purchasePaymentStatusEnum.PENDING) {
            return "bg-amber-100 text-amber-500";
        } else if (status === purchasePaymentStatusEnum.PARTIAL_PAID) {
            return "bg-indigo-100 text-indigo-500";
        } else if (status === purchasePaymentStatusEnum.FULLY_PAID) {
            return "bg-green-100 text-green-500";
        }
    },
    purchaseStatusClass: function (status) {
        if (status === purchaseStatusEnum.PENDING) {
            return "bg-amber-100 text-amber-500";
        } else if (status === purchaseStatusEnum.ORDERED) {
            return "bg-indigo-100 text-indigo-500";
        } else if (status === purchaseStatusEnum.RECEIVED) {
            return "bg-green-100 text-green-500";
        }
    },

    returnStatusClass: function (status) {
        if (status == returnStatusEnum.PENDING) {
            return "text-[#F6A609]";
        } else if (status == returnStatusEnum.ACCEPT) {
            return "text-[#2AC769]";
        } else {
            return "text-[#FB4E4E]";
        }
    },

    cancelOrder: function () {
        return new VueSimpleAlert.confirm(
            "You want to cancel your order?",
            "Are you sure?",
            "warning",
            {
                confirmButtonText: "Yes, Cancel it!",
                cancelButtonText: "No, Cancel",
                confirmButtonColor: "#696cff",
                cancelButtonColor: "#8592a3",
            }
        );
    },

    acceptOrder: function () {
        return new VueSimpleAlert.confirm(
            "You will not be able to cancel the order!",
            "Are you sure?",
            "warning",
            {
                confirmButtonText: "Yes, Accept it!",
                cancelButtonText: "No, Cancel!",
                confirmButtonColor: "#696cff",
                cancelButtonColor: "#8592a3",
            }
        );
    },
    closeMobileFilterSheets: function () {
        document?.querySelectorAll(".table-filter-div.active").forEach((filterSheet) => {
            filterSheet.classList.remove("active");
            filterSheet.style.height = "0px";
            filterSheet.style.overflow = "hidden";
            filterSheet.style.opacity = "0";
            filterSheet.style.visibility = "hidden";
        });

        document?.body?.classList?.remove("merchant-mobile-filter-open");
        this.syncMobileBackdropState();
    },
    emitMobileLayerEvent: function (eventName, detail = {}) {
        if (typeof window === "undefined") {
            return;
        }

        window.dispatchEvent(new CustomEvent(eventName, { detail }));
    },
    syncMobileBackdropState: function () {
        const body = document?.body;
        const backdrop = document?.querySelector(".backdrop");

        if (!body || !backdrop) {
            return;
        }

        const shouldLock = !!document?.querySelector(".db-sidebar.active, .table-filter-div.active") ||
            body.classList.contains("merchant-mobile-settings-open");

        backdrop.classList.toggle("active", shouldLock);
        body.classList.toggle("overflow-hidden", shouldLock);
        body.style.overflowY = shouldLock ? "hidden" : "auto";
    },
    openMobileSettingsHub: function () {
        document?.body?.classList?.add("merchant-mobile-settings-open");
        this.syncMobileBackdropState();
        this.emitMobileLayerEvent("merchant-mobile-settings-hub-change", { open: true });
    },
    closeMobileSettingsHub: function () {
        document?.body?.classList?.remove("merchant-mobile-settings-open");
        this.syncMobileBackdropState();
        this.emitMobileLayerEvent("merchant-mobile-settings-hub-change", { open: false });
    },
    handleSlide: function (id) {
        const targetElement = document.querySelector(`#${id}`);

        if (!targetElement) {
            return;
        }

        if (targetElement.classList.contains("table-filter-div") && this.isMobileSidebarBreakpoint()) {
            const willOpen = !targetElement.classList.contains("active");

            if (willOpen) {
                this.closeSidebar();
                this.closeMobileSettingsHub();
            }

            document?.querySelectorAll(".table-filter-div.active").forEach((filterSheet) => {
                if (filterSheet !== targetElement) {
                    filterSheet.classList.remove("active");
                    filterSheet.style.height = "0px";
                    filterSheet.style.overflow = "hidden";
                    filterSheet.style.opacity = "0";
                    filterSheet.style.visibility = "hidden";
                }
            });

            targetElement.classList.toggle("active", willOpen);
            targetElement.style.height = willOpen ? "auto" : "0px";
            targetElement.style.overflow = willOpen ? "auto" : "hidden";
            targetElement.style.opacity = willOpen ? "1" : "0";
            targetElement.style.visibility = willOpen ? "visible" : "hidden";
            document?.body?.classList?.toggle("merchant-mobile-filter-open", willOpen);
            this.syncMobileBackdropState();
            return;
        }

        targetElement.classList.add(
            "transition-all",
            "duration-300",
            "ease-in-out"
        );

        if (targetElement.style.visibility == "visible") {
            targetElement.style.height = "0px";
            targetElement.style.overflow = "hidden";
            targetElement.style.opacity = "0";
            targetElement.style.visibility = "hidden";
        } else {
            targetElement.style.height = targetElement.scrollHeight + "px";
            setTimeout(() => {
                targetElement.style.overflow = "visible";
            }, 300);
            targetElement.style.opacity = "1";
            targetElement.style.visibility = "visible";
        }
    },
    handleTab: function (event, targetID, targetButton, targetDiv, active) {
        const targetBtns = document.querySelectorAll(targetButton);
        const targetDivs = document.querySelectorAll(targetDiv);
        const currentBtn = event.currentTarget;
        const currentDiv = document.querySelector(targetID);
        targetBtns.forEach((item) => item.classList.remove(active));
        targetDivs.forEach((item) => item.classList.remove(active));
        currentBtn.classList.add(active);
        currentDiv.classList.add(active);
    },
    openSettingMenu: function (event) {
        const btn = event.currentTarget;
        const options = btn.nextElementSibling;
        const isExpanded = btn.getAttribute("aria-expanded") === "true";
        document.querySelectorAll(".settings-btn").forEach((otherBtn) => {
            if (
                otherBtn !== btn &&
                otherBtn.getAttribute("aria-expanded") === "true"
            ) {
                const otherOptions = otherBtn.nextElementSibling;
                otherOptions.style.height = "0px";
                otherOptions.style.margin = "0px";
                otherBtn
                    .querySelector(".icon")
                    .classList.remove("fa-chevron-up");
                otherBtn
                    .querySelector(".icon")
                    .classList.add("fa-chevron-down");
                otherBtn.setAttribute("aria-expanded", "false");
            }
        });

        if (isExpanded) {
            options.style.height = "0px";
            options.style.margin = "0px";
            btn.querySelector(".icon").classList.remove("fa-chevron-up");
            btn.querySelector(".icon").classList.add("fa-chevron-down");
        } else {
            options.style.height = "auto";
            const pixel = options.scrollHeight;
            options.style.height = "0px";
            requestAnimationFrame(() => {
                options.style.height = `${pixel}px`;
                options.style.margin = "8px 0px 0px 0px";
            });

            btn.querySelector(".icon").classList.remove("fa-chevron-down");
            btn.querySelector(".icon").classList.add("fa-chevron-up");
        }

        btn.setAttribute("aria-expanded", !isExpanded);
    },

    closeSettingMenu: function (event) {
        if (!event.target.closest(".settings-btn, .settings-menu-panel")) {
            document.querySelectorAll(".settings-btn").forEach((btn) => {
                if (btn.getAttribute("aria-expanded") === "true") {
                    const options = btn.nextElementSibling;
                    options.style.height = "0px";
                    options.style.margin = "0px";
                    btn.querySelector(".icon").classList.remove(
                        "fa-chevron-up"
                    );
                    btn.querySelector(".icon").classList.add("fa-chevron-down");
                    btn.setAttribute("aria-expanded", "false");
                }
            });
        }
    },
    isMobileSidebarBreakpoint: function () {
        return typeof window !== "undefined" && window.matchMedia("(max-width: 1023px)").matches;
    },

    toggleSidebar: function () {
        const main = document?.querySelector(".db-main");
        const sidebar = document?.querySelector(".db-sidebar");

        if (!sidebar) {
            return;
        }

        const willOpen = !sidebar.classList.contains("active");

        if (willOpen) {
            this.closeMobileSettingsHub();
            this.closeMobileFilterSheets();
        }

        main?.classList?.toggle("expand", willOpen);
        sidebar.classList.toggle("active", willOpen);

        if (this.isMobileSidebarBreakpoint()) {
            document?.body?.classList?.toggle("merchant-mobile-sidebar-open", willOpen);
            this.syncMobileBackdropState();
        } else {
            document?.body?.classList?.remove("merchant-mobile-sidebar-open");
            this.syncMobileBackdropState();
        }

        this.emitMobileLayerEvent("merchant-mobile-sidebar-change", { open: willOpen });
    },

    closeSidebar: function () {
        const main = document?.querySelector(".db-main");
        const sidebar = document?.querySelector(".db-sidebar");

        main?.classList?.remove("expand");
        sidebar?.classList?.remove("active");
        document?.body?.classList?.remove("merchant-mobile-sidebar-open");
        this.syncMobileBackdropState();
        this.emitMobileLayerEvent("merchant-mobile-sidebar-change", { open: false });
    },
    closeMobilePanels: function () {
        this.closeMobileFilterSheets();
        this.closeSidebar();
        this.closeMobileSettingsHub();
    },

    normalizeSidebarForViewport: function () {
        this.responsiveLoad();

        if (!this.isMobileSidebarBreakpoint()) {
            this.closeMobileFilterSheets();
            this.closeMobileSettingsHub();
            const backdrop = document?.querySelector(".backdrop");
            document?.body?.classList?.remove("merchant-mobile-sidebar-open");

            if (backdrop?.classList?.contains("active")) {
                this.closeSidebar();
                return;
            }

            this.syncMobileBackdropState();
        }
    },

    openCanvas: function (targetID) {
        setTimeout(() => {
            const targetElement = document.getElementById(targetID);
            targetElement.classList.add('active');
            document.body.classList.add('overflow-hidden');
            document.body.style.overflowY = 'hidden';
        }, 50);
    },

    closeCanvas: function (targetID) {
        const targetElement = document.getElementById(targetID);
        targetElement.classList.remove('active');
        document.body.classList.remove('overflow-hidden');
        document.body.style.overflowY = 'auto'
    },
};
