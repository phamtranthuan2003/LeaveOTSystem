/* Everland HR - UI behaviours */
(function () {
    "use strict";

    const $ = (sel, root) => (root || document).querySelector(sel);
    const $$ = (sel, root) => Array.from((root || document).querySelectorAll(sel));

    document.addEventListener("DOMContentLoaded", function () {
        initSidebar();
        initDropdowns();
        initModals();
        initBalanceGrant();
        initAlerts();
        initConfirm();
        initLeaveCalculator();
        initOtCalculator();
        initPasswordToggles();
        initSorting();
        initAutoSubmit();
        initRangeHints();
        initSearchableSelects();
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

        const scrollKey = "everland.sidebar.scroll";
        const saveScroll = () => {
            try {
                sessionStorage.setItem(scrollKey, String(sidebar.scrollTop));
            } catch (e) {}
        };
        sidebar.addEventListener("scroll", saveScroll, { passive: true });
        sidebar.addEventListener("click", (e) => {
            if (e.target.closest("a")) saveScroll();
        });
        window.addEventListener("beforeunload", saveScroll);
        window.addEventListener("pageshow", (e) => {
            if (!e.persisted) return;
            try {
                sidebar.scrollTop = parseInt(sessionStorage.getItem(scrollKey) || "0", 10) || 0;
            } catch (err) {}
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

    /* ---------- Balance grant: switch leave type ---------- */
    function initBalanceGrant() {
        $$("[data-grant-type]").forEach((select) => {
            const form = select.closest("form");
            if (!form) return;

            const typeName = $("[data-grant-type-name]", form);
            const entitled = $("[data-grant-entitled]", form);
            const used = $("[data-grant-used]", form);
            const remaining = $("[data-grant-remaining]", form);
            if (!typeName || !entitled || !used || !remaining) return;

            const fmt = (value) => {
                const number = parseFloat(value);
                return new Intl.NumberFormat("vi-VN", { maximumFractionDigits: 1 }).format(isNaN(number) ? 0 : number);
            };
            const render = () => {
                const option = select.options[select.selectedIndex];
                if (!option) return;
                typeName.textContent = option.textContent.trim();
                entitled.textContent = fmt(option.getAttribute("data-entitled"));
                used.textContent = fmt(option.getAttribute("data-used"));
                remaining.textContent = fmt(option.getAttribute("data-remaining"));
            };

            select.addEventListener("change", render);
            render();
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
                const key = s.getFullYear() + "-" + String(s.getMonth() + 1).padStart(2, "0") + "-" + String(s.getDate()).padStart(2, "0");
                const day = s.getDay();
                if (day === 0 || holidaySet.has(key)) {
                    days = 0;
                } else if (day === 6) {
                    days = getDayType() === "morning" ? 0.5 : 0;
                } else {
                    days = 0.5;
                }
            } else {
                const cursor = new Date(s.getTime());
                while (cursor <= e) {
                    const day = cursor.getDay();
                    const key = cursor.getFullYear() + "-" + String(cursor.getMonth() + 1).padStart(2, "0") + "-" + String(cursor.getDate()).padStart(2, "0");
                    if (day !== 0 && !holidaySet.has(key)) days += day === 6 ? 0.5 : 1;
                    cursor.setDate(cursor.getDate() + 1);
                }
            }
            output.value = days;
            if (hint) {
                hint.style.color = "";
                const saturdayAfternoon = half && s.getDay() === 6 && getDayType() === "afternoon"
                    && days === 0 && !holidaySet.has(s.getFullYear() + "-" + String(s.getMonth() + 1).padStart(2, "0") + "-" + String(s.getDate()).padStart(2, "0"));
                hint.textContent = saturdayAfternoon
                    ? "Thứ 7 chỉ làm việc buổi sáng"
                    : (days > 0
                        ? "Tính theo ngày làm việc (T2 - T6, sáng T7), trừ ngày lễ"
                        : "Khoảng thời gian này không có ngày làm việc");
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
            const notify = () => hoursOut.dispatchEvent(new Event("input", { bubbles: true }));
            if (!start.value || !end.value) {
                hoursOut.value = "";
                notify();
                return;
            }
            const [sh, sm] = start.value.split(":").map(Number);
            const [eh, em] = end.value.split(":").map(Number);
            let mins = eh * 60 + em - (sh * 60 + sm);
            if (mins < 0) {
                // Giờ kết thúc nhỏ hơn giờ bắt đầu: OT qua đêm, kết thúc vào hôm sau.
                mins += 24 * 60;
            }
            if (mins === 0) {
                hoursOut.value = "";
                notify();
                if (typeOut) typeOut.textContent = "Giờ kết thúc phải khác giờ bắt đầu";
                return;
            }
            const hours = Math.round((mins / 60) * 100) / 100;
            hoursOut.value = hours;
            detectType();
            notify();
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

    /* ---------- Searchable select ---------- */
    function initSearchableSelects() {
        const normalize = (text) => (text || "")
            .normalize("NFD")
            .replace(/[\u0300-\u036f]/g, "")
            .replace(/đ/g, "d")
            .replace(/Đ/g, "D")
            .toLowerCase();

        $$("select[data-searchable]").forEach((select) => {
            const all = $$("option", select).map((option) => ({
                value: option.value,
                label: option.textContent.trim(),
            }));
            const placeholder = select.dataset.searchPlaceholder || "Tìm theo tên…";
            const emptyLabel = (all.find((option) => option.value === "") || {}).label || "— Không có —";

            const wrap = document.createElement("div");
            wrap.className = "combo";
            select.parentNode.insertBefore(wrap, select);
            wrap.appendChild(select);

            const trigger = document.createElement("button");
            trigger.type = "button";
            trigger.className = "input select combo-trigger";
            trigger.setAttribute("aria-haspopup", "listbox");
            trigger.setAttribute("aria-expanded", "false");
            if (select.classList.contains("is-invalid")) trigger.classList.add("is-invalid");
            if (select.disabled) trigger.disabled = true;
            wrap.appendChild(trigger);

            const panel = document.createElement("div");
            panel.className = "combo-panel";
            panel.hidden = true;

            const search = document.createElement("input");
            search.type = "search";
            search.className = "input combo-search";
            search.placeholder = placeholder;
            search.setAttribute("aria-label", placeholder);

            const list = document.createElement("div");
            list.className = "combo-list";
            list.setAttribute("role", "listbox");

            panel.append(search, list);
            wrap.appendChild(panel);

            select.classList.add("combo-native");
            select.tabIndex = -1;

            const label = select.id ? document.querySelector('label[for="' + select.id + '"]') : null;

            const syncTrigger = () => {
                const chosen = all.find((option) => option.value === select.value);
                trigger.textContent = chosen && chosen.value !== "" ? chosen.label : emptyLabel;
                trigger.classList.toggle("is-placeholder", !chosen || chosen.value === "");
            };

            let activeIndex = -1;

            const choose = (option) => {
                select.value = option.value;
                select.dispatchEvent(new Event("change", { bubbles: true }));
                syncTrigger();
                close();
                trigger.focus();
            };

            const renderList = () => {
                const keyword = normalize(search.value.trim());
                list.innerHTML = "";
                activeIndex = -1;

                const items = all.filter((option) => {
                    if (option.value === "" && keyword) return false;
                    return !keyword || normalize(option.label).includes(keyword);
                });

                if (!items.length) {
                    const empty = document.createElement("div");
                    empty.className = "combo-empty";
                    empty.textContent = "Không tìm thấy";
                    list.appendChild(empty);
                    return;
                }

                items.forEach((option) => {
                    const item = document.createElement("button");
                    item.type = "button";
                    item.className = "combo-option" + (option.value === select.value ? " is-selected" : "");
                    item.textContent = option.label;
                    item.setAttribute("role", "option");
                    item.setAttribute("aria-selected", option.value === select.value ? "true" : "false");
                    item.addEventListener("click", () => choose(option));
                    list.appendChild(item);
                });

                const selected = list.querySelector(".combo-option.is-selected");
                if (selected) selected.scrollIntoView({ block: "nearest" });
            };

            const open = () => {
                if (select.disabled) return;
                panel.hidden = false;
                trigger.setAttribute("aria-expanded", "true");
                wrap.classList.add("is-open");
                search.value = "";
                renderList();
                search.focus();
            };

            const close = () => {
                panel.hidden = true;
                trigger.setAttribute("aria-expanded", "false");
                wrap.classList.remove("is-open");
            };

            trigger.addEventListener("click", () => (panel.hidden ? open() : close()));

            if (label) {
                label.addEventListener("click", (event) => {
                    event.preventDefault();
                    open();
                });
            }

            search.addEventListener("input", renderList);

            search.addEventListener("keydown", (event) => {
                const items = $$(".combo-option", list);

                if (event.key === "Escape") {
                    close();
                    trigger.focus();
                    return;
                }

                if (event.key === "ArrowDown" || event.key === "ArrowUp") {
                    event.preventDefault();
                    if (!items.length) return;
                    activeIndex = event.key === "ArrowDown"
                        ? Math.min(activeIndex + 1, items.length - 1)
                        : Math.max(activeIndex - 1, 0);
                    items.forEach((item, index) => item.classList.toggle("is-active", index === activeIndex));
                    items[activeIndex].scrollIntoView({ block: "nearest" });
                    return;
                }

                if (event.key === "Enter" && items[activeIndex]) {
                    event.preventDefault();
                    items[activeIndex].click();
                }
            });

            document.addEventListener("click", (event) => {
                if (!wrap.contains(event.target)) close();
            });

            select.addEventListener("change", syncTrigger);

            syncTrigger();
        });
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
