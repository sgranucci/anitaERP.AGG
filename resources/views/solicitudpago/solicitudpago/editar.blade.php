@extends("theme.$theme.layout")
@section('titulo')
    Solicitudes de pago
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/proveedor/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/cuentacontable/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/solicitudpago/solicitudpago/crear.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Editar solicitud de pago #{{ $data->codigo }}</h3>
                <div class="card-tools">
                    @if (can('actualizar-solicitud-pago', false))
                        @if ($data->estado !== 'SUSPENDIDA')
                            <form action="{{ route('suspender_solicitudpago', $data->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-warning btn-sm" onclick="return confirm('¿Suspender esta solicitud?');">
                                    <i class="fa fa-pause"></i> Suspender
                                </button>
                            </form>
                        @else
                            <form action="{{ route('levantar_suspension_solicitudpago', $data->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-success btn-sm" onclick="return confirm('¿Levantar suspensión?');">
                                    <i class="fa fa-play"></i> Levantar suspensi&oacute;n
                                </button>
                            </form>
                        @endif
                        @if ($data->estado === 'AUTORIZADA')
                            <a href="{{ route('ir_a_pago_solicitudpago', $data->id) }}" class="btn btn-outline-primary btn-sm">
                                <i class="fa fa-money"></i> Pagar (IE)
                            </a>
                            <form action="{{ route('marcar_pagada_solicitudpago', $data->id) }}" method="POST" class="d-inline">
                                @csrf
                                <button type="submit" class="btn btn-outline-success btn-sm" onclick="return confirm('¿Marcar como PAGADA sin IE?');">
                                    <i class="fa fa-check"></i> Marcar pagada
                                </button>
                            </form>
                        @endif
                    @endif
                    <a href="{{route('consultar_solicitudpago')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('actualizar_solicitudpago', ['id' => $data->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf
                @method('PUT')
                @include('solicitudpago.solicitudpago.partials.form_tabs')
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            @include('includes.boton-form-editar')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
