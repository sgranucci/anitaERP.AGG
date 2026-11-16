{{-- Copiloto IA: panel amplio, resultado primero, herramientas agrupadas --}}
<div id="anita-ai-consulta" class="anita-ai-consulta" aria-hidden="true">
    <button type="button" class="anita-ai-consulta__fab" id="anita-ai-consulta-fab" title="Copiloto operativo IA">
        <i class="fa fa-magic" aria-hidden="true"></i>
        <span class="anita-ai-consulta__fab-label">IA</span>
        <span class="anita-ai-consulta__fab-badge d-none" id="anita-ai-consulta-fab-badge" aria-label="Avisos"></span>
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
                    id="anita-ai-consulta-prefs-toggle"
                    title="Preferencias del copiloto"
                    aria-pressed="false">
                    <i class="fa fa-cog" aria-hidden="true"></i>
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
                    <p class="mb-1">Pregunte para decidir: KPIs, deudas, mayor, stock o listados. Abra <strong>Herramientas</strong> o escriba en español.</p>
                    <p class="anita-ai-consulta__hint mb-0">Ej.: «armar brief del día», «cash position», «aging de proveedores», «cola HITL». Preferencias → Asistente por rol. Después: «y el mes pasado».</p>
                    <div class="anita-ai-consulta__aviso-inicio" id="anita-ai-consulta-aviso-inicio"></div>
                    <div class="anita-ai-consulta__historial" id="anita-ai-consulta-historial"></div>
                    <div class="anita-ai-consulta__ejemplos" id="anita-ai-consulta-ejemplos"></div>
                </div>
            </div>

            <div class="anita-ai-consulta__prefs" id="anita-ai-consulta-prefs" hidden>
                <div class="anita-ai-consulta__tools-head">
                    <span class="anita-ai-consulta__tools-title">Preferencias</span>
                    <button type="button" class="anita-ai-consulta__btn-icon" id="anita-ai-consulta-prefs-cerrar" title="Volver al chat">
                        <i class="fa fa-arrow-left" aria-hidden="true"></i>
                    </button>
                </div>
                <form id="anita-ai-consulta-prefs-form" class="anita-ai-consulta__prefs-form" autocomplete="off">
                    <div class="form-group mb-2">
                        <label class="small mb-0" for="anita-ai-pref-asistente-rol">Asistente (rol)</label>
                        <select class="form-control form-control-sm" id="anita-ai-pref-asistente-rol" name="asistente_rol">
                            <option value="auto">Automático (todos los módulos)</option>
                            <option value="compras">Compras / AP</option>
                            <option value="tesoreria">Tesorería / caja</option>
                            <option value="contable">Contable</option>
                            <option value="ventas">Ventas / AR</option>
                            <option value="stock">Stock / logística</option>
                            <option value="sueldos">Sueldos</option>
                            <option value="tickets">Tickets</option>
                        </select>
                        <small class="form-text text-muted">Filtra herramientas, ejemplos y el brief del día.</small>
                    </div>
                    <div class="form-group mb-2">
                        <label class="small mb-0" for="anita-ai-pref-empresa">Empresa ID (default)</label>
                        <input type="number" min="0" class="form-control form-control-sm" id="anita-ai-pref-empresa" name="empresa_id" placeholder="Vacío = sin filtro">
                    </div>
                    <div class="form-group mb-2">
                        <label class="small mb-0" for="anita-ai-pref-cc">Centro de costo (código)</label>
                        <input type="text" class="form-control form-control-sm" id="anita-ai-pref-cc" name="centrocosto_codigo" placeholder="Ej. 93">
                    </div>
                    <div class="form-group mb-2">
                        <label class="small mb-0" for="anita-ai-pref-dep">Depósito (código)</label>
                        <input type="text" class="form-control form-control-sm" id="anita-ai-pref-dep" name="deposito_codigo" placeholder="Ej. 12">
                    </div>
                    <div class="custom-control custom-checkbox mb-1">
                        <input type="checkbox" class="custom-control-input" id="anita-ai-pref-auto-brief" name="auto_brief" checked>
                        <label class="custom-control-label small" for="anita-ai-pref-auto-brief">Armar brief automáticamente al abrir (1×/día)</label>
                    </div>
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" class="custom-control-input" id="anita-ai-pref-notify" name="notify_brief_campanita" checked>
                        <label class="custom-control-label small" for="anita-ai-pref-notify">Campanita al sugerir / armar brief</label>
                    </div>
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" class="custom-control-input" id="anita-ai-pref-notify-mail" name="notify_brief_mail">
                        <label class="custom-control-label small" for="anita-ai-pref-notify-mail">Mail al armar / sugerir brief (opt-in)</label>
                    </div>
                    <hr class="my-2">
                    <div class="custom-control custom-checkbox mb-2">
                        <input type="checkbox" class="custom-control-input" id="anita-ai-pref-alerta-umbral" name="notify_alerta_umbral">
                        <label class="custom-control-label small" for="anita-ai-pref-alerta-umbral">Campanita si cruzo umbral (cheques / deuda AP)</label>
                    </div>
                    <div class="form-group mb-2">
                        <label class="small mb-0" for="anita-ai-pref-umbral-cheques">Umbral cheques vencidos ($)</label>
                        <input type="number" min="0" step="0.01" class="form-control form-control-sm" id="anita-ai-pref-umbral-cheques" name="umbral_cheques_vencidos" placeholder="Vacío / 0 = off">
                    </div>
                    <div class="form-group mb-2">
                        <label class="small mb-0" for="anita-ai-pref-umbral-deuda">Umbral deuda AP vencida ($)</label>
                        <input type="number" min="0" step="0.01" class="form-control form-control-sm" id="anita-ai-pref-umbral-deuda" name="umbral_deuda_ap" placeholder="Vacío / 0 = off">
                    </div>
                    <div class="form-group mb-2">
                        <label class="small mb-0" for="anita-ai-pref-umbral-tickets">Umbral tickets abiertos (cant.)</label>
                        <input type="number" min="0" step="1" class="form-control form-control-sm" id="anita-ai-pref-umbral-tickets" name="umbral_tickets_abiertos" placeholder="Vacío / 0 = off">
                    </div>
                    <div class="form-group mb-2">
                        <label class="small mb-0" for="anita-ai-pref-umbral-stock">Umbral insumos stock crítico (cant.)</label>
                        <input type="number" min="0" step="1" class="form-control form-control-sm" id="anita-ai-pref-umbral-stock" name="umbral_stock_critico" placeholder="Vacío / 0 = off">
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary" id="anita-ai-consulta-prefs-guardar">
                        <i class="fa fa-save"></i> Guardar
                    </button>
                    <span class="small text-muted ml-2" id="anita-ai-consulta-prefs-msg"></span>
                </form>
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
            <div class="anita-ai-consulta__intent-pill anita-ai-consulta__contexto-pill d-none" id="anita-ai-consulta-contexto-pill">
                <span>Chat con contexto</span>
                <button type="button" class="anita-ai-consulta__intent-clear" id="anita-ai-consulta-contexto-clear" title="Reiniciar contexto del chat">&times;</button>
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
