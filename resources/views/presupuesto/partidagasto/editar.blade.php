@extends("theme.$theme.layout")
@section('titulo')
    Partida de Gasto
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/presupuesto/partidagasto/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/proveedor/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/contable/cuentacontable/consulta.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $volverListadoUrl = route('consultar_partidagasto', $filtrosQuery ?? []);
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                @if (!isset($visualizar))
                    <h3 class="card-title">Editar Partida de Gasto — {{$data->codigo ?? ''}} <small class="text-white-50">#{{$data->id}}</small></h3>
                    <div class="card-tools">
                        <a href="{{$volverListadoUrl}}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                        <button type="button" onclick="anulaPartidagasto()" id="anulapartidagasto" class="btn btn-warning btn-sm" style="display: none">
                            <i class="fa fa-fw fa-ban"></i>
                            Anular Partida
                        </button>
                        <button type="button" onclick="anulaPartidagasto()" id="activapartidagasto" class="btn btn-warning btn-sm" style="display: none">
                            <i class="fa fa-fw fa-check"></i>
                            Activar Partida
                        </button>
                        <button type="button" onclick="cierraPartidagasto()" id="abrepartidagasto" class="btn btn-success btn-sm" style="display: none">
                            <i class="fa fa-fw fa-check"></i>
                            Activar Partida
                        </button>
                        <button type="button" onclick="cierraPartidagasto()" id="cierrapartidagasto" class="btn btn-success btn-sm" style="display: none">
                            <i class="fa fa-fw fa-lock"></i>
                            Cerrar Partida
                        </button>
                    </div>
                @else
                    <h3 class="card-title">Visualizar Partida de Gasto — {{$data->codigo ?? ''}} <small class="text-white-50">#{{$data->id}}</small></h3>
                    <div class="card-tools">
                        <a href="{{$volverListadoUrl}}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                    </div>
                @endif
            </div>
            <form action="{{route('actualizar_partidagasto', ['id' => $data->id] + ($filtrosQuery ?? []))}}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf @method("put")
                <div class="card-body">
                    @include('includes.tabs-activas-estilos')
                    <div class="tabs-activas">
                        <ul class="nav nav-tabs" id="tabs-partidagasto" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#tab-partidagasto-datos" role="tab">
                                    <i class="fa fa-info-circle"></i> Datos principales
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#tab-partidagasto-historia" role="tab">
                                    <i class="fa fa-history"></i> Historia
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#tab-partidagasto-archivos" role="tab">
                                    <i class="fa fa-paperclip"></i> Archivos asociados
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#tab-partidagasto-oc" role="tab">
                                    <i class="fa fa-shopping-cart"></i> Órdenes de Compra
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="tab-content pt-3" id="partidagasto-tab-content">
                        <div class="tab-pane fade show active" id="tab-partidagasto-datos" role="tabpanel">
                            @include('presupuesto.partidagasto.form')
                        </div>
                        <div class="tab-pane fade" id="tab-partidagasto-historia" role="tabpanel">
                            @include('presupuesto.partidagasto.form2')
                        </div>
                        <div class="tab-pane fade" id="tab-partidagasto-archivos" role="tabpanel">
                            @include('presupuesto.partidagasto.form3')
                        </div>
                        <div class="tab-pane fade" id="tab-partidagasto-oc" role="tabpanel">
                            @include('presupuesto.partidagasto.form4')
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        @if (!isset($visualizar))
                            <div class="col-lg-6">
                                @include('includes.boton-form-editar')
                            </div>
                        @endif
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.compras.modalconsultaproveedor')
@include('includes.stock.modalconsultaarticulo')
@include('includes.contable.modalconsultacuentacontable')

@endsection
