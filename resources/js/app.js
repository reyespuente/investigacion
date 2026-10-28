const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const banner = document.querySelector("#cookie-banner");
document.querySelectorAll("[data-cookie-open]").forEach((b) =>
    b.addEventListener("click", () => {
        banner.hidden = false;
        banner.querySelector("button")?.focus();
    }),
);
document.querySelectorAll("[data-cookie-choice]").forEach((b) =>
    b.addEventListener("click", async () => {
        try {
            const r = await fetch("/cookies", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                    "X-CSRF-TOKEN": csrf,
                },
                body: JSON.stringify({ choice: b.dataset.cookieChoice }),
            });
            if (!r.ok) throw Error();
            banner.hidden = true;
        } catch {
            document.querySelector("[data-cookie-error]").hidden = false;
        }
    }),
);
let timer, controller;
const live = document.querySelector("[data-live-search]"),
    home = document.querySelector("[data-home-search]");
async function search(form, isHome = false) {
    controller?.abort();
    const abort = new AbortController();
    controller = abort;
    const params = new URLSearchParams(new FormData(form));
    for (const [k, v] of [...params]) if (!v) params.delete(k);
    const target = document.querySelector(
        isHome ? "#home-live-results" : "#results",
    );
    if (isHome) {
        form.querySelector(".text-link").href = "/publicaciones?" + params;
        if (
            ![...params].some(
                ([k, v]) => v && (k !== "scope" || params.get("q")),
            )
        ) {
            target.hidden = true;
            document.querySelector("#home-default").hidden = false;
            return;
        }
        target.hidden = false;
        document.querySelector("#home-default").hidden = true;
    }
    target.classList.add("loading");
    target.setAttribute("aria-busy", "true");
    try {
        const r = await fetch("/publicaciones?" + params, {
            signal: abort.signal,
            headers: { Accept: "application/json" },
        });
        if (!r.ok) throw Error();
        const data = await r.json();
        target.innerHTML = data.html;
        if (!isHome) history.replaceState(null, "", "/publicaciones?" + params);
        document.querySelector("#search-error")?.setAttribute("hidden", "");
    } catch (e) {
        if (e.name !== "AbortError") {
            if (isHome)
                target.textContent =
                    "No pudimos cargar los resultados. Intenta nuevamente.";
            else document.querySelector("#search-error").hidden = false;
        }
    } finally {
        if (!abort.signal.aborted) {
            target.classList.remove("loading");
            target.removeAttribute("aria-busy");
        }
    }
}
for (const [form, isHome] of [
    [live, false],
    [home, true],
])
    if (form) {
        form.addEventListener("input", (e) => {
            clearTimeout(timer);
            timer = setTimeout(
                () => search(form, isHome),
                e.target.type === "search" ? 280 : 0,
            );
        });
        form.addEventListener("change", () => {
            clearTimeout(timer);
            search(form, isHome);
        });
        form.addEventListener("submit", (e) => {
            e.preventDefault();
            clearTimeout(timer);
            search(form, isHome);
        });
    }
document
    .querySelector(".directory-search input")
    ?.addEventListener("input", () => {
        clearTimeout(timer);
        timer = setTimeout(
            () => document.querySelector(".directory-search").requestSubmit(),
            450,
        );
    });
const dialog = document.querySelector("#export-dialog");
document.querySelectorAll("[data-export-open]").forEach((b) =>
    b.addEventListener("click", () => {
        const area = dialog.querySelector("[data-export-filters]");
        if (area) {
            area.replaceChildren();
            for (const [k, v] of new URLSearchParams(location.search)) {
                if (
                    ![
                        "q",
                        "year",
                        "type",
                        "abstract",
                        "researcher",
                        "sort",
                        "scope",
                        "venue",
                        "doi",
                    ].includes(k)
                )
                    continue;
                const input = document.createElement("input");
                input.type = "hidden";
                input.name = k;
                input.value = v;
                area.append(input);
            }
        }
        dialog.showModal();
    }),
);
document
    .querySelector("[data-close-dialog]")
    ?.addEventListener("click", () => dialog.close());
