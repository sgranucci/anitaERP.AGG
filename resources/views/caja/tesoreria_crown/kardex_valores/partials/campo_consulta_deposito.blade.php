{{-- Filtro depósito kardex: id oculto + código + nombre + F1/lupa. Vacío = todos. --}}
@php
    $depositoId = $depositoId ?? '';
    $codigo = $codigo ?? '';
    $nombre = $nombre ?? '';
@endphp
<div class="tm-kardex-deposito-campo">
    <label class="small mb-0">Dep&oacute;sito</label>
    <div class="d-flex flex-nowrap align-items-center" style="gap:4px;">
        <input type="hidden" name="deposito_id" id="kardex_deposito_id" value="{{ $depositoId }}">
        <button type="button" class="btn-accion-tabla tooltipsC consultakardeposito flex-shrink-0" title="Consultar dep&oacute;sitos (F1)">
            <i class="fa fa-search text-primary"></i>
        </button>
        <input type="text" name="deposito_codigo" id="kardex_deposito_codigo" class="form-control form-control-sm codigokardeposito"
               value="{{ $codigo }}" placeholder="C&oacute;d." autocomplete="off"
               title="Vac&iacute;o = todos; Enter avanza; F1 o lupa consulta" style="width:5rem;flex-shrink:0;"
               data-resuelto="{{ $depositoId !== '' && $depositoId !== null && (int) $depositoId > 0 ? $codigo : '' }}">
        <input type="text" name="deposito_nombre" id="kardex_deposito_nombre" class="form-control form-control-sm nombrekardeposito text-truncate"
               value="{{ $nombre }}" placeholder="Todos" readonly style="min-width:0;flex:1 1 auto;">
    </div>
</div>
