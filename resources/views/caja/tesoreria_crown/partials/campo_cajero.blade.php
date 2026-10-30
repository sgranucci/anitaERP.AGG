@php
    $cajeroCodigo = $codigo ?? '';
    $cajeroNombre = $nombre ?? '';
    $cajeroInputId = $inputId ?? 'cajero_numero_mesa';
@endphp
<div class="form-group col-md-7 mb-2 tc-cajero-campo"
     data-sala-id="{{ (int) ($salaId ?? 0) }}"
     data-resolver="{{ route('resolver_cajero_tesoreria_crown_jornada') }}"
     data-consulta="{{ route('consulta_cajero_tesoreria_crown_jornada') }}">
    <label for="{{ $cajeroInputId }}">Cajero</label>
    <div class="input-group">
        <input type="text"
               name="cajero_numero"
               id="{{ $cajeroInputId }}"
               class="form-control tc-cajero-codigo"
               inputmode="numeric"
               autocomplete="off"
               placeholder="Código"
               title="Código de cajero. F1 abre la búsqueda."
               value="{{ $cajeroCodigo }}"
               style="max-width:8rem;">
        <input type="text" class="form-control tc-cajero-nombre" readonly tabindex="-1" placeholder="Nombre" value="{{ $cajeroNombre }}">
        <div class="input-group-append">
            <button type="button" class="btn btn-outline-secondary tc-cajero-lupa" title="Buscar cajero (F1)">
                <i class="fa fa-search"></i>
            </button>
        </div>
    </div>
</div>
