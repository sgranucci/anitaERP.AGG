{{-- Filtro tipo movimiento kardex: id oculto + código + nombre + F1/lupa. Vacío = todos. --}}
@php
    $tipoId = $tipoId ?? '';
    $codigo = $codigo ?? '';
    $nombre = $nombre ?? '';
@endphp
<div class="tm-kardex-tipo-campo">
    <label class="small mb-0">Tipo</label>
    <div class="d-flex flex-nowrap align-items-center" style="gap:4px;">
        <input type="hidden" name="tipo_id" id="kardex_tipo_id" value="{{ $tipoId }}">
        <button type="button" class="btn-accion-tabla tooltipsC consultakartipo flex-shrink-0" title="Consultar tipos (F1)">
            <i class="fa fa-search text-primary"></i>
        </button>
        <input type="text" name="tipo_codigo" id="kardex_tipo_codigo" class="form-control form-control-sm codigokartipo"
               value="{{ $codigo }}" placeholder="C&oacute;d." autocomplete="off" inputmode="numeric"
               title="Vac&iacute;o = todos; Enter avanza; F1 o lupa consulta" style="width:5rem;flex-shrink:0;"
               data-resuelto="{{ $tipoId !== '' && $tipoId !== null && (int) $tipoId > 0 ? $codigo : '' }}">
        <input type="text" name="tipo_nombre" id="kardex_tipo_nombre" class="form-control form-control-sm nombrekartipo text-truncate"
               value="{{ $nombre }}" placeholder="Todos" readonly style="min-width:0;flex:1 1 auto;">
    </div>
</div>
