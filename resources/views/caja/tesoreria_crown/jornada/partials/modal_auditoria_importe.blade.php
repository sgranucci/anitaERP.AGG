{{-- Modal compartido: explicación + auditoría de un importe (Res. cajeros / Anexo III). --}}
@php
    $modalId = $modalId ?? 'modal-tc-auditoria';
    $urlAuditoria = $urlAuditoria ?? '';
@endphp
<div class="modal fade" id="{{ $modalId }}" tabindex="-1" role="dialog" aria-labelledby="{{ $modalId }}-titulo"
     data-url="{{ $urlAuditoria }}">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content tc-audit-modal">
            <div class="modal-header tc-audit-modal-header py-3">
                <div class="d-flex align-items-start w-100 pr-3">
                    <div class="tc-audit-modal-icon mr-3" aria-hidden="true">
                        <i class="fa fa-search-plus"></i>
                    </div>
                    <div class="flex-grow-1 min-w-0">
                        <h5 class="modal-title mb-0" id="{{ $modalId }}-titulo">Auditoría del importe</h5>
                        <div class="small tc-audit-modal-sub" id="{{ $modalId }}-subtitulo"></div>
                    </div>
                    <div class="tc-audit-modal-total text-right ml-2" id="{{ $modalId }}-total" hidden>
                        <div class="small text-uppercase tc-audit-modal-total-label">Total</div>
                        <strong id="{{ $modalId }}-total-valor">0,00</strong>
                    </div>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="tc-audit-panel tc-audit-panel-formula mb-3" id="{{ $modalId }}-bloque-formula" hidden>
                    <div class="tc-audit-panel-title">
                        <i class="fa fa-calculator" aria-hidden="true"></i> Cómo se calcula
                    </div>
                    <p class="mb-0" id="{{ $modalId }}-formula"></p>
                </div>
                <div class="tc-audit-panel tc-audit-panel-pista mb-3" id="{{ $modalId }}-bloque-pista" hidden>
                    <div class="tc-audit-panel-title">
                        <i class="fa fa-compass" aria-hidden="true"></i> Cómo auditarlo
                    </div>
                    <p class="mb-0" id="{{ $modalId }}-pista"></p>
                </div>
                <div id="{{ $modalId }}-avisos"></div>
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="tc-audit-docs-titulo mb-0">
                        <i class="fa fa-folder-open-o" aria-hidden="true"></i> Documentos que lo arman
                    </div>
                    <span class="badge badge-light border" id="{{ $modalId }}-docs-count" hidden></span>
                </div>
                <p class="text-muted mb-2" id="{{ $modalId }}-cargando" hidden>
                    <i class="fa fa-spinner fa-spin" aria-hidden="true"></i> Cargando movimientos…
                </p>
                <p class="text-muted mb-0 tc-audit-vacio" id="{{ $modalId }}-vacio" hidden>Ningún movimiento aporta a este importe.</p>
                <div id="{{ $modalId }}-lista" class="tc-audit-lista"></div>
            </div>
            <div class="modal-footer py-2">
                <a href="#" class="btn btn-outline-info btn-sm" id="{{ $modalId }}-cierre" target="_blank" rel="noopener" hidden>
                    <i class="fa fa-external-link" aria-hidden="true"></i> Abrir cierre de caja
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
