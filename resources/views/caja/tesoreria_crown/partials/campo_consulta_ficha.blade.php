{{-- Consulta ficha Crown: id oculto + código + descripción + F1/lupa. Vacío = sin elegir. --}}
@php
    $fichaId = $fichaId ?? '';
    $codigo = $codigo ?? '';
    $nombre = $nombre ?? '';
    $prefix = $prefix ?? 'ficha';
@endphp
<div class="tm-ficha-campo">
    <div class="input-group input-group-sm">
        <input type="hidden" name="{{ $inputName ?? 'ficha_id' }}" id="{{ $inputId ?? $prefix.'_id' }}" class="ficha_id" value="{{ $fichaId }}">
        <button type="button" class="btn-accion-tabla tooltipsC consultaficha flex-shrink-0" title="Consultar fichas (F1)">
            <i class="fa fa-search"></i>
        </button>
        <input type="text" name="{{ $codigoName ?? 'ficha_codigo' }}" id="{{ $prefix }}_codigo" class="form-control form-control-sm codigoficha"
               maxlength="12" inputmode="numeric" placeholder="Cód." title="Código de ficha. F1 consulta."
               value="{{ $codigo }}"
               data-resuelto="{{ $fichaId !== '' && $fichaId !== null && (int) $fichaId > 0 ? $codigo : '' }}">
        <input type="text" name="{{ $nombreName ?? 'ficha_nombre' }}" id="{{ $prefix }}_nombre" class="form-control form-control-sm nombreficha text-truncate"
               readonly tabindex="-1" placeholder="Serie / color" value="{{ $nombre }}">
    </div>
</div>
