@extends("theme.$theme.layout")
@section('titulo')
    Configuración de indumentaria
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/depmae/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/tipotransaccion_stock/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/tipotransaccion_stock/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/centrocosto/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/centrocosto/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script>
window.payloadExtraConsultaTipotransaccionStock = function () {
    return { operaciones: ['S'] };
};
$(function () {
    var $codigo = $('#deposito_id_codigo');
    if ($codigo.length && !$codigo.prop('readonly') && !$codigo.prop('disabled')) {
        setTimeout(function () {
            $codigo.trigger('focus').select();
        }, 50);
    }
});
</script>
@endsection

@section('contenido')
@php
    $puedeEditar = can('editar-configuracion-indumentaria', false);
    $depositoId = (int) old('deposito_id', $deposito->id ?? $config->deposito_id ?? 0);
    $tipoId = (int) old('tipotransaccion_stock_id', $tipo->id ?? $config->tipotransaccion_stock_id ?? 0);
    $centrocostoId = (int) old('centrocosto_id', $centrocosto->id ?? $config->centrocosto_id ?? 0);
@endphp
<div class="row">
    <div class="col-lg-8">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-cogs"></i> Configuración de entrega de indumentaria</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_prenda_sueldos') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-arrow-left"></i> Volver a Prendas
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_config_indumentaria') }}" method="POST" class="form-horizontal" autocomplete="off">
                @csrf @method('put')
                <div class="card-body">
                    <p class="text-muted">
                        La entrega de prendas descuenta stock y genera el asiento contable reutilizando el circuito de
                        <strong>Movimientos de stock</strong> (la cuenta contable se toma del artículo, igual que en mov. de stock).
                        En cada campo: código + Enter para resolver, F1 o lupa para consultar.
                    </p>

                    @include('stock.partials.campo_consulta_deposito', [
                        'prefix' => 'indumentaria',
                        'layout' => 'form_row',
                        'label' => 'Depósito de origen',
                        'inputName' => 'deposito_id',
                        'inputId' => 'deposito_id',
                        'depositoId' => $depositoId > 0 ? $depositoId : '',
                        'codigo' => old('deposito_codigo', $deposito->codigo ?? ''),
                        'descripcion' => old('deposito_descripcion', $deposito->nombre ?? ''),
                        'tipodeposito' => $deposito->tipodeposito ?? '',
                        'solo_lectura' => ! $puedeEditar,
                        'required' => true,
                        'col_label' => 'col-lg-4 control-label text-right pr-2',
                        'col_input' => 'col-lg-8',
                        'ayuda_tooltip' => 'Depósito del que salen las prendas al entregarlas.',
                    ])

                    @include('stock.partials.campo_consulta_tipotransaccion_stock', [
                        'prefix' => 'indumentaria',
                        'label' => 'Tipo de transacción de stock',
                        'inputName' => 'tipotransaccion_stock_id',
                        'inputId' => 'tipotransaccion_stock_id',
                        'tipoId' => $tipoId,
                        'abreviatura' => old('tipotransaccion_abreviatura', $tipo->abreviatura ?? ''),
                        'nombre' => old('tipotransaccion_nombre', $tipo->nombre ?? ''),
                        'operacion' => $tipo->operacion ?? 'S',
                        'maneja_contabilidad' => (bool) ($tipo->maneja_contabilidad ?? false),
                        'solo_lectura' => ! $puedeEditar,
                        'required' => true,
                        'col_label' => 'col-lg-4 control-label text-right pr-2',
                        'col_input' => 'col-lg-8',
                    ])
                    <div class="form-group row mb-2">
                        <div class="col-lg-4"></div>
                        <div class="col-lg-8">
                            <small class="text-muted">Salida de stock (operación S). Preconfigurado: ENTREGA DE INDUMENTARIA (EIND).</small>
                        </div>
                    </div>

                    @include('contable.partials.campo_consulta_centrocosto', [
                        'prefix' => 'indumentaria',
                        'layout' => 'form_row',
                        'label' => 'Centro de costo (opcional)',
                        'inputName' => 'centrocosto_id',
                        'inputId' => 'centrocosto_id',
                        'centrocostoId' => $centrocostoId > 0 ? $centrocostoId : '',
                        'codigo' => old('centrocosto_codigo', $centrocosto->codigo ?? ''),
                        'descripcion' => old('centrocosto_descripcion', $centrocosto->nombre ?? ''),
                        'solo_lectura' => ! $puedeEditar,
                        'required' => false,
                        'col_label' => 'col-lg-4 control-label text-right pr-2',
                        'col_input' => 'col-lg-8',
                        'ayuda' => 'Si se deja vacío, el asiento usa el centro de costo del empleado.',
                    ])
                </div>
                @if ($puedeEditar)
                    <div class="card-footer text-right">
                        <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Guardar configuración</button>
                    </div>
                @endif
            </form>
        </div>
    </div>
</div>
@include('includes.stock.modalconsultadeposito')
@include('includes.stock.modalconsultatipotransaccionstock')
@include('includes.contable.modalconsultacentrocosto')
@endsection
