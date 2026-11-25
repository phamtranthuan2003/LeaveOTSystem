/* Everland HR - UI behaviours */
(function () {
    "use strict";

    const $ = (sel, root) => (root || document).querySelector(sel);
    const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));

    document.addEventListener("DOMContentLoaded", function () {
        initSidebar();
        initDropdowns();
        initModals();
        initAlerts();
        initConfirm();
        initLeaveCalculator();
        initOtCalculator();
        initPasswordToggles();
        initSorting();
        initAutoSubmit();
        initRangeHints();
    });

    /* ---------- Sidebar ---------- */
    function initSidebar() {
        const toggle = $("#sidebar-toggle");
        const sidebar = $("#sidebar");
        const overlay = $("#sidebar-overlay");
        if (!toggle || !sidebar) return;

        const close = () => {
            sidebar.classList.remove("open");
            if (overlay) overlay.classList.remove("open");
            document.body.style.overflow = "";
        };
        toggle.addEventListener("click", () => {
            sidebar.classList.toggle("open");
            if (overlay) overlay.classList.toggle("open", sidebar.classList.contains("open"));
        });
        if (overlay) overlay.addEventListener("click", close);
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape") close();
        });
    }

    /* ---------- Dropdowns ---------- */
    function initDropdowns() {
        $$("[data-dropdown]").forEach((wrap) => {
            const trigger = wrap.querySelector("[data-dropdown-toggle]");
            if (!trigger) return;
            trigger.addEventListener("click", (e) => {
                e.stopPropagation();
                const open = wrap.classList.contains("is-open");
                closeAllDropdowns();
                wrap.classList.toggle("is-open", !open);
            });
        });

        document.addEventListener("click", () => closeAllDropdowns());
        document.addEventListener("keydown", (e) => {
            if (e.key === "Escape") closeAllDropdowns();
        });
        $$(".dropdown-menu").forEach((menu) => menu.addEventListener("click", (e) => e.stopPropagation()));
    }

    function closeAllDropdowns() {
        $$(".dropdown.is-open").forEach((d) => d.classList.remove("is-open"));
    }

    /* ---------- Modals ---------- */
    function initModals() {
        $$("[data-modal-open]").forEach((btn) => {
            btn.addEventListener("click", (e) => {
                e.preventDefault();
                const target = $(btn.getAttribute("data-modal-open"));
                if (target) {
                    target.classList.add("is-open");
                    document.body.style.overflow = "hidden";
                    const first = target.querySelector("textarea, input, select");
                    if (first) setTimeout(() => first.focus(), 60);
                }
            });
        });

        $$(".modal").forEach((modal) => {
            const close = () => {
                modal.classList.remove("is-open");
                document.body.style.overflow = "";
            };
            $$("[data-modal-close]", modal).forEach((btn) => {
                if (btn.hasAttribute("data-modal-submit")) return;
                btn.addEventListener("click", (e) => {
                    e.preventDefault();
                    close();
                });
            });
            modal.addEventListener("mousedown", (e) => {
                if (e.target === modal) close();
            });
            document.addEventListener("keydown", (e) => {
                if (e.key === "Escape" && modal.classList.contains("is-open")) close();
            });
        });
    }

    /* ---------- Flash alerts ---------- */
    function initAlerts() {
        $$("[data-dismiss-alert]").forEach((btn) => {
            btn.addEventListener("click", () => {
                const alert = btn.closest(".alert");
                if (alert) alert.remove();
            });
        });
        $$(".alert[data-auto-dismiss]").forEach((alert) => {
            setTimeout(() => {
                alert.style.transition = "opacity .4s, transform .4s";
                alert.style.opacity = "0";
                alert.style.transform = "translateY(-4px)";
                setTimeout(() => alert.remove(), 420);
            }, 6500);
        });
    }

    /* ---------- Confirm actions ---------- */
    function initConfirm() {
        document.addEventListener("click", function (e) {
            const el = e.target.closest("[data-confirm]");
            if (!el) return;
            if (!window.confirm(el.getAttribute("data-confirm"))) {
                e.preventDefault();
                e.stopPropagation();
            }
        });
        $$("form[data-confirm]").forEach((form) => {
            form.addEventListener("submit", (e) => {
                if (!window.confirm(form.getAttribute("data-confirm"))) e.preventDefault();
            });
        });
    }

    /* ---------- Leave working days ---------- */
    function holidays() {
        const node = document.getElementById("holiday-data");
        if (!node) return [];
        try {
            const data = JSON.parse(node.textContent || "[]");
            return Array.isArray(data) ? data : [];
        } catch (err) {
            return [];
        }
    }

    function parseDate(value) {
        if (!value) return null;
        const parts = value.split("-").map(Number);
        if (parts.length !== 3) return null;
        const d = new Date(parts[0], parts[1] - 1, parts[2]);
        return isNaN(d.getTime()) ? null : d;
    }

    function initLeaveCalculator() {
        const start = $("#leave-start");
        const end = $("#leave-end");
        const dayTypeInputs = $$('input[name="day_type"]');
        const dayType = $("#leave-day-type");
        const output = $("#leave-total-days");
        const hint = $("#leave-day-hint");
        if (!start || !end || !output) return;

        const holidaySet = new Set(holidays());

        const getDayType = () => {
            const checked = dayTypeInputs.find((input) => input.checked);
            return checked ? checked.value : "full";
        };

        function recalc() {
            const s = parseDate(start.value);
            const e = parseDate(end.value) || s;
            if (!s || !e) {
                output.value = "";
                if (hint) hint.textContent = "Chọn ngày để tính số ngày nghỉ";
                return;
            }
            if (e < s) {
                output.value = "";
                if (hint) hint.textContent = "Ngày kết thúc phải sau ngày bắt đầu";
                return;
            }
            const half = getDayType() !== "full";
            if (half && start.value !== end.value) {
                if (hint) {
                    hint.textContent = "Nghỉ nửa ngày chỉ áp dụng trong cùng một ngày";
                    hint.style.color = "#d83b4b";
                }
                output.value = "";
                return;
            }
            let days = 0;
            if (half) {
                days = 0.5;
            } else {
                const cursor = new Date(s.getTime());
                while (cursor <= e) {
                    const day = cursor.getDay();
                    const key = cursor.getFullYear() + "-" + String(cursor.getMonth() + 1).padStart(2, "0") + "-" + String(cursor.getDate()).padStart(2, "0");
                    if (day !== 0 && day !== 6 && !holidaySet.has(key)) days += 1;
                    cursor.setDate(cursor.getDate() + 1);
                }
            }
            output.value = days;
            if (hint) {
                hint.style.color = "";
                hint.textContent = days > 0
                    ? "Tính theo ngày làm việc (Thứ 2 - Thứ 6), trừ ngày lễ"
                    : "Khoảng thời gian này không có ngày làm việc";
            }
        }

        [start, end].forEach((el) => {
            el.addEventListener("change", recalc);
            el.addEventListener("input", recalc);
        });
        dayTypeInputs.forEach((el) => el.addEventListener("change", recalc));
        if (dayType) dayType.addEventListener("change", recalc);
        recalc();
    }

    function initRangeHints() {
        $$("[data-summary-target]").forEach((el) => {
            el.addEventListener("change", updateSummary);
        });
    }

    function updateSummary() {}

    /* ---------- OT hours ---------- */
    function initOtCalculator() {
        const date = $("#ot-date");
        const start = $("#ot-start");
        const end = $("#ot-end");
        const hoursOut = $("#ot-hours");
        const typeOut = $("#ot-type-hint");
        const dayType = $("#ot-day-type");
        if (!date || !start || !end) return;

        function detectType() {
            const d = parseDate(date.value);
            if (!d || !dayType) return;
            const key = date.value;
            const holidaySet = new Set(holidays());
            if (holidaySet.has(key)) dayType.value = "holiday";
            else if (d.getDay() === 0 || d.getDay() === 6) dayType.value = "weekend";
            else dayType.value = "weekday";
            if (typeOut) {
                const rateOf = (value) => {
                    const option = dayType.querySelector('option[value="' + value + '"]');
                    return option && option.dataset.rate ? option.dataset.rate.replace(".", ",") : null;
                };
                const map = {
                    holiday: "Ngày lễ · hệ số x" + (rateOf("holiday") || "3"),
                    weekend: "Cuối tuần · hệ số x" + (rateOf("weekend") || "2"),
                    weekday: "Ngày thường · hệ số x" + (rateOf("weekday") || "1,5"),
                };
                typeOut.textContent = map[dayType.value] || "";
            }
        }

        function calcHours() {
            if (!hoursOut) return;
            if (!start.value || !end.value) {
                hoursOut.value = "";
                return;
            }
            const [sh, sm] = start.value.split(":").map(Number);
            const [eh, em] = end.value.split(":").map(Number);
            let mins = eh * 60 + em - (sh * 60 + sm);
            if (mins <= 0) {
                hoursOut.value = "";
                if (typeOut) typeOut.textContent = "Giờ kết thúc phải sau giờ bắt đầu";
                return;
            }
            const hours = Math.round((mins / 60) * 100) / 100;
            hoursOut.value = hours;
            detectType();
        }

        date.addEventListener("change", () => {
            detectType();
            calcHours();
        });
        start.addEventListener("change", calcHours);
        start.addEventListener("input", calcHours);
        end.addEventListener("change", calcHours);
        end.addEventListener("input", calcHours);
        detectType();
        calcHours();
    }

    /* ---------- Password reveal ---------- */
    function initPasswordToggles() {
        $$("[data-toggle-password]").forEach((btn) => {
            btn.addEventListener("click", () => {
                const target = document.querySelector(btn.getAttribute("data-toggle-password"));
                if (!target) return;
                target.type = target.type === "password" ? "text" : "password";
                btn.classList.toggle("is-active");
            });
        });
    }

    /* ---------- Table sorting ---------- */
    function initSorting() {
        $$("[data-sort-table]").forEach((table) => {
            $$("th[data-sort]", table).forEach((th) => {
                th.style.cursor = "pointer";
                th.title = "Nhấn để sắp xếp";
                th.addEventListener("click", () => {
                    const index = Array.from(th.parentNode.children).indexOf(th);
                    const isAsc = th.dataset.dir !== "asc";
                    $$("th[data-sort]", table).forEach((h) => {
                        delete h.dataset.dir;
                        h.querySelector(".sort-indicator")?.remove();
                    });
                    th.dataset.dir = isAsc ? "asc" : "desc";
                    const indicator = document.createElement("span");
                    indicator.className = "sort-indicator";
                    indicator.style.marginLeft = "4px";
                    indicator.textContent = isAsc ? "▲" : "▼";
                    th.appendChild(indicator);

                    const tbody = table.tBodies[0];
                    const rows = Array.from(tbody.rows);
                    rows.sort((a, b) => {
                        const av = cellValue(a.cells[index]);
                        const bv = cellValue(b.cells[index]);
                        const cmp = av.localeCompare(bv, "vi", { numeric: true, sensitivity: "base" });
                        return isAsc ? cmp : -cmp;
                    });
                    rows.forEach((r) => tbody.appendChild(r));
                });
            });
        });
    }

    function cellValue(cell) {
        if (!cell) return "";
        const raw = cell.getAttribute("data-sort-value");
        return raw !== null ? raw : cell.textContent.trim();
    }

    /* ---------- Auto submit filters ---------- */
    function initAutoSubmit() {
        $$("[data-auto-submit]").forEach((el) => {
            el.addEventListener("change", () => {
                const form = el.closest("form");
                if (form) form.submit();
            });
        });
    }
})();
