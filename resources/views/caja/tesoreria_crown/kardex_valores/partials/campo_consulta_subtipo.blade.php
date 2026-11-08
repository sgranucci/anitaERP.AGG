{{-- Filtro subtipo kardex: id oculto + código + nombre + F1/lupa. Vacío = todos. --}}
@php
    $subtipoId = $subtipoId ?? '';
    $codigo = $codigo ?? '';
    $nombre = $nombre ?? '';
@endphp
<div class="tm-kardex-subtipo-campo">
    <label class="small mb-0">Subtipo</label>
    <div class="d-flex flex-nowrap align-items-center" style="gap:4px;">
        <input type="hidden" name="subtipo_id" id="kardex_subtipo_id" value="{{ $subtipoId }}">
        <button type="button" class="btn-accion-tabla tooltipsC consultakarsubtipo flex-shrink-0" title="Consultar subtipos (F1)">
            <i class="fa fa-search text-primary"></i>
        </button>
        <input type="text" name="subtipo_codigo" id="kardex_subtipo_codigo" class="form-control form-control-sm codigokarsubtipo"
               value="{{ $codigo }}" placeholder="C&oacute;d." autocomplete="off"
               title="Vac&iacute;o = todos; conviene elegir tipo primero" style="width:5rem;flex-shrink:0;"
               data-resuelto="{{ $subtipoId !== '' && $subtipoId !== null && (int) $subtipoId > 0 ? $codigo : '' }}">
        <input type="text" name="subtipo_nombre" id="kardex_subtipo_nombre" class="form-control form-control-sm nombrekarsubtipo text-truncate"
               value="{{ $nombre }}" placeholder="Todos" readonly style="min-width:0;flex:1 1 auto;">
    </div>
</div>
