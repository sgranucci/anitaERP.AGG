@php
    $salidaSeleccionadaId = old('salida_id', $datas['salida_id'] ?? '');
    $salidaSeleccionada = $salidas_query->firstWhere('id', (int) $salidaSeleccionadaId);
    $salidaSeleccionadaTexto = $salidaSeleccionada
        ? $salidaSeleccionada->nombre . ' — ' . $salidaSeleccionada->ubicacion
        : '';
    $salidaUbicacionHintClase = $salidaSeleccionada ? '' : 'd-none';
@endphp
<div class="form-group row">
    <label for="salida_seleccionada_texto" class="col-lg-3 col-form-label requerido">Salida</label>
    <div class="col-lg-6">
        <input type="hidden" name="salida_id" id="salida_id" value="{{ $salidaSeleccionadaId }}" required>
        <div class="input-group">
            <input type="text"
                   id="salida_seleccionada_texto"
                   class="form-control"
                   readonly
                   value="{{ $salidaSeleccionadaTexto }}"
                   placeholder="Seleccione una impresora…">
            <div class="input-group-append">
                <button type="button" class="btn btn-outline-primary" id="btn_abrir_modal_salida" title="Buscar impresora">
                    <i class="fa fa-search"></i> Buscar
                </button>
            </div>
        </div>
        <small class="form-text text-muted {{ $salidaUbicacionHintClase }}" id="salida_ubicacion_hint">
            @if ($salidaSeleccionada)
                Ubicación: {{ $salidaSeleccionada->ubicacion }}
            @endif
        </small>
    </div>
</div>
@include('includes.configuracion.modal-seleccion-salida', ['salidas' => $salidas_query])
<input type="hidden" name="programa" id="programa" class="form-control" value="{{ $programa }}"/>
<input type="hidden" name="urlretorno" id="urlretorno" class="form-control" value="{{ $urlRetorno ?? '' }}"/>
