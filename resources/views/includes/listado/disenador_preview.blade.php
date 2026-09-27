{{-- Panel preview diseñador (packs A wireframe + B muestra). Montar a la derecha del modal. --}}
@php
    $previewUrl = $previewUrl ?? '';
    $previewCsrf = csrf_token();
@endphp
<aside class="lw-disenador-preview" id="lw-disenador-preview"
       data-preview-url="{{ $previewUrl }}"
       data-csrf="{{ $previewCsrf }}">
    <div class="lw-disenador-preview-head">
        <div>
            <strong><i class="fa fa-eye"></i> Vista previa</strong>
            <div class="lw-disenador-preview-sub">Wireframe + muestra (hasta 20 filas)</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary" id="btn-lw-preview-refresh" title="Actualizar muestra">
            <i class="fa fa-refresh"></i>
        </button>
    </div>

    <div class="lw-disenador-chips" id="lw-disenador-chips">
        <span class="lw-disenador-chip lw-disenador-chip--muted">Sin cortes activos</span>
    </div>

    <div class="lw-disenador-wire" id="lw-disenador-wire" aria-label="Wireframe de columnas">
        <div class="lw-disenador-wire-empty text-muted small">Marcá columnas visibles para ver el layout…</div>
    </div>

    <div class="lw-disenador-sample-meta" id="lw-disenador-sample-meta">
        <span class="text-muted small">Muestra pendiente</span>
    </div>
    <div class="lw-disenador-sample-wrap">
        <table class="table table-sm table-bordered mb-0 lw-disenador-sample" id="lw-disenador-sample">
            <thead id="lw-disenador-sample-thead" style="background:#85C1E9;color:#17202A;"></thead>
            <tbody id="lw-disenador-sample-tbody">
                <tr><td class="text-muted small text-center py-3">Usá «Actualizar» o editá columnas para cargar datos.</td></tr>
            </tbody>
        </table>
    </div>
</aside>
