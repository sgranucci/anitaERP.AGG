@extends("theme.$theme.layout")
@section('titulo')
Editar préstamo
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/prestamo/form.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/deposito-filtro-empresa.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-handshake-o"></i> Editar préstamo
                    <small>{{ $prestamo->codigo }}</small>
                    @include('stock.prestamo.partials.estado_badge', ['estado' => $prestamo->estado])
                </h3>
                <div class="card-tools">
                    <a href="{{ route('ver_prestamo', ['id' => $prestamo->id]) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-eye"></i> Ver detalle
                    </a>
                    <a href="{{ route('prestamo') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_prestamo', ['id' => $prestamo->id]) }}" id="form-prestamo" class="form-horizontal" method="POST" autocomplete="off">
                @csrf @method('put')
                <div class="card-body">
                    @include('stock.prestamo.form', ['modoEdicion' => true])
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-editar')
                        </div>
                        <div class="col-lg-3 text-right">
                            @if (can('confirmar-envio-prestamo', false))
                                <button type="button" class="btn btn-success" data-toggle="modal" data-target="#modal-confirmar-envio">
                                    <i class="fa fa-paper-plane"></i> Confirmar envío
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

@if (can('confirmar-envio-prestamo', false))
<div class="modal fade" id="modal-confirmar-envio" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <form action="{{ route('confirmar_envio_prestamo', ['id' => $prestamo->id]) }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirmar envío del préstamo</h5>
                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                </div>
                <div class="modal-body">
                    <p>Esta acción genera la <strong>salida del depósito origen</strong> y envía el correo al destinatario para que apruebe la recepción.</p>
                    <p>Asegurate de haber cargado los datos correctamente. Luego del envío el préstamo no podrá editarse hasta que el destinatario apruebe o rechace.</p>
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
@endsection
