@extends("theme.$theme.layout")
@section('titulo')
    Ingresos y Egresos de Caja
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/ingresoegreso/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/cuentacaja/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/asiento/asiento_externo.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/conceptogasto/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}" type="text/javascript"></script>
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
    $volverUrl = isset($caja_id)
        ? route('consulta_movimiento_caja')
        : route('ingresoegreso');
@endphp
<div class="row" id="crear">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        @if (! empty($solicitudpagoOrigen))
            <div class="alert alert-info">
                <strong>Pago desde solicitud de pago #{{ $solicitudpagoOrigen->codigo }}</strong>
                — monto SP: {{ number_format((float) $solicitudpagoOrigen->monto, 2, ',', '.') }}
                @if ($solicitudpagoOrigen->estado)
                    — estado: {{ $solicitudpagoOrigen->estado }}
                @endif
                <br>
                Al guardar este IE, si la solicitud está AUTORIZADA pasará a PAGADA.
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
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
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
                    @include('caja.ingresoegreso.form')
                    @include('caja.ingresoegreso.form2')
                    @include('caja.ingresoegreso.form3')
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
