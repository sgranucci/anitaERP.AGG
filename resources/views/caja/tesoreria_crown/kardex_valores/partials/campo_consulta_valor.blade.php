{{-- Filtro valor/denominación kardex: id oculto + código + nombre + F1/lupa. Vacío = todos. --}}
@php
    $valorId = $valorId ?? '';
    $codigo = $codigo ?? '';
    $nombre = $nombre ?? '';
@endphp
<div class="tm-kardex-valor-campo">
    <label class="small mb-0">Valor</label>
    <div class="d-flex flex-nowrap align-items-center" style="gap:4px;">
        <input type="hidden" name="valor_id" id="kardex_valor_id" value="{{ $valorId }}">
        <button type="button" class="btn-accion-tabla tooltipsC consultakarvalor flex-shrink-0" title="Consultar valores (F1)">
            <i class="fa fa-search text-primary"></i>
        </button>
        <input type="text" name="valor_codigo" id="kardex_valor_codigo" class="form-control form-control-sm codigokarvalor"
               value="{{ $codigo }}" placeholder="Denom." autocomplete="off" inputmode="numeric"
               title="Vac&iacute;o = todos; Enter avanza; F1 o lupa consulta" style="width:5.5rem;flex-shrink:0;"
               data-resuelto="{{ $valorId !== '' && $valorId !== null && (int) $valorId > 0 ? $codigo : '' }}">
        <input type="text" name="valor_nombre" id="kardex_valor_nombre" class="form-control form-control-sm nombrekarvalor text-truncate"
               value="{{ $nombre }}" placeholder="Todos" readonly style="min-width:0;flex:1 1 auto;">
    </div>
</div>
