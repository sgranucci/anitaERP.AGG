@extends("theme.$theme.layout")
@section('titulo')
    Nueva cobranza
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/cuentacaja/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/cuentacaja/consulta.js')) ?: time() }}" type="text/javascript"></script>
@include('includes.contable.asiento_montos_formato_js')
<script src="{{ asset('assets/pages/scripts/contable/asiento/asiento_externo.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/asiento/asiento_externo.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/cliente/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/cliente/consulta.js')) ?: time() }}" type="text/javascript"></script>
@include('includes.ventas.cliente_politica_contexto', ['contextoPoliticaCliente' => 'cobranza'])
<script src="{{ asset('assets/pages/scripts/caja/banco/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/cobranza/crear.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/cobranza/crear.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/cobranza/descuento_comprobante.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/caja/cobranza/descuento_comprobante.js')) ?: time() }}" type="text/javascript"></script>
@if ($puede_nd_cheque ?? false)
<script>
window.chequeRechazoNdUrls = {
    datos: @json(url('caja/cheque/:id/rechazo-nd')),
    emitir: @json(url('caja/cheque/:id/rechazar-nd'))
};
</script>
<script src="{{ asset('assets/pages/scripts/caja/cheque/rechazo_nd.js') }}" type="text/javascript"></script>
@endif
@endsection

@section('contenido')
<div class="row" id="crear">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Crear cobranza
                    @if (isset($caja_id))
                        — Caja: {{ $caja_id }} {{ $nombreCaja ?? '' }}
                    @endif
                </h3>
                <div class="card-tools">
                    <a href="{{ route('cobranza') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_cobranza') }}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off" data-usuario-id="{{ auth()->id() }}" data-mensaje-grabacion="Grabando cobranza…">
                @csrf
                @if (isset($caja_id))
                    <input type="hidden" class="caja_id" id="caja_id" name="caja_id" value="{{ $caja_id ?? '' }}">
                @endif
                <input type="hidden" class="origen" id="origen" name="origen" value="{{ $origen ?? '' }}">
                @include('caja.cobranza.partials.tabs_header')
                @include('caja.cobranza.partials.resumen_liquidacion')
                <div class="card-body">
                    @include('caja.cobranza.form')
                    @include('caja.cobranza.form2')
                    @include('caja.cobranza.form3')
                    @include('caja.cobranza.form4')
                    @include('caja.cobranza.form5')
                    @include('includes.contable.formasientoexterno')
                    @include('caja.cobranza.form7')
                </div>
                <div class="card-footer">
                    <button type="button" id="botonform0" class="btn btn-success">
                        <i class="fa fa-save"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
