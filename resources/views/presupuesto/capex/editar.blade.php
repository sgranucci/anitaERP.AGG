@extends("theme.$theme.layout")
@section('titulo')
    Capex
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/presupuesto/capex/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/proveedor/consulta.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('consultar_capex', $filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                @if (!isset($visualizar))
                    <h3 class="card-title">
                        @if (! empty($soloConsulta) && empty($puedeActualizarCapex))
                            Consultar
                        @else
                            Editar
                        @endif
                        Capex — {{ $data->codigo ?? '' }}
                        <small class="text-white-50">#{{ $data->id }} · {{ $data->codigoproyecto }}</small>
                    </h3>
                    <div class="card-tools">
                        @if (empty($ocultarVolver))
                            <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                                <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                            </a>
                        @endif
                        @if (empty($soloConsulta))
                            <button type="button" onclick="anulaCapex()" id="anulacapex" class="btn btn-warning btn-sm" style="display: none">
                                <i class="fa fa-fw fa-ban"></i>
                                Anular Capex
                            </button>
                            <button type="button" onclick="anulaCapex()" id="activacapex" class="btn btn-warning btn-sm" style="display: none">
                                <i class="fa fa-fw fa-check"></i>
                                Activar Capex
                            </button>
                            <button type="button" onclick="cierraCapex()" id="abrecapex" class="btn btn-success btn-sm" style="display: none">
                                <i class="fa fa-fw fa-check"></i>
                                Activar Capex
                            </button>
                            <button type="button" onclick="cierraCapex()" id="cierracapex" class="btn btn-success btn-sm" style="display: none">
                                <i class="fa fa-fw fa-lock"></i>
                                Cerrar Capex
                            </button>
                        @endif
                    </div>
                @else
                    <h3 class="card-title">Visualizar Capex — {{ $data->codigo ?? '' }} <small class="text-white-50">#{{ $data->id }} · {{ $data->codigoproyecto ?? '' }}</small></h3>
                    <div class="card-tools">
                        @if (empty($ocultarVolver))
                            <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                                <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                            </a>
                        @endif
                    </div>
                @endif
            </div>
            <form action="{{ route('actualizar_capex', ['id' => $data->id] + ($filtrosQuery ?? [])) }}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off" @if(!empty($soloConsulta) && empty($puedeActualizarCapex)) onsubmit="return false;" @endif>
                @csrf @method("put")
                @if (! empty($soloConsulta))
                    <input type="hidden" name="origen" value="modal_consulta">
                    <input type="hidden" name="vista" value="consulta">
                @endif
                <div class="card-body @if(!empty($soloConsulta) && empty($puedeActualizarCapex)) pe-none @endif" @if(!empty($soloConsulta) && empty($puedeActualizarCapex)) style="opacity:.92" @endif>
                    @include('includes.tabs-activas-estilos')
                    <div class="tabs-activas">
                        <ul class="nav nav-tabs" id="tabs-capex" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#tab-capex-datos" role="tab">
                                    <i class="fa fa-info-circle"></i> Datos principales
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#tab-capex-historia" role="tab">
                                    <i class="fa fa-history"></i> Historia
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#tab-capex-archivos" role="tab">
                                    <i class="fa fa-paperclip"></i> Archivos asociados
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#tab-capex-oc" role="tab">
                                    <i class="fa fa-shopping-cart"></i> Órdenes de Compra
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="tab-content pt-3" id="capex-tab-content">
                        <div class="tab-pane fade show active" id="tab-capex-datos" role="tabpanel">
                            @include('presupuesto.capex.form')
                        </div>
                        <div class="tab-pane fade" id="tab-capex-historia" role="tabpanel">
                            @include('presupuesto.capex.form2')
                        </div>
                        <div class="tab-pane fade" id="tab-capex-archivos" role="tabpanel">
                            @include('presupuesto.capex.form3')
                        </div>
                        <div class="tab-pane fade" id="tab-capex-oc" role="tabpanel">
                            @include('presupuesto.capex.form4')
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        @if (!isset($visualizar))
                            <div class="col-lg-6 text-center">
                                @if (empty($soloConsulta))
                                    @include('includes.boton-form-editar')
                                @else
                                    @if (! empty($puedeActualizarCapex))
                                        @include('includes.boton-form-editar')
                                    @endif
                                    <button type="button" class="btn btn-secondary @if(!empty($puedeActualizarCapex)) ml-2 @endif" onclick="window.close()">Cerrar solapa</button>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('presupuesto.capex.modalpartidamonto')
@include('includes.compras.modalconsultaproveedor')

@endsection