document
    .querySelectorAll("[data-export-form],[data-download-form]")
    .forEach((form) =>
        form.addEventListener("submit", async (e) => {
            e.preventDefault();
            const button = form.querySelector(
                'button[type="submit"],button:not([type])',
            );
            button.disabled = true;
            let message = form.querySelector("[data-download-status]");
            if (!message) {
                message = document.createElement("p");
                message.dataset.downloadStatus = "";
                message.setAttribute("role", "status");
                form.append(message);
            }
            message.textContent = "Preparando archivo…";
            try {
                const r = await fetch(form.action, {
                    method: "POST",
                    body: new FormData(form),
                    headers: {
                        "X-CSRF-TOKEN": csrf,
                        Accept: "application/json",
                    },
                });
                if (!r.ok) {
                    const d = await r.json().catch(() => ({}));
                    throw Error(
                        d.message ||
                            "No se pudo generar el archivo. Intenta nuevamente.",
                    );
                }
                const blob = await r.blob(),
                    url = URL.createObjectURL(blob),
                    a = document.createElement("a");
                a.href = url;
                a.download =
                    /filename="?([^";]+)/i.exec(
                        r.headers.get("content-disposition") || "",
                    )?.[1] || "exportacion";
                a.click();
                setTimeout(() => URL.revokeObjectURL(url), 1000);
                message.textContent = "Archivo preparado.";
            } catch (error) {
                message.textContent = error.message;
            } finally {
                button.disabled = false;
                if (window.turnstile)
                    for (const widget of form.querySelectorAll(".cf-turnstile"))
                        try {
                            window.turnstile.reset(widget);
                        } catch {}
            }
        }),
    );
const data = document.querySelector("#citation-data");
if (data) {
    (async () => {
        const { Cite, plugins } = await import("@citation-js/core");
        await import("@citation-js/plugin-csl");
        await import("@citation-js/plugin-bibtex");
        await import("@citation-js/plugin-ris");
        for (const key of ["apa", "chicago", "ieee"]) {
            const r = await fetch("/citation-styles/" + key + ".csl");
            if (r.ok)
                plugins.config.get("@csl").styles.add(key, await r.text());
        }
        const locale = await fetch("/citation-styles/locales-es-ES.xml");
        if (locale.ok)
            plugins.config
                .get("@csl")
                .locales.add("es-ES", await locale.text());
        const cite = new Cite(JSON.parse(data.textContent)),
            select = document.querySelector("#citation-style"),
            box = document.querySelector("#citation-text");
        function render() {
            try {
                box.textContent =
                    select.value === "bibtex"
                        ? cite.format("bibtex")
                        : select.value === "ris"
                          ? cite.format("ris")
                          : cite.format("bibliography", {
                                format: "text",
                                template: select.value,
                                lang: "es-ES",
                            });
                document.querySelector("#citation-status").textContent = "";
            } catch {
                document.querySelector("#citation-status").textContent =
                    "No se pudo aplicar el estilo. Se conserva la referencia anterior.";
            }
        }
        select.addEventListener("change", render);
        render();
    })().catch((error) => {
        console.error("Citation initialization:", error.message);
        document.querySelector("#citation-status").textContent =
            "La referencia original sigue disponible.";
    });
    document
        .querySelector("[data-copy-citation]")
        .addEventListener("click", async () => {
            try {
                await navigator.clipboard.writeText(
                    document.querySelector("#citation-text").textContent,
                );
                document.querySelector("#citation-status").textContent =
                    "Cita copiada";
            } catch {
                document.querySelector("#citation-status").textContent =
                    "Selecciona el texto para copiarlo.";
            }
        });
}
const viewer = document.querySelector("[data-pdf-src]");
if (viewer)
    (async () => {
        const pdfjs = await import("pdfjs-dist"),
            worker = await import("pdfjs-dist/build/pdf.worker.min.mjs?url");
        pdfjs.GlobalWorkerOptions.workerSrc = worker.default;
        const pdf = await pdfjs.getDocument({
            url: viewer.dataset.pdfSrc,
            isEvalSupported: false,
            disableAutoFetch: true,
        }).promise;
        let current = 1,
            busy = false;
        async function render() {
            if (busy) return;
            busy = true;
            try {
                const page = await pdf.getPage(current),
                    base = page.getViewport({ scale: 1 }),
                    ratio = Math.min(devicePixelRatio || 1, 2),
                    scale =
                        Math.min(1.7, (viewer.clientWidth - 40) / base.width) *
                        ratio,
                    vp = page.getViewport({ scale }),
                    canvas = document.querySelector("#pdf-canvas");
                canvas.width = vp.width;
                canvas.height = vp.height;
                canvas.style.width = `${vp.width / ratio}px`;
                await page.render({
                    canvasContext: canvas.getContext("2d"),
                    viewport: vp,
                }).promise;
                document.querySelector("#pdf-status").textContent =
                    `Página ${current} de ${pdf.numPages}`;
                document.querySelector("[data-pdf-prev]").disabled =
                    current === 1;
                document.querySelector("[data-pdf-next]").disabled =
                    current === pdf.numPages;
            } finally {
                busy = false;
            }
        }
        document
            .querySelector("[data-pdf-prev]")
            .addEventListener("click", () => {
                if (!busy && current > 1) {
                    current--;
                    render();
                }
            });
        document
            .querySelector("[data-pdf-next]")
            .addEventListener("click", () => {
                if (!busy && current < pdf.numPages) {
                    current++;
                    render();
                }
            });
        await render();
    })().catch(() => {
        document.querySelector("#pdf-status").textContent =
            "No se pudo mostrar el PDF. Puedes abrir el archivo original.";
    });

