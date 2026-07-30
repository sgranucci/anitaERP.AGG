@php
    $readonlyCalculo = isset($data->id) && $data->id;
@endphp

<div class="form-group row">
    <label for="empresa_id" class="col-lg-3 col-form-label text-right">Empresa <span class="text-danger">*</span></label>
    <div class="col-lg-6">
        <select name="empresa_id" id="empresa_id" class="form-control" required {{ $readonlyCalculo ? 'disabled' : '' }}>
            <option value="">-- Seleccione --</option>
            @foreach ($empresa_query as $empresa)
                <option value="{{ $empresa->id }}" {{ (int) old('empresa_id', $data->empresa_id ?? 0) === (int) $empresa->id ? 'selected' : '' }}>{{ $empresa->nombre }}</option>
            @endforeach
        </select>
        @if($readonlyCalculo)
            <input type="hidden" name="empresa_id" value="{{ $data->empresa_id }}">
        @endif
    </div>
</div>

<div class="form-group row">
    <label for="fecha" class="col-lg-3 col-form-label text-right">Fecha jornada <span class="text-danger">*</span></label>
    <div class="col-lg-3">
        <input type="date" name="fecha" id="fecha" class="form-control" required
               value="{{ old('fecha', optional($data->fecha)->format('Y-m-d')) }}"
               {{ $readonlyCalculo ? 'readonly' : '' }}>
    </div>
    <div class="col-lg-6">
        <button type="button" class="btn btn-outline-primary btn-sm mt-1" id="btn-flash-calcular">
            <i class="fa fa-calculator"></i> Calcular desde ERP/Wigos
        </button>
        <button type="button" class="btn btn-outline-secondary btn-sm mt-1 ml-1" id="btn-flash-desglose-wigos">
            <i class="fa fa-list-alt"></i> Desglose Wigos
        </button>
    </div>
</div>

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'flash-calculo-aviso',
    'tituloId' => 'flash-calculo-aviso-titulo',
    'subtituloId' => 'flash-calculo-aviso-subtitulo',
    'titulo' => 'Calculando flash…',
    'subtitulo' => 'Consultando ERP, Wigos y Anita. Por favor espere. No cierre ni recargue la página.',
])

<div class="modal fade" id="modal-flash-desglose-wigos" tabindex="-1" role="dialog" aria-labelledby="modal-flash-desglose-wigos-titulo" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-flash-desglose-wigos-titulo">Desglose Wigos — armado de totales</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="flash-desglose-wigos-body">
                <p class="text-muted mb-0">Todavía no hay desglose. Use <strong>Calcular</strong> o este botón para consultarlo.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-success" id="btn-flash-desglose-excel">
                    <i class="fa fa-file-excel-o"></i> Excel
                </button>
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

@if(isset($data->id))
<div class="form-group row">
    <label class="col-lg-3 col-form-label text-right"></label>
    <div class="col-lg-6">
        <div class="custom-control custom-checkbox">
            <input type="checkbox" class="custom-control-input" id="recalcular" name="recalcular" value="1">
            <label class="custom-control-label" for="recalcular">Recalcular autom&aacute;ticamente al guardar</label>
        </div>
    </div>
</div>
@endif

<hr>
<h5 class="text-muted mb-3">Datos manuales</h5>

<div class="form-group row">
    <label for="att" class="col-lg-3 col-form-label text-right">Asistencia (att)</label>
    <div class="col-lg-3">
        <input type="number" name="att" id="att" class="form-control" min="0" value="{{ old('att', $data->att) }}">
    </div>
    <label for="pos_online" class="col-lg-2 col-form-label text-right">POS on-line</label>
    <div class="col-lg-2">
        <input type="number" name="pos_online" id="pos_online" class="form-control" min="0" value="{{ old('pos_online', $data->pos_online ?? 0) }}">
    </div>
</div>

<div class="form-group row">
    <label for="show" class="col-lg-3 col-form-label text-right">Show</label>
    <div class="col-lg-3">
        <input type="text" inputmode="decimal" name="show" id="show" class="form-control flash-campo-decimal" autocomplete="off"
               value="{{ number_format((float) old('show', $data->show ?? 0), 2, ',', '.') }}">
    </div>
    <label for="cotizacion" class="col-lg-2 col-form-label text-right">Cotizaci&oacute;n</label>
    <div class="col-lg-2">
        <input type="number" step="0.0001" name="cotizacion" id="cotizacion" class="form-control" value="{{ old('cotizacion', $data->cotizacion) }}">
    </div>
</div>

<div class="form-group row">
    <label for="comentario" class="col-lg-3 col-form-label text-right">Comentario</label>
    <div class="col-lg-6">
        <input type="text" name="comentario" id="comentario" maxlength="30" class="form-control" value="{{ old('comentario', $data->comentario) }}">
    </div>
</div>

<hr>
<h5 class="text-muted mb-3">Gaming (Wigos)</h5>

@include('caja.flash.partials.campos_calculados')
