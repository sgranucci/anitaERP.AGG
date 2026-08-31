@extends("theme.$theme.layout")
@section('titulo')
    Ingresos y Egresos de Caja
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/ingresoegreso/crear.js') }}" type="text/javascript"></script>
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
<script>
    var urlConsultaProveedor = "{{ route('editar_proveedor', ':id') }}";
    var ingresoEgresoChequeDiferidosHabilitado = @json((bool) config('caja.cheque_propio_imputacion_diferidos_habilitado'));
</script>
@endsection

@section('contenido')
@php
    $volverUrl = route('ingresoegreso');
    if (($origen ?? '') === 'movimientocaja' || isset($caja_id)) {
        $volverUrl = route('consulta_movimiento_caja');
    } elseif (($origen ?? '') === 'solicitudpago') {
        $volverUrl = route('consultar_solicitudpago');
    }
@endphp
<div class="row" id="crear">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        @if (! empty($solicitudpagoOrigen))
            @php
                $montoSpUi = \App\Support\Caja\IngresoEgresoSolicitudpagoSupport::montoPendiente($solicitudpagoOrigen);
            @endphp
            <div class="alert alert-info">
                <strong>Pago desde solicitud de pago #{{ $solicitudpagoOrigen->codigo }}</strong>
                — monto fijo a pagar:
                <strong>{{ number_format($montoSpUi, 2, ',', '.') }}</strong>
                @if ($solicitudpagoOrigen->monedas)
                    {{ $solicitudpagoOrigen->monedas->abreviatura ?? '' }}
                @endif
                @if ($solicitudpagoOrigen->estado)
                    — estado: {{ $solicitudpagoOrigen->estado }}
                @endif
                <br>
                El total de cuentas de caja (y cheques) debe coincidir exactamente con ese monto; no puede ser ni mayor ni menor.
                @if (\App\Support\Caja\IngresoEgresoSolicitudpagoSupport::esPagoOpa($solicitudpagoOrigen))
                    Tipo de transacción: {{ \App\Support\Caja\IngresoEgresoSolicitudpagoSupport::abreviaturaTipoPago($solicitudpagoOrigen) }}
                    (solicitud anticipada). Se genera una OPA impaga en la cuenta corriente del proveedor
                    y el asiento imputa anticipos a proveedores.
                @else
                    Tipo de transacción: {{ \App\Support\Caja\IngresoEgresoSolicitudpagoSupport::abreviaturaTipoPago($solicitudpagoOrigen) }}.
                    El asiento contable se arma con las cuentas de la solicitud.
                @endif
                Al guardar, la solicitud AUTORIZADA pasa a PAGADA, se emite el PDF de la orden de pago y se vuelve al listado de solicitudes.
                <a href="{{ route('editar_solicitudpago', $solicitudpagoOrigen->id) }}" target="_blank" rel="noopener">
                    Abrir solicitud
                </a>
            </div>
        @endif
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    Crear movimiento de caja
                    @if (isset($caja_id))
                        <span class="ml-2 font-weight-normal small">Caja: {{ $caja_id }} — {{ $nombreCaja }}</span>
                    @endif
                </h3>
                <div class="card-tools">
                    <a href="{{ $volverUrl }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i>
                        @if (($origen ?? '') === 'solicitudpago')
                            Volver a solicitudes
                        @else
                            Volver al listado
                        @endif
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_ingresoegreso') }}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf
                @if (isset($caja_id))
                    <input type="hidden" class="caja_id" id="caja_id" name="caja_id" value="{{ $caja_id ?? '' }}">
                @endif
                <input type="hidden" class="origen" id="origen" name="origen" value="{{ $origen ?? '' }}">
                    @include('caja.ingresoegreso.partials.tabs_header')
                <div class="card-body">
                    @if (empty($solicitudpagoOrigen))
                        @include('caja.ingresoegreso.partials.modo_uso_selector')
                    @endif
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
                                <i class="fa fa-save"></i> Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
