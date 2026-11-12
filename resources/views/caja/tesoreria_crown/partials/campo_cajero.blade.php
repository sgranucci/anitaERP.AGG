@php
    $cajeroCodigo = $codigo ?? '';
    $cajeroNombre = $nombre ?? '';
    $cajeroInputId = $inputId ?? 'cajero_numero_mesa';
    $cajeroCol = $colClass ?? 'col-md-7';
    $cajeroCodigoName = $codigoName ?? 'cajero_numero';
    $cajeroIdName = $inputNameId ?? null;
    $cajeroIdValor = $cajeroId ?? '';
@endphp
<div class="form-group {{ $cajeroCol }} mb-2 tc-cajero-campo"
     data-sala-id="{{ (int) ($salaId ?? 0) }}"
     data-resolver="{{ route('resolver_cajero_tesoreria_crown_jornada') }}"
     data-consulta="{{ route('consulta_cajero_tesoreria_crown_jornada') }}">
    <label for="{{ $cajeroInputId }}">Cajero</label>
    <div class="input-group">
        @if ($cajeroIdName)
            <input type="hidden" name="{{ $cajeroIdName }}" class="tc-cajero-id" value="{{ $cajeroIdValor }}">
        @endif
        <input type="text"
               @if ($cajeroCodigoName !== '')
                   name="{{ $cajeroCodigoName }}"
               @endif
               id="{{ $cajeroInputId }}"
               class="form-control tc-cajero-codigo"
               inputmode="numeric"
               autocomplete="off"
               placeholder="Código"
               title="Código o legajo. F1 abre la búsqueda."
               value="{{ $cajeroCodigo }}"
               style="max-width:8rem;">
        <input type="text" class="form-control tc-cajero-nombre" readonly tabindex="-1" placeholder="Nombre" value="{{ $cajeroNombre }}">
        <div class="input-group-append">
            <button type="button" class="btn-accion-tabla tooltipsC tc-cajero-lupa" title="Buscar cajero (F1)">
                <i class="fa fa-search"></i>
            </button>
        </div>
    </div>
    @if (!empty($ayuda))
        <small class="text-muted">{{ $ayuda }}</small>
    @endif
</div>
