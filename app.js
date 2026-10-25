"use strict";

const formatRupiahDigits = (rawValue) => {
    const [rawInteger, rawDecimal = ""] = rawValue.split(",");
    const integer = rawInteger.replace(/\D/g, "").replace(/^0+(?=\d)/, "") || "0";
    const decimal = rawDecimal.replace(/\D/g, "").slice(0, 2);
    const grouped = integer.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    return rawValue.includes(",") ? `${grouped},${decimal}` : grouped;
};

const formatRupiahRaw = (rawValue) => {
    const normalized = String(rawValue).trim().replace(",", ".");
    if (!/^\d+(?:\.\d{1,2})?$/.test(normalized)) {
        return "";
    }

    const [integer, decimal] = normalized.split(".");
    return formatRupiahDigits(decimal ? `${integer},${decimal}` : integer);
};

const prepareRupiahInputs = (form) => {
    const inputs = Array.from(form.querySelectorAll("[data-rupiah-input]"));
    inputs.forEach((input) => {
        input.value = formatRupiahDigits(input.value);
        input.addEventListener("input", () => {
            input.value = formatRupiahDigits(input.value);
        });
    });

    form.addEventListener("submit", () => {
        inputs.forEach((input) => {
            input.value = input.value.replace(/,$/, "").replace(/\./g, "").replace(",", ".");
        });
    });
};

const initializeWishlistInteractions = () => {
    document.querySelectorAll("form").forEach(prepareRupiahInputs);

    document.querySelectorAll("[data-live-filter]").forEach((form) => {
        const searchInput = form.querySelector("[data-filter-search]");
        const selects = Array.from(form.querySelectorAll("[data-filter-select]"));
        const tables = Array.from(document.querySelectorAll("[data-filter-table]"));
        const feedback = form.querySelector("[data-filter-feedback]");
        const applyFilters = () => {
            const search = (searchInput?.value || "").trim().toLocaleLowerCase("id");
            const filters = Object.fromEntries(
                selects.map((select) => [select.name, select.value])
            );
            let totalMatches = 0;

            tables.forEach((table) => {
                const rows = Array.from(table.querySelectorAll("[data-filter-row]"));
                let tableMatches = 0;

                rows.forEach((row) => {
                    const matchesSearch = row.textContent
                        .trim()
                        .toLocaleLowerCase("id")
                        .includes(search);
                    const matchesKind = !filters.jenis || row.dataset.kind === filters.jenis;
                    const matchesCategory = !filters.kategori || row.dataset.category === filters.kategori;
                    const matchesStatus = !filters.status || row.dataset.status === filters.status;
                    const visible = matchesSearch && matchesKind && matchesCategory && matchesStatus;

                    row.classList.toggle("d-none", !visible);
                    tableMatches += Number(visible);
                });

                totalMatches += tableMatches;
                const emptyRow = table.querySelector(".filter-no-results");
                emptyRow?.classList.toggle("d-none", tableMatches > 0 || rows.length === 0);

                const sectionCount = table.closest("section")?.querySelector(".section-count");
                if (sectionCount) {
                    sectionCount.textContent = `${tableMatches} barang`;
                }
            });

            if (feedback) {
                feedback.textContent = `${totalMatches} barang ditampilkan.`;
            }
        };

        searchInput?.addEventListener("input", applyFilters);
        selects.forEach((select) => select.addEventListener("change", applyFilters));
        applyFilters();
    });

    document.querySelectorAll("[data-purchase-form]").forEach((form) => {
        const itemSelect = form.querySelector("[data-purchase-item]");
        const nameInput = form.querySelector("[data-purchase-name]");
        const amountInput = form.querySelector("[data-purchase-amount]");

        if (!itemSelect || !nameInput || !amountInput) {
            return;
        }

        let suggestedName = "";
        let suggestedAmount = "";

        itemSelect.addEventListener("change", () => {
            const option = itemSelect.selectedOptions[0];
            if (!option?.value) {
                if (nameInput.value === suggestedName) {
                    nameInput.value = "";
                }
                if (amountInput.value === formatRupiahRaw(suggestedAmount)) {
                    amountInput.value = "";
                }
                suggestedName = "";
                suggestedAmount = "";
                nameInput.readOnly = false;
                return;
            }

            suggestedName = `Pembelian ${option.dataset.nama || option.textContent.trim()}`;
            suggestedAmount = option.dataset.harga || "";
            nameInput.value = suggestedName;
            amountInput.value = formatRupiahRaw(suggestedAmount);
            amountInput.dispatchEvent(new Event("input", { bubbles: true }));
            nameInput.readOnly = true;
        });

        if (itemSelect.value) {
            const option = itemSelect.selectedOptions[0];
            suggestedName = `Pembelian ${option.dataset.nama || option.textContent.trim()}`;
            suggestedAmount = option.dataset.harga || "";
            if (!nameInput.value) {
                nameInput.value = suggestedName;
            }
            if (!amountInput.value) {
                amountInput.value = formatRupiahRaw(suggestedAmount);
                amountInput.dispatchEvent(new Event("input", { bubbles: true }));
            }
            nameInput.readOnly = true;
        }
    });

    document.querySelectorAll("form[data-loading-form]").forEach((form) => {
        form.addEventListener("submit", (event) => {
            const submitter = event.submitter || form.querySelector('[type="submit"]');
            if (!submitter || submitter.disabled) {
                return;
            }

            submitter.disabled = true;
            submitter.setAttribute("aria-busy", "true");
            const spinner = document.createElement("span");
            spinner.className = "spinner-border spinner-border-sm me-2";
            spinner.setAttribute("aria-hidden", "true");
            const label = document.createElement("span");
            label.textContent = "Menyimpan...";
            submitter.replaceChildren(spinner, label);
        });
    });
};

if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", initializeWishlistInteractions, { once: true });
} else {
    initializeWishlistInteractions();
}
