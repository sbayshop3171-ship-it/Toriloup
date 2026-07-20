const tableReadyAttribute = "data-mobile-card-ready";
const tableEnabledAttribute = "data-mobile-card";
const ignoredHeaderText = ["", "#"];

const safeDocument = function () {
    return typeof document !== "undefined" ? document : null;
};

const visibleText = function (element) {
    return String(element?.innerText || element?.textContent || "")
        .replace(/\s+/g, " ")
        .trim();
};

const labelsForTable = function (table) {
    const headerCells = Array.from(table?.querySelectorAll("thead th") || []);

    return headerCells.map((cell) => visibleText(cell));
};

const isEnhancedTable = function (table) {
    if (!table?.classList?.contains("db-table")) {
        return false;
    }

    return table.getAttribute(tableEnabledAttribute) !== "false";
};

const enhanceTable = function (table) {
    if (!isEnhancedTable(table)) {
        return;
    }

    const labels = labelsForTable(table);
    if (labels.length === 0) {
        return;
    }

    table.setAttribute(tableEnabledAttribute, "true");
    table.setAttribute(tableReadyAttribute, "true");
    table.closest(".db-table-responsive")?.setAttribute(tableReadyAttribute, "true");

    Array.from(table.querySelectorAll("tbody tr")).forEach((row) => {
        const cells = Array.from(row.children || []).filter((cell) => {
            return String(cell?.tagName || "").toLowerCase() === "td";
        });

        cells.forEach((cell, index) => {
            const label = labels[index] || "";
            const normalizedLabel = label.toLowerCase();

            if (!cell.hasAttribute("data-label")) {
                cell.setAttribute("data-label", ignoredHeaderText.includes(normalizedLabel) ? "" : label);
            }

            if (cell.colSpan > 1 || cells.length === 1) {
                cell.setAttribute("data-mobile-card-wide", "true");
            }

            if (normalizedLabel.includes("action")) {
                cell.classList.add("db-table-mobile-action");
            }
        });
    });
};

const enhanceMobileTables = function (root = safeDocument()) {
    const scope = root?.querySelectorAll ? root : safeDocument();

    if (!scope) {
        return;
    }

    scope.querySelectorAll("table.db-table").forEach((table) => enhanceTable(table));
};

const scheduleEnhancement = function () {
    const browserWindow = typeof window !== "undefined" ? window : null;

    if (!browserWindow) {
        enhanceMobileTables();
        return;
    }

    browserWindow.requestAnimationFrame(() => enhanceMobileTables());
};

const startBackendMobileEnhancements = function () {
    const doc = safeDocument();
    if (!doc) {
        return () => {};
    }

    doc.documentElement.classList.add("backend-mobile-enhanced");
    scheduleEnhancement();

    if (typeof MutationObserver === "undefined") {
        return () => {};
    }

    const observer = new MutationObserver(() => scheduleEnhancement());
    observer.observe(doc.body, {
        childList: true,
        subtree: true,
    });

    return () => {
        observer.disconnect();
        doc.documentElement.classList.remove("backend-mobile-enhanced");
    };
};

export default {
    enhanceMobileTables,
    startBackendMobileEnhancements,
};
