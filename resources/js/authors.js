document.querySelectorAll("[data-author-editor]").forEach((editor) => {
    const list = editor.querySelector("[data-author-list]");
    const search = editor.querySelector("[data-author-search]");
    const suggestions = editor.querySelector("[data-author-suggestions]");
    const status = editor.querySelector("[data-author-status]");
    const external = editor.querySelector("[data-external-authors]");
    const rows = () => [...list.querySelectorAll("[data-author-row]")];
    const nameKey = (name) => name
        .trim()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLocaleLowerCase();
    let timer;
    let request;

    function renumber() {
        rows().forEach((row, index, all) => {
            row.querySelector("[data-author-name]").name = `authors[${index}][name]`;
            row.querySelector("[data-author-profile]").name = `authors[${index}][researcher_id]`;
            row.querySelector("[data-author-affiliations]").name = `authors[${index}][affiliations]`;
            row.querySelector("[data-author-up]").disabled = index === 0;
            row.querySelector("[data-author-down]").disabled = index === all.length - 1;
        });
    }

    function addAuthor(author) {
        const name = author.name.trim();
        const duplicate = rows().some((row) => author.id
            ? row.querySelector("[data-author-profile]").value === String(author.id)
            : nameKey(row.querySelector("[data-author-name]").value) === nameKey(name));
        if (duplicate) {
            status.textContent = "Este autor ya está en la lista.";
            return false;
        }
        if (!name || name.length > 500 || rows().length >= 1000) {
            status.textContent = "Revisa los nombres. Puedes agregar hasta 1000 autores y 500 caracteres por nombre.";
            return false;
        }
        const fragment = editor.querySelector("[data-author-template]").content.cloneNode(true);
        const row = fragment.querySelector("[data-author-row]");
        const nameInput = row.querySelector("[data-author-name]");
        nameInput.value = name;
        nameInput.readOnly = Boolean(author.id);
        row.querySelector("[data-author-profile]").value = author.id || "";
        if (author.id) {
            const badge = document.createElement("span");
            badge.className = "badge";
            badge.textContent = "Perfil seleccionado";
            row.querySelector(".field > span").append(" ", badge);
        }
        list.append(fragment);
        renumber();
        status.textContent = `${name} agregado a la lista.`;
        return true;
    }

    editor.querySelector("[data-add-external]").addEventListener("click", () => {
        const pending = external.value.split(/[,\r\n]+/).map((name) => name.trim()).filter(Boolean);
        const failed = [];
        pending.forEach((name) => {
            if (!addAuthor({ name })) failed.push(name);
        });
        external.value = failed.join(", ");
    });

    list.addEventListener("click", (event) => {
        const button = event.target.closest("button");
        if (!button) return;
        const row = button.closest("[data-author-row]");
        if (button.hasAttribute("data-author-remove") && row.dataset.self !== "1") {
            const name = row.querySelector("[data-author-name]").value;
            row.remove();
            status.textContent = `${name} retirado de la lista.`;
        }
        if (button.hasAttribute("data-author-up") && row.previousElementSibling) {
            list.insertBefore(row, row.previousElementSibling);
        }
        if (button.hasAttribute("data-author-down") && row.nextElementSibling) {
            list.insertBefore(row.nextElementSibling, row);
        }
        renumber();
        if (button.isConnected && button.disabled) {
            row.querySelector("[data-author-name]").focus();
        }
    });

    search.addEventListener("input", () => {
        clearTimeout(timer);
        request?.abort();
        suggestions.replaceChildren();
        suggestions.hidden = true;
        const query = search.value.trim();
        status.textContent = "";
        if (query.length < 2) return;
        timer = setTimeout(async () => {
            const abort = new AbortController();
            request = abort;
            status.textContent = "Buscando investigadores…";
            try {
                const url = new URL(editor.dataset.url, location.href);
                url.searchParams.set("q", query);
                const response = await fetch(url, {
                    signal: abort.signal,
                    headers: { Accept: "application/json" },
                });
                if (!response.ok) throw Error();
                const authors = await response.json();
                if (abort.signal.aborted || search.value.trim() !== query) return;
                authors.forEach((author) => {
                    const item = document.createElement("li");
                    const button = document.createElement("button");
                    button.type = "button";
                    const name = document.createElement("strong");
                    name.textContent = author.name;
                    button.append(name);
                    if (author.department || author.orcid) {
                        const detail = document.createElement("small");
                        detail.textContent = [author.department, author.orcid].filter(Boolean).join(" · ");
                        button.append(detail);
                    }
                    button.addEventListener("click", () => {
                        addAuthor(author);
                        suggestions.replaceChildren();
                        suggestions.hidden = true;
                        search.value = "";
                        search.focus();
                    });
                    item.append(button);
                    suggestions.append(item);
                });
                suggestions.hidden = authors.length === 0;
                status.textContent = authors.length
                    ? "Selecciona el perfil correcto. Puedes recorrer las opciones con Tab."
                    : "Sin coincidencias. Puedes agregar el nombre como autor externo.";
            } catch (error) {
                if (error.name !== "AbortError") {
                    status.textContent = "No se pudieron cargar las recomendaciones. Intenta nuevamente.";
                }
            }
        }, 280);
    });
    search.addEventListener("keydown", (event) => {
        if (event.key === "Enter") {
            event.preventDefault();
            suggestions.querySelector("button")?.click();
        }
        if (event.key === "ArrowDown") {
            event.preventDefault();
            suggestions.querySelector("button")?.focus();
        }
        if (event.key === "Escape") {
            request?.abort();
            clearTimeout(timer);
            suggestions.hidden = true;
            status.textContent = "";
        }
    });
    renumber();
});
