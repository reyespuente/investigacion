document.querySelectorAll("[data-degrees-editor]").forEach((editor) => {
    const list = editor.querySelector("[data-degrees-list]");
    const status = editor.querySelector("[data-degrees-status]");
    function renumber() {
        list.querySelectorAll("[data-degree-row]").forEach((row, index) => {
            row.querySelectorAll("[data-degree-field]").forEach((input) => {
                input.name = `degrees[${index}][${input.dataset.degreeField}]`;
            });
        });
    }
    editor.querySelector("[data-add-degree]").addEventListener("click", () => {
        if (list.querySelectorAll("[data-degree-row]").length >= 20) {
            status.textContent = "Puedes registrar hasta 20 grados académicos.";
            return;
        }
        list.append(editor.querySelector("[data-degree-template]").content.cloneNode(true));
        renumber();
        list.lastElementChild.querySelector("select").focus();
        status.textContent = "Grado agregado. Completa sus datos.";
    });
    list.addEventListener("click", (event) => {
        const button = event.target.closest("[data-remove-degree]");
        if (!button) return;
        button.closest("[data-degree-row]").remove();
        renumber();
        status.textContent = "Grado retirado. Guarda el perfil para aplicar el cambio.";
    });
    renumber();
});
const prodep = document.querySelector("[data-prodep-toggle]");
if (prodep) {
    const dates = document.querySelector("[data-prodep-dates]");
    const update = () => {
        dates.hidden = !prodep.checked;
        dates.querySelectorAll("input").forEach((input) => {
            input.disabled = !prodep.checked;
            input.required = prodep.checked;
        });
    };
    prodep.addEventListener("change", update);
    update();
}
