@extends("theme.$theme.layout")
@section('titulo')
Editar salida de bienes
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/usuario/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/salida_bienes/form.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-truck"></i> Editar salida
                    <small>{{ $prestamo->codigo }}</small>
                    @include('stock.salida_bienes.partials.estado_badge', ['estado' => $prestamo->estado])
                </h3>
                <div class="card-tools">
                    <a href="{{ route('ver_salida_bienes', ['id' => $prestamo->id]) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-eye"></i> Ver detalle
                    </a>
                    <a href="{{ route('salida_bienes') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_salida_bienes', ['id' => $prestamo->id]) }}" id="form-prestamo" class="form-horizontal" method="POST" autocomplete="off">
                @csrf @method('put')
                <div class="card-body">
                    @include('stock.salida_bienes.form', ['modoEdicion' => true])
                </div>
            </form>
            <div class="card-footer">
                <div class="row">
                    <div class="col-lg-3"></div>
                    <div class="col-lg-6">
                        <button type="submit" form="form-prestamo" class="btn botonsubmit btn-success">Actualizar</button>
                    </div>
                    <div class="col-lg-3 text-right">
                        @if (can('confirmar-envio-salida-bienes', false))
                            <button type="button" class="btn btn-success" data-toggle="modal" data-target="#modal-confirmar-envio">
                                <i class="fa fa-paper-plane"></i> Confirmar envío
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@if (can('confirmar-envio-salida-bienes', false))
<div class="modal fade" id="modal-confirmar-envio" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <form action="{{ route('confirmar_envio_salida_bienes', ['id' => $prestamo->id]) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar envío</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Si hay artículos en inventario, genera la <strong>salida del depósito origen</strong>.</p>
                    <p>Depósito / usuario: se notifica para aprobar recepción. Externo: queda en estado Enviado.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">Confirmar envío</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endif
@include('includes.stock.modalconsultaarticulo')
@include('includes.stock.modalconsultadeposito')
@include('includes.admin.modalconsultausuario')
@endsection
