{{--
    Campo de consulta de provincia por código, con modal (F1 / lupa) y Enter para resolver.

    Requiere en la vista, fuera del <form>:
        @include('includes.configuracion.modalconsultaprovincia')
        <script src="{{ asset('assets/pages/scripts/configuracion/provincia/consulta.js') }}"></script>

    Parámetros:
        inputName            name del hidden con el ID (default provincia_id)
        inputId              id del hidden; el JS legacy espera provincia_id (default provincia_id)
        provinciaId          ID actual
        codigo / nombre      valores actuales (código Anita y nombre)
        jurisdiccion         jurisdicción actual (solo lectura, informativa)
        label                etiqueta (default Provincia)
        col_label/col_input  columnas Bootstrap
        requerido            bool
        solo_lectura         bool
        mostrar_jurisdiccion bool (default true en form_row; false en inline)
        layout               form_row (default) | inline (celda de grilla, sin label)
        extra_class          clases extra en .tm-provincia-campo (ej. tm-provincia-iibb-campo)
        codigoName / codigoId / nombreName / nombreId
        descName             hidden con el nombre (compat desc_provincia / desc_provincias[])
--}}
@php
    $cpInputName = $inputName ?? 'provincia_id';
    $cpInputId = $inputId ?? 'provincia_id';
    $cpLabel = $label ?? 'Provincia';
    $cpColLabel = $col_label ?? 'col-lg-4 control-label text-right pr-2';
    $cpColInput = $col_input ?? 'col-lg-8';
    $cpRequerido = (bool) ($requerido ?? false);
    $cpSoloLectura = (bool) ($solo_lectura ?? false);
    $cpLayout = $layout ?? 'form_row';
    $cpExtraClass = trim((string) ($extra_class ?? ''));
    $cpMostrarJurisdiccion = (bool) ($mostrar_jurisdiccion ?? ($cpLayout !== 'inline'));
    $cpCodigoName = $codigoName ?? 'provincia_codigo';
    $cpCodigoId = $codigoId ?? 'codigoprovincia';
    $cpNombreName = $nombreName ?? 'provincia_nombre';
    $cpNombreId = $nombreId ?? 'nombreprovincia';
    $cpDescName = $descName ?? null;
    $cpProvinciaId = old($cpInputName, $provinciaId ?? '');
    $cpCodigo = old($cpCodigoName, $codigo ?? '');
    $cpNombre = old($cpNombreName, $nombre ?? '');
    $cpJurisdiccion = old('provincia_jurisdiccion', $jurisdiccion ?? '');
    $cpHelp = $help ?? null;
    $cpUsaIds = $cpLayout !== 'inline';
@endphp
@if ($cpLayout === 'inline')
    <div class="tm-provincia-campo {{ $cpExtraClass }}">
        <input type="hidden" name="{{ $cpInputName }}" class="provincia_id"
            value="{{ $cpProvinciaId }}">
        @if ($cpDescName)
            <input type="hidden" class="desc_provincia" name="{{ $cpDescName }}" value="{{ $cpNombre }}">
        @endif
        <div class="d-flex flex-nowrap align-items-center" style="gap: 2px;">
            @if (! $cpSoloLectura)
                <button type="button" title="Consulta provincias (F1)"
                    class="btn-accion-tabla consultaprovincia tooltipsC flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
            @endif
            <input type="text" class="form-control form-control-sm codigoprovincia flex-shrink-0"
                value="{{ $cpCodigo }}" placeholder="C&oacute;d." autocomplete="off"
                style="width: 4rem;" title="C&oacute;digo de provincia. F1 = consulta, Enter = resolver"
                @if ($cpSoloLectura) readonly @endif>
            <input type="text" class="form-control form-control-sm nombreprovincia text-truncate"
                value="{{ $cpNombre }}" placeholder="Provincia" readonly
                style="min-width: 0; flex: 1 1 auto;">
        </div>
    </div>
@else
    <div class="form-group row tm-provincia-campo {{ $cpExtraClass }}">
        <label
            @if ($cpUsaIds)
                for="{{ $cpCodigoId }}"
            @endif
            class="{{ $cpColLabel }}{{ $cpRequerido ? ' requerido' : '' }}">{{ $cpLabel }}</label>
        <div class="{{ $cpColInput }}">
            <input type="hidden" name="{{ $cpInputName }}"
                @if ($cpUsaIds)
                    id="{{ $cpInputId }}"
                @endif
                class="provincia_id"
                value="{{ $cpProvinciaId }}" @if ($cpRequerido && ! $cpSoloLectura) required @endif>
            @if ($cpDescName)
                <input type="hidden"
                    @if ($cpUsaIds)
                        id="{{ $cpDescName }}"
                    @endif
                    class="desc_provincia" name="{{ $cpDescName }}" value="{{ $cpNombre }}">
            @endif
            <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
                @if (! $cpSoloLectura)
                    <button type="button" title="Consulta provincias (F1)"
                        class="btn-accion-tabla consultaprovincia tooltipsC flex-shrink-0">
                        <i class="fa fa-search text-primary"></i>
                    </button>
                @endif
                <input type="text" name="{{ $cpCodigoName }}"
                    @if ($cpUsaIds)
                        id="{{ $cpCodigoId }}"
                    @endif
                    class="form-control codigoprovincia" value="{{ $cpCodigo }}"
                    placeholder="C&oacute;d." autocomplete="off" style="width: 5.5rem; flex-shrink: 0;"
                    title="C&oacute;digo de provincia. F1 = consulta, Enter = resolver"
                    @if ($cpSoloLectura) readonly @endif>
                <input type="text" name="{{ $cpNombreName }}"
                    @if ($cpUsaIds)
                        id="{{ $cpNombreId }}"
                    @endif
                    class="form-control nombreprovincia text-truncate" value="{{ $cpNombre }}"
                    placeholder="Provincia" readonly>
                @if ($cpMostrarJurisdiccion)
                    <input type="text" class="form-control jurisdiccionprovincia text-center"
                        @if ($cpUsaIds)
                            id="{{ $cpInputId === 'provincia_id' ? 'jurisdiccionprovincia' : $cpInputId.'_jurisdiccion' }}"
                        @endif
                        value="{{ $cpJurisdiccion }}"
                        placeholder="Jur." readonly style="width: 4.5rem; flex-shrink: 0;"
                        title="Jurisdicci&oacute;n IIBB de la provincia">
                @endif
            </div>
            @if ($cpHelp)
                <small class="form-text text-muted">{{ $cpHelp }}</small>
            @endif
        </div>
    </div>
@endif
