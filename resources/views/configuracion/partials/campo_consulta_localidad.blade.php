{{--
    Campo de consulta de localidad por código, con modal (F1 / lupa) y Enter para resolver.

    Requiere en la vista, fuera del <form>:
        @include('includes.configuracion.modalconsultalocalidad')
        <script src="{{ asset('assets/pages/scripts/configuracion/localidad/consulta.js') }}"></script>

    Parámetros:
        inputName / inputId  hidden con el ID (default localidad_id)
        localidadId          ID actual
        codigo / nombre      valores actuales
        label                etiqueta (default Localidad)
        col_label/col_input  columnas Bootstrap
        requerido / solo_lectura
        layout               form_row (default) | inline
        extra_class
        codigoName / codigoId / nombreName / nombreId
        previaName           hidden localidad_id_previa (default localidad_id_previa)
        descName             hidden desc_localidad (default desc_localidad)
        provinciaSource      selector CSS del hidden de provincia para filtrar el modal
--}}
@php
    $clInputName = $inputName ?? 'localidad_id';
    $clInputId = $inputId ?? 'localidad_id';
    $clLabel = $label ?? 'Localidad';
    $clColLabel = $col_label ?? 'col-lg-4 control-label text-right pr-2';
    $clColInput = $col_input ?? 'col-lg-8';
    $clRequerido = (bool) ($requerido ?? false);
    $clSoloLectura = (bool) ($solo_lectura ?? false);
    $clLayout = $layout ?? 'form_row';
    $clExtraClass = trim((string) ($extra_class ?? ''));
    $clCodigoName = $codigoName ?? 'codigolocalidad';
    $clCodigoId = $codigoId ?? 'codigolocalidad';
    $clNombreName = $nombreName ?? 'nombrelocalidad';
    $clNombreId = $nombreId ?? 'nombrelocalidad';
    $clPreviaName = $previaName ?? 'localidad_id_previa';
    $clDescName = $descName ?? 'desc_localidad';
    $clProvinciaSource = $provinciaSource ?? '';
    $clLocalidadId = old($clInputName, $localidadId ?? '');
    $clCodigo = old($clCodigoName, $codigo ?? '');
    $clNombre = old($clNombreName, $nombre ?? '');
    $clUsaIds = $clLayout !== 'inline';
@endphp
@if ($clLayout === 'inline')
    <div class="tm-localidad-campo {{ $clExtraClass }}"
        @if ($clProvinciaSource !== '')
            data-provincia-source="{{ $clProvinciaSource }}"
        @endif
    >
        <input type="hidden" name="{{ $clInputName }}" class="localidad_id" value="{{ $clLocalidadId }}">
        <input type="hidden" class="localidad_id_previa" name="{{ $clPreviaName }}" value="{{ $clLocalidadId }}">
        <input type="hidden" class="desc_localidad" name="{{ $clDescName }}" value="{{ $clNombre }}">
        <div class="d-flex flex-nowrap align-items-center" style="gap: 2px;">
            @if (! $clSoloLectura)
                <button type="button" title="Consulta localidades (F1)"
                    class="btn-accion-tabla consultalocalidad tooltipsC flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
            @endif
            <input type="text" class="form-control form-control-sm codigolocalidad flex-shrink-0"
                value="{{ $clCodigo }}" placeholder="C&oacute;d." autocomplete="off"
                style="width: 4rem;" title="C&oacute;digo de localidad. F1 = consulta, Enter = resolver"
                @if ($clSoloLectura) readonly @endif>
            <input type="text" class="form-control form-control-sm nombrelocalidad text-truncate"
                value="{{ $clNombre }}" placeholder="Localidad" readonly
                style="min-width: 0; flex: 1 1 auto;">
        </div>
    </div>
@else
    <div class="form-group row tm-localidad-campo {{ $clExtraClass }}"
        @if ($clProvinciaSource !== '')
            data-provincia-source="{{ $clProvinciaSource }}"
        @endif
        id="loc"
    >
        <label
            @if ($clUsaIds)
                for="{{ $clCodigoId }}"
            @endif
            class="{{ $clColLabel }}{{ $clRequerido ? ' requerido' : '' }}">{{ $clLabel }}</label>
        <div class="{{ $clColInput }}">
            <input type="hidden" name="{{ $clInputName }}"
                @if ($clUsaIds)
                    id="{{ $clInputId }}"
                @endif
                class="localidad_id" value="{{ $clLocalidadId }}"
                @if ($clRequerido && ! $clSoloLectura) required @endif>
            <input type="hidden"
                @if ($clUsaIds)
                    id="localidad_id_previa"
                @endif
                class="localidad_id_previa" name="{{ $clPreviaName }}" value="{{ $clLocalidadId }}">
            <input type="hidden"
                @if ($clUsaIds)
                    id="desc_localidad"
                @endif
                class="desc_localidad" name="{{ $clDescName }}" value="{{ $clNombre }}">
            <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
                @if (! $clSoloLectura)
                    <button type="button" title="Consulta localidades (F1)"
                        class="btn-accion-tabla consultalocalidad tooltipsC flex-shrink-0">
                        <i class="fa fa-search text-primary"></i>
                    </button>
                @endif
                <input type="text" name="{{ $clCodigoName }}"
                    @if ($clUsaIds)
                        id="{{ $clCodigoId }}"
                    @endif
                    class="form-control codigolocalidad flex-shrink-0" value="{{ $clCodigo }}"
                    placeholder="C&oacute;d." autocomplete="off" style="width: 5.5rem;"
                    title="C&oacute;digo de localidad. F1 = consulta, Enter = resolver"
                    @if ($clSoloLectura) readonly @endif>
                <input type="text" name="{{ $clNombreName }}"
                    @if ($clUsaIds)
                        id="{{ $clNombreId }}"
                    @endif
                    class="form-control nombrelocalidad" value="{{ $clNombre }}"
                    placeholder="Descripci&oacute;n" readonly style="min-width: 0; flex: 1 1 auto;">
            </div>
        </div>
    </div>
@endif
