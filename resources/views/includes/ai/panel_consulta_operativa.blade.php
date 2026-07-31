{{-- Copiloto IA: panel amplio, resultado primero, herramientas agrupadas --}}
<div id="anita-ai-consulta" class="anita-ai-consulta" aria-hidden="true">
    <button type="button" class="anita-ai-consulta__fab" id="anita-ai-consulta-fab" title="Copiloto operativo IA">
        <i class="fa fa-magic" aria-hidden="true"></i>
        <span class="anita-ai-consulta__fab-label">IA</span>
    </button>

    <div class="anita-ai-consulta__drawer" id="anita-ai-consulta-drawer" role="dialog" aria-labelledby="anita-ai-consulta-titulo" aria-modal="true">
        <header class="anita-ai-consulta__header">
            <div class="anita-ai-consulta__header-main">
                <h3 class="anita-ai-consulta__titulo" id="anita-ai-consulta-titulo">
                    <span class="anita-ai-consulta__titulo-icon" aria-hidden="true"><i class="fa fa-comments"></i></span>
                    Copiloto ERP
                </h3>
                <p class="anita-ai-consulta__subtitulo" id="anita-ai-consulta-subtitulo">Solo lectura · datos del ERP</p>
            </div>
            <div class="anita-ai-consulta__header-actions">
                <button type="button"
                    class="anita-ai-consulta__btn-action anita-ai-consulta__btn-excel-header d-none"
                    id="anita-ai-consulta-excel"
                    title="Exportar última consulta a Excel">
                    <i class="fa fa-file-excel-o" aria-hidden="true"></i>
                    <span>Excel</span>
                </button>
                <button type="button"
                    class="anita-ai-consulta__btn-icon"
                    id="anita-ai-consulta-expandir"
                    title="Ampliar / reducir panel"
                    aria-pressed="false">
                    <i class="fa fa-expand" aria-hidden="true"></i>
                </button>
                <button type="button" class="anita-ai-consulta__btn-icon anita-ai-consulta__cerrar" id="anita-ai-consulta-cerrar" title="Cerrar">
                    <i class="fa fa-times" aria-hidden="true"></i>
                </button>
            </div>
        </header>

        <div class="anita-ai-consulta__body">
            <div class="anita-ai-consulta__chat" id="anita-ai-consulta-chat" aria-live="polite">
                <div class="anita-ai-consulta__msg anita-ai-consulta__msg--bot anita-ai-consulta__msg--welcome">
                    <p class="mb-1">Pregunte en lenguaje natural o abra <strong>Herramientas</strong> para KPIs y consultas tipadas.</p>
                    <p class="anita-ai-consulta__hint mb-0">Ej.: «OC pendientes de firma», «mayor cuenta 214010013 este mes», «saldo proveedor 475».</p>
                    <div class="anita-ai-consulta__ejemplos" id="anita-ai-consulta-ejemplos"></div>
                </div>
            </div>

            {{-- Panel de herramientas (overlay interno; no ocupa el chat hasta abrirlo) --}}
            <div class="anita-ai-consulta__tools" id="anita-ai-consulta-tools" hidden>
                <div class="anita-ai-consulta__tools-head">
                    <span class="anita-ai-consulta__tools-title">Herramientas</span>
                    <button type="button" class="anita-ai-consulta__btn-icon" id="anita-ai-consulta-tools-cerrar" title="Volver al chat">
                        <i class="fa fa-arrow-left" aria-hidden="true"></i>
                    </button>
                </div>
                <div class="anita-ai-consulta__tools-tabs" id="anita-ai-consulta-tools-tabs" role="tablist"></div>
                <div class="anita-ai-consulta__tools-grid" id="anita-ai-consulta-tools-grid"></div>
                <p class="anita-ai-consulta__tools-hint">
                    Los KPIs se ejecutan al instante. El resto pide un código en el cuadro de abajo.
                </p>
            </div>
        </div>

        <footer class="anita-ai-consulta__footer">
            <div class="anita-ai-consulta__intent-pill d-none" id="anita-ai-consulta-intent-pill">
                <span id="anita-ai-consulta-intent-label"></span>
                <button type="button" class="anita-ai-consulta__intent-clear" id="anita-ai-consulta-intent-clear" title="Quitar atajo">&times;</button>
            </div>
            <div class="anita-ai-consulta__composer">
                <button type="button"
                    class="anita-ai-consulta__btn-tools"
                    id="anita-ai-consulta-tools-toggle"
                    title="Abrir herramientas">
                    <i class="fa fa-th-large" aria-hidden="true"></i>
                    <span>Herramientas</span>
                </button>
                <label class="sr-only" for="anita-ai-consulta-pregunta">Consulta</label>
                <textarea
                    class="anita-ai-consulta__input"
                    id="anita-ai-consulta-pregunta"
                    rows="1"
                    placeholder="Escriba su consulta…"
                ></textarea>
                <button type="button" class="anita-ai-consulta__btn-send" id="anita-ai-consulta-enviar" title="Enviar">
                    <i class="fa fa-paper-plane" aria-hidden="true"></i>
                </button>
            </div>
            <div class="anita-ai-consulta__error d-none" id="anita-ai-consulta-error" role="alert"></div>
        </footer>
    </div>
</div>
