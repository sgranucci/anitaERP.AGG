{{-- Diálogo operativo IA (Fase C): cuadro de diálogo + atajos tipados --}}
<div id="anita-ai-consulta" class="anita-ai-consulta" aria-hidden="true">
    <button type="button" class="anita-ai-consulta__fab" id="anita-ai-consulta-fab" title="Asistente operativo IA">
        <i class="fa fa-magic"></i>
        <span class="anita-ai-consulta__fab-label">IA</span>
    </button>
    <div class="anita-ai-consulta__drawer" id="anita-ai-consulta-drawer" role="dialog" aria-labelledby="anita-ai-consulta-titulo">
        <div class="anita-ai-consulta__header">
            <h3 class="anita-ai-consulta__titulo" id="anita-ai-consulta-titulo">
                <i class="fa fa-comments"></i> Asistente operativo
            </h3>
            <button type="button" class="anita-ai-consulta__cerrar" id="anita-ai-consulta-cerrar" title="Cerrar">&times;</button>
        </div>
        <div class="anita-ai-consulta__body">
            <div class="anita-ai-consulta__chat" id="anita-ai-consulta-chat" aria-live="polite">
                <div class="anita-ai-consulta__msg anita-ai-consulta__msg--bot">
                    Escriba en lenguaje natural (saldo, mayor, OC, proveedor…). Interpreto la frase y consulto el ERP (solo lectura; no invento importes).
                    <div class="anita-ai-consulta__ejemplos small mt-1" id="anita-ai-consulta-ejemplos"></div>
                </div>
            </div>
            <div class="anita-ai-consulta__atajos">
                <div class="small text-muted mb-1">Atajos</div>
                <div class="anita-ai-consulta__chips" id="anita-ai-consulta-chips"></div>
            </div>
            <div class="anita-ai-consulta__composer">
                <label class="sr-only" for="anita-ai-consulta-pregunta">Consulta</label>
                <textarea
                    class="form-control form-control-sm"
                    id="anita-ai-consulta-pregunta"
                    rows="3"
                    placeholder="Ej.: saldo del artículo ABC-100 / quién debe firmar la OC 1234"
                ></textarea>
                <div class="anita-ai-consulta__composer-actions">
                    <button type="button" class="btn btn-sm btn-primary" id="anita-ai-consulta-enviar">
                        Enviar
                    </button>
                </div>
            </div>
            <div class="anita-ai-consulta__error alert alert-warning py-2 mt-2 d-none" id="anita-ai-consulta-error"></div>
        </div>
    </div>
</div>
