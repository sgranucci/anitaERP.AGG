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
@php
    $cantUsuarios = ($data->usuarios ?? collect())->count();
    $cantCuentas = ($data->cuentas ?? collect())->count();
    $cantFormas = ($data->formapagos ?? collect())->count();
    $soloConsulta = ! empty($soloConsulta);
    $ocultarVolver = ! empty($ocultarVolver);
    $puedeActualizar = ! empty($puedeActualizar);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    @if ($soloConsulta && ! $puedeActualizar)
                        Consultar concepto #{{ $data->codigo }}
                    @else
                        Editar concepto #{{ $data->codigo }}
                    @endif
                </h3>
                <div class="card-tools">
                    @if (! $ocultarVolver)
                        <a href="{{route('consultar_concepto_solicitudpago')}}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    @endif
                </div>
            </div>
            <form action="{{route('actualizar_concepto_solicitudpago', ['id' => $data->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" autocomplete="off"
                  @if ($soloConsulta && ! $puedeActualizar) onsubmit="return false;" @endif>
                @csrf @method("put")
                @if ($soloConsulta)
                    <input type="hidden" name="origen" value="modal_consulta">
                    <input type="hidden" name="vista" value="consulta">
                @endif
                <div class="card-body @if ($soloConsulta && ! $puedeActualizar) pe-none @endif"
                     @if ($soloConsulta && ! $puedeActualizar) style="opacity:.92" @endif>
                    <ul class="nav nav-tabs" id="tabs-concepto-sp" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="tab-datos-link" data-toggle="tab" href="#tab-datos" role="tab">
                                <i class="fa fa-info-circle"></i> Datos principales
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-usuarios-link" data-toggle="tab" href="#tab-usuarios" role="tab">
                                <i class="fa fa-sitemap"></i> &Aacute;rbol de aprobaci&oacute;n
                                @if ($cantUsuarios > 0)
                                    <span class="badge badge-info">{{ $cantUsuarios }}</span>
                                @endif
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-cuentas-link" data-toggle="tab" href="#tab-cuentas" role="tab">
                                <i class="fa fa-book"></i> Cuentas
                                @if ($cantCuentas > 0)
                                    <span class="badge badge-info">{{ $cantCuentas }}</span>
                                @endif
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="tab-formapagos-link" data-toggle="tab" href="#tab-formapagos" role="tab">
                                <i class="fa fa-credit-card"></i> Formas de pago
                                @if ($cantFormas > 0)
                                    <span class="badge badge-info">{{ $cantFormas }}</span>
                                @endif
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
                        <div class="col-lg-6 text-center">
                            @if (! $soloConsulta)
                                @include('includes.boton-form-editar')
                            @else
                                @if ($puedeActualizar)
                                    @include('includes.boton-form-editar')
                                @endif
                                <button type="button" class="btn btn-secondary @if ($puedeActualizar) ml-2 @endif" onclick="window.close()">Cerrar solapa</button>
                            @endif
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
