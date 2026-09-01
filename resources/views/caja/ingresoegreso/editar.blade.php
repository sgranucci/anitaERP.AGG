@extends("theme.$theme.layout")
@section('titulo')
    Ingresos y Egresos de Caja
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/ingresoegreso/crear.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/ingresoegreso/crear.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/cuentacaja/consulta.js') }}" type="text/javascript"></script>
@include('includes.contable.asiento_montos_formato_js')
<script src="{{ asset('assets/pages/scripts/contable/asiento/asiento_externo.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/asiento/asiento_externo.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/conceptogasto/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/cbu_pago.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/banco/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/ingresoegreso/cheques.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/conceptos_ivacompra_coherencia.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/ingresoegreso/comprobantes_ivacompra.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/ingresoegreso/anular_revertir.js') }}" type="text/javascript"></script>
<script>
    var urlConsultaProveedor = "{{ route('editar_proveedor', ':id') }}";
    var ingresoEgresoChequeDiferidosHabilitado = @json((bool) config('caja.cheque_propio_imputacion_diferidos_habilitado'));
</script>
@endsection

@section('contenido')
@php
    $volverUrl = isset($caja_id) && $caja_id
        ? route('consulta_movimiento_caja')
        : route('ingresoegreso');
    $tituloIe = trim(($data->tipotransaccioncajas->nombre ?? '').' · N° '.($data->numerotransaccion ?? ''));
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        @if (! empty($solicitudpagoOrigen))
            <div class="alert alert-info">
                <strong>Vinculado a solicitud de pago #{{ $solicitudpagoOrigen->codigo }}</strong>
                @if ($solicitudpagoOrigen->estado)
                    — estado: {{ $solicitudpagoOrigen->estado }}
                @endif
                <a href="{{ route('editar_solicitudpago', ['id' => $solicitudpagoOrigen->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                   class="text-primary ml-2" target="_blank" rel="noopener">
                    Abrir solicitud
                </a>
            </div>
        @endif
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    Editar movimiento de caja
                    @if ($tituloIe !== '· N°')
                        <span class="ml-2 font-weight-normal small">{{ $tituloIe }}</span>
                    @endif
                    @if (! empty($caja_id))
                        <span class="ml-2 font-weight-normal small">Caja: {{ $caja_id }} — {{ $nombreCaja }}</span>
                    @endif
                </h3>
                <div class="card-tools">
                    <a href="{{ route('imprimir_ingresoegreso', $data->id) }}"
                       class="btn btn-outline-secondary btn-sm"
                       target="_blank" rel="noopener"
                       title="Emitir comprobante / orden de pago">
                        <i class="fa fa-print"></i> Imprimir
                    </a>
                    <a href="{{ $volverUrl }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                    <button type="button" id="boton-copia-ie" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-copy"></i> Copiar
                    </button>
                    @if (
                        can('revertir-ingresos-egresos-caja', false)
                        && empty($data->caja_movimiento_origen_id)
                        && empty($data->caja_movimiento_revertido_por_id)
                    )
                    <button type="button" id="boton-revierte-ie" class="btn btn-outline-warning btn-sm">
                        <i class="fa fa-history"></i> Revertir
                    </button>
                    @endif
                    @if (
                        can('anular-ingresos-egresos-caja', false)
                        && empty($data->caja_movimiento_origen_id)
                        && empty($data->caja_movimiento_revertido_por_id)
                    )
                    <form action="{{ route('anular_fisicamente_ingresoegreso', $data->id) }}"
                          class="d-inline form-anular-fisico-ie" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Anular físicamente">
                            <i class="fa fa-ban"></i> Anular físico
                        </button>
                    </form>
                    @endif
                </div>
            </div>
            <form action="{{ route('actualizar_ingresoegreso', ['id' => $data->id]) }}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off" data-mensaje-grabacion="Grabando OP…">
                <input type="hidden" class="caja_id" id="caja_id" name="caja_id" value="{{ $data->caja_id ?? '' }}">
                <input type="hidden" class="origen" id="origen" name="origen" value="{{ $origen ?? '' }}">
                @csrf @method('put')
                @include('caja.ingresoegreso.partials.tabs_header')
                <div class="card-body">
                    @include('caja.ingresoegreso.form')
                    @include('caja.ingresoegreso.form2')
                    @include('caja.ingresoegreso.form3')
                    @include('caja.ingresoegreso.form4')
                    @include('includes.contable.formasientoexterno')
                    @include('caja.ingresoegreso.form6')
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            <button type="button" id="botonform0" class="btn btn-success">
                                <i class="fa fa-save"></i> Actualizar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
