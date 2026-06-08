@extends("theme.$theme.layout")
@section('titulo')
Recuento {{ $recuento->codigo }}
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/recuento/form.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/recuento/movimientos_articulo.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/recuento/archivos.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">
                    Recuento {{ $recuento->codigo }}
                    @include('stock.recuento.partials.estado_badge', ['estado' => $recuento->estado])
                </h3>
                <div class="card-tools">
                    @if (can('imprimir-recuento', false))
                    <a href="{{ route('imprimir_pdf_recuento', ['id' => $recuento->id]) }}" class="btn btn-primary btn-sm" target="_blank">
                        <i class="fas fa-file-pdf"></i> PDF
                    </a>
                    @endif
                    <a href="{{ route('ver_recuento', ['id' => $recuento->id]) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-eye"></i> Ver
                    </a>
                    <a href="{{ route('recuento') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Listado
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_recuento', ['id' => $recuento->id]) }}" id="form-recuento" class="form-horizontal" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf @method('put')
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">Datos e ítems</button>
                    <button type="button" id="botonform2" class="btn btn-info btn-sm">Historia</button>
                    <button type="button" id="botonform3" class="btn btn-info btn-sm">Archivos</button>
                </div>
                <div class="card-body">
                    <div class="form1">
                        @include('stock.recuento.partials.form_cabecera', ['soloLectura' => $soloLectura])
                        <hr class="my-3">
                        @include('stock.recuento.partials.form_items', ['soloLectura' => $soloLectura])
                    </div>
                    <div class="form2" style="display:none;">
                        @include('stock.recuento.partials.solapa_estados', ['recuento' => $recuento])
                    </div>
                    <div class="form3" style="display:none;">
                        @include('stock.recuento.partials.archivos_adjuntos', ['data' => $recuento, 'ocultarInputsConservar' => $soloLectura])
                        @if (! $soloLectura)
                            @include('stock.recuento.partials.solapa_agregar_archivos', ['data' => $recuento])
                        @endif
                    </div>
                </div>
                <input type="hidden" id="recuento-saldo-articulo-url" value="{{ route('recuento_saldo_articulo') }}">
                <input type="hidden" id="recuento-movimientos-articulo-url" value="{{ route('recuento_movimientos_articulo') }}">
                <input type="hidden" id="recuento-aleatorio-url" value="{{ route('recuento_aleatorio') }}">
                <input type="hidden" id="recuento-csrf" value="{{ csrf_token() }}">
                @if (! $soloLectura)
                <div class="card-footer recuento-form-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-editar')
                        </div>
                    </div>
                </div>
                @endif
            </form>
        </div>
    </div>
</div>
@include('includes.stock.modalconsultaarticulo')
@include('includes.stock.modalconsultadeposito')
@if (! $soloLectura)
    @include('stock.recuento.partials.modal_importar_excel', [
        'recuento' => $recuento,
        'modoPreview' => false,
        'importUrl' => route('importar_recuento', ['id' => $recuento->id]),
    ])
@endif
@endsection
