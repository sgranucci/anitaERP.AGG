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
        mostrar_jurisdiccion bool (default true)
--}}
@php
    $cpInputName = $inputName ?? 'provincia_id';
    $cpInputId = $inputId ?? 'provincia_id';
    $cpLabel = $label ?? 'Provincia';
    $cpColLabel = $col_label ?? 'col-lg-4 control-label text-right pr-2';
    $cpColInput = $col_input ?? 'col-lg-8';
    $cpRequerido = (bool) ($requerido ?? false);
    $cpSoloLectura = (bool) ($solo_lectura ?? false);
    $cpMostrarJurisdiccion = (bool) ($mostrar_jurisdiccion ?? true);
    $cpProvinciaId = old($cpInputName, $provinciaId ?? '');
    $cpCodigo = old('provincia_codigo', $codigo ?? '');
    $cpNombre = old('provincia_nombre', $nombre ?? '');
    $cpJurisdiccion = old('provincia_jurisdiccion', $jurisdiccion ?? '');
@endphp
<div class="form-group row tm-provincia-campo">
    <label for="{{ $cpInputId }}_codigo" class="{{ $cpColLabel }}">{{ $cpLabel }}</label>
    <div class="{{ $cpColInput }}">
        <input type="hidden" name="{{ $cpInputName }}" id="{{ $cpInputId }}" class="provincia_id"
            value="{{ $cpProvinciaId }}" @if ($cpRequerido && ! $cpSoloLectura) required @endif>
        <div class="d-flex flex-nowrap align-items-center w-100" style="gap: 4px;">
            @if (! $cpSoloLectura)
                <button type="button" title="Consulta provincias (F1)"
                    class="btn-accion-tabla consultaprovincia tooltipsC flex-shrink-0">
                    <i class="fa fa-search text-primary"></i>
                </button>
            @endif
            <input type="text" name="provincia_codigo" id="codigoprovincia"
                class="form-control codigoprovincia" value="{{ $cpCodigo }}"
                placeholder="C&oacute;d." autocomplete="off" style="width: 5.5rem; flex-shrink: 0;"
                title="C&oacute;digo de provincia. F1 = consulta, Enter = resolver"
                @if ($cpSoloLectura) readonly @endif>
            <input type="text" name="provincia_nombre" id="nombreprovincia"
                class="form-control nombreprovincia text-truncate" value="{{ $cpNombre }}"
                placeholder="Provincia" readonly>
            @if ($cpMostrarJurisdiccion)
                <input type="text" name="provincia_jurisdiccion" id="jurisdiccionprovincia"
                    class="form-control jurisdiccionprovincia text-center" value="{{ $cpJurisdiccion }}"
                    placeholder="Jur." readonly style="width: 4.5rem; flex-shrink: 0;"
                    title="Jurisdicci&oacute;n IIBB de la provincia">
            @endif
        </div>
    </div>
</div>
