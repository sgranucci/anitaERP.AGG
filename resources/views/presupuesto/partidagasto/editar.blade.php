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
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                @if (!isset($visualizar))
                    <h3 class="card-title">Editar Partida de Gasto - Número {{$data->codigo ?? ''}} - Id {{$data->id}} - Proyecto {{$data->codigoproyecto}}</h3>
                    <div class="card-tools">
                        <a href="{{route('consultar_partidagasto')}}" class="btn btn-outline-info btn-sm">
                            <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                        </a>
                        <button type="submit" onclick="anulaPartidagasto()" id="anulapartidagasto" class="btn btn-warning" style="display: none">
                            <i class="fa fa-fw fa-cross"></i>
                            Anular Partida
                        </button>
                        <button type="submit" onclick="anulaPartidagasto()" id="activapartidagasto" class="btn btn-warning" style="display: none">
                            <i class="fa fa-fw fa-check"></i>
                            Activar Partida
                        </button>
                        <button type="submit" onclick="cierraPartidagasto()" id="abrepartidagasto" class="btn btn-success" style="display: none">
                            <i class="fa fa-fw fa-check"></i>
                            Activar Partida
                        </button>
                        <button type="submit" onclick="cierraPartidagasto()" id="cierrapartidagasto" class="btn btn-success" style="display: none">
                            <i class="fa fa-fw fa-lock"></i>
                            Cerrar Partida
                        </button>
                    </div>
                @else
                    <h3 class="card-title">Visualizar Partida de Gasto - Número {{$data->codigo ?? ''}} - Id {{$data->id}}</h3>
                @endif
            </div>
            <form action="{{route('actualizar_partidagasto', ['id' => $data->id])}}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf @method("put")
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">
                        <i class="fa fa-user"></i> Datos principales
                    </button>
                    <button type="button" id="botonform2" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Historia
                    </button>                    
                    <button type="button" id="botonform3" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Archivos asociados
                    </button>
                    <button type="button" id="botonform4" class="btn btn-info btn-sm">
                        <span class="fa fa-copy"></span> Ordenes de Compra
                    </button>     
                </div>
                <div class="card-body">
                    @include('presupuesto.partidagasto.form')
                    @include('presupuesto.partidagasto.form2')
                    @include('presupuesto.partidagasto.form3')
                    @include('presupuesto.partidagasto.form4')
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
