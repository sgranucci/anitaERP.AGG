@extends("theme.$theme.layout")
@section('titulo')
Nuevo recuento
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/recuento/form.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/recuento/movimientos_articulo.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/recuento/archivos.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/movimientostock/atajos-consulta.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-clipboard"></i> Nuevo recuento de inventario</h3>
                <div class="card-tools">
                    <a href="{{ route('recuento') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_recuento') }}" id="form-recuento" class="form-horizontal" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf
                <input type="hidden" name="tipo" id="recuento-tipo" value="{{ $tipo ?? 'MANUAL' }}">
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">Datos e ítems</button>
                    <button type="button" id="botonform2" class="btn btn-info btn-sm">Historia</button>
                    <button type="button" id="botonform3" class="btn btn-info btn-sm">Archivos</button>
                </div>
                <div class="card-body">
                    <div class="form1">
                        @include('stock.recuento.partials.form_cabecera', ['soloLectura' => false])
                        <hr class="my-3">
                        @include('stock.recuento.partials.form_items', ['soloLectura' => false])
                    </div>
                    <div class="form2" style="display:none;">
                        @include('stock.recuento.partials.solapa_estados')
                    </div>
                    <div class="form3" style="display:none;">
                        @include('stock.recuento.partials.archivos_adjuntos', ['data' => null, 'ocultarInputsConservar' => false])
                        @include('stock.recuento.partials.solapa_agregar_archivos')
                    </div>
                </div>
                <input type="hidden" id="recuento-saldo-articulo-url" value="{{ route('recuento_saldo_articulo') }}">
                <input type="hidden" id="recuento-movimientos-articulo-url" value="{{ route('recuento_movimientos_articulo') }}">
                <input type="hidden" id="recuento-aleatorio-url" value="{{ route('recuento_aleatorio') }}">
                <input type="hidden" id="recuento-csrf" value="{{ csrf_token() }}">
                <div class="card-footer recuento-form-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-crear')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
{{-- Modales fuera del form: un <form> anidado cierra #form-recuento en el DOM y el Guardar deja de enviar. --}}
@include('includes.stock.modalconsultaarticulo')
@include('includes.stock.modalconsultadeposito')
@include('stock.recuento.partials.modal_importar_excel', [
    'modoPreview' => true,
    'importUrl' => route('importar_recuento_preview'),
])
@endsection
