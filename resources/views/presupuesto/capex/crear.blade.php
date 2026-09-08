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
<div class="row" id="crear">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Crear Capex</h3>
                <div class="card-tools">
                    <a href="{{ $volverListadoUrl }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_capex', $filtrosQuery ?? []) }}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf
                <div class="card-body">
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
                        <div class="col-lg-6">
                            @include('includes.boton-form-crear')
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('presupuesto.capex.modalpartidamonto')
@include('includes.compras.modalconsultaproveedor')
@endsection
