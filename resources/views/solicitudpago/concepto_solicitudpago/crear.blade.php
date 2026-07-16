@extends("theme.$theme.layout")
@section('titulo')
    Conceptos solicitud de pago
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/admin/usuario/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/cuentacontable/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/solicitudpago/concepto_solicitudpago/crear.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Crear concepto de solicitud de pago</h3>
                <div class="card-tools">
                    <a href="{{route('consultar_concepto_solicitudpago')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{route('guardar_concepto_solicitudpago')}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off">
                @csrf
                <div class="card-body">
                    <ul class="nav nav-tabs" id="tabs-concepto-sp" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="tab-datos-link" data-toggle="tab" href="#tab-datos" role="tab">
                                <i class="fa fa-info-circle"></i> Datos principales
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-usuarios-link" data-toggle="tab" href="#tab-usuarios" role="tab">
                                <i class="fa fa-sitemap"></i> &Aacute;rbol de aprobaci&oacute;n
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-cuentas-link" data-toggle="tab" href="#tab-cuentas" role="tab">
                                <i class="fa fa-book"></i> Cuentas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-formapagos-link" data-toggle="tab" href="#tab-formapagos" role="tab">
                                <i class="fa fa-credit-card"></i> Formas de pago
                            </a>
                        </li>
                    </ul>
                    <div class="tab-content pt-3">
                        <div class="tab-pane fade show active" id="tab-datos" role="tabpanel">
                            @include('solicitudpago.concepto_solicitudpago.form_datos')
                        </div>
                        <div class="tab-pane fade" id="tab-usuarios" role="tabpanel">
                            @include('solicitudpago.concepto_solicitudpago.form_usuarios')
                        </div>
                        <div class="tab-pane fade" id="tab-cuentas" role="tabpanel">
                            @include('solicitudpago.concepto_solicitudpago.form_cuentas')
                        </div>
                        <div class="tab-pane fade" id="tab-formapagos" role="tabpanel">
                            @include('solicitudpago.concepto_solicitudpago.form_formapagos')
                        </div>
                    </div>
                </div>
                <div class="card-footer">
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
@endsection