const settingsForm = document.querySelector("[data-settings-form]");
const batchForm = document.querySelector("#batch-review");
if (batchForm) {
    function selectionCount() {
        const count = document.querySelectorAll(
            "[data-batch-item]:checked",
        ).length;
        document.querySelector("[data-selection-count]").textContent = count;
        document.querySelector("[data-batch-submit]").disabled = count === 0;
        batchForm.classList.toggle("has-selection", count > 0);
    }
    document
        .querySelectorAll("[data-batch-item]")
        .forEach((input) => input.addEventListener("change", selectionCount));
    selectionCount();
}
if (settingsForm) {
    const panels = [...settingsForm.querySelectorAll("[data-settings-panel]")];
    const tabs = [...document.querySelectorAll("[data-settings-tab]")];
    function selectSettings(key) {
        if (!panels.some((panel) => panel.dataset.settingsPanel === key))
            key = "access";
        panels.forEach(
            (panel) => (panel.hidden = panel.dataset.settingsPanel !== key),
        );
        tabs.forEach((tab) => {
            tab.classList.toggle("active", tab.dataset.settingsTab === key);
            if (tab.dataset.settingsTab === key)
                tab.setAttribute("aria-current", "true");
            else tab.removeAttribute("aria-current");
        });
    }
    tabs.forEach((tab) =>
        tab.addEventListener("click", (event) => {
            event.preventDefault();
            selectSettings(tab.dataset.settingsTab);
            history.replaceState(
                null,
                "",
                "#settings-" + tab.dataset.settingsTab,
            );
        }),
    );
    settingsForm.addEventListener(
        "invalid",
        (event) => {
            const panel = event.target.closest("[data-settings-panel]");
            if (panel) selectSettings(panel.dataset.settingsPanel);
        },
        true,
    );
    selectSettings(location.hash.replace("#settings-", ""));
}
document.querySelectorAll("[data-review-decision]").forEach((select) => {
    const form = select.form;
    function updateDecision() {
        const publish = select.value === "published";
        document
            .querySelectorAll("[data-review-primary]")
            .forEach(
                (button) =>
                    (button.firstChild.textContent = publish
                        ? "Publicar y siguiente "
                        : "Guardar y siguiente "),
            );
        form.querySelector('[name="reason"]').required = !publish;
        form.querySelectorAll(
            '[name="title"],[name="year"],[name="venue"],[name="external_url"]',
        ).forEach((input) => (input.required = publish));
    }
    select.addEventListener("change", updateDecision);
    updateDecision();
});
document.querySelectorAll(".review-form").forEach((form) =>
    form.addEventListener("submit", () => {
        form.setAttribute("aria-busy", "true");
        // Keep the submitter enabled until its name/value is sent by the browser.
        requestAnimationFrame(() =>
            form
                .querySelectorAll('button[type="submit"]')
                .forEach((button) => (button.disabled = true)),
        );
    }),
);
document.querySelectorAll(".review-form").forEach((form) =>
    form.addEventListener(
        "invalid",
        (event) => {
            const details = event.target.closest("details");
            if (details) details.open = true;
        },
        true,
    ),
);
const statisticsForm = document.querySelector("[data-statistics-form]");
if (statisticsForm) {
    let request;
    async function refreshStatistics() {
        request?.abort();
        const abort = new AbortController();
        request = abort;
        const params = new URLSearchParams(new FormData(statisticsForm));
        for (const [key, value] of [...params]) if (!value) params.delete(key);
        const panel = document.querySelector("#statistics-results");
        panel.setAttribute("aria-busy", "true");
        try {
            const response = await fetch("/estadisticas?" + params, {
                headers: { Accept: "application/json" },
                signal: abort.signal,
            });
            if (!response.ok) throw Error();
            const data = await response.json();
            panel.innerHTML = data.html;
            history.replaceState(null, "", "/estadisticas?" + params);
            document.querySelector("[data-statistics-error]").hidden = true;
        } catch (error) {
            if (error.name !== "AbortError")
                document.querySelector("[data-statistics-error]").hidden =
                    false;
        } finally {
            if (!abort.signal.aborted) panel.removeAttribute("aria-busy");
        }
    }
    let statisticsTimer;
    statisticsForm.addEventListener("submit", (event) => {
        event.preventDefault();
        refreshStatistics();
    });
    statisticsForm.addEventListener("change", refreshStatistics);
    statisticsForm.addEventListener("input", (event) => {
        if (event.target.type === "search") {
            clearTimeout(statisticsTimer);
            statisticsTimer = setTimeout(refreshStatistics, 300);
        }
    });
    document
        .querySelector("#statistics-results")
        .addEventListener("click", (event) => {
            const control = event.target.closest("[data-stat-filter]");
            if (!control) return;
            const input = statisticsForm.elements.namedItem(
                control.dataset.statFilter,
            );
            input.value =
                input.value === control.dataset.statValue
                    ? ""
                    : control.dataset.statValue;
            refreshStatistics();
        });
}

const adminMenu = document.querySelector(".admin-menu");
if (adminMenu) {
    const size = matchMedia("(max-width:700px)");
    const syncMenu = () => (adminMenu.open = !size.matches);
    syncMenu();
    size.addEventListener("change", syncMenu);
}

const selectPage = document.querySelector("[data-batch-all]");
if (selectPage) {
    const inputs = [...document.querySelectorAll("[data-batch-item]")];
    selectPage.disabled = inputs.length === 0;
    selectPage.addEventListener("click", () => {
        const checked = !inputs.every((input) => input.checked);
        inputs.forEach((input) => {
            input.checked = checked;
            input.dispatchEvent(new Event("change"));
        });
        selectPage.textContent = checked
            ? "Quitar selección"
            : "Seleccionar aptas de esta página";
    });
}
