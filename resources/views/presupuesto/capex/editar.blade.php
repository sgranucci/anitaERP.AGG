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
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                @if (!isset($visualizar))
                    <h3 class="card-title">
                        @if (! empty($soloConsulta) && empty($puedeActualizarCapex))
                            Consultar
                        @else
                            Editar
                        @endif
                        Capex - Número {{ $data->codigo ?? '' }} - Id {{ $data->id }} - Proyecto {{ $data->codigoproyecto }}
                    </h3>
                    <div class="card-tools">
                        @if (empty($ocultarVolver))
                            <a href="{{ route('consultar_capex') }}" class="btn btn-outline-info btn-sm">
                                <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                            </a>
                        @endif
                        @if (empty($soloConsulta))
                            <button type="submit" onclick="anulaCapex()" id="anulacapex" class="btn btn-warning" style="display: none">
                                <i class="fa fa-fw fa-cross"></i>
                                Anular Capex
                            </button>
                            <button type="submit" onclick="anulaCapex()" id="activacapex" class="btn btn-warning" style="display: none">
                                <i class="fa fa-fw fa-check"></i>
                                Activar Capex
                            </button>
                            <button type="submit" onclick="cierraCapex()" id="abrecapex" class="btn btn-success" style="display: none">
                                <i class="fa fa-fw fa-check"></i>
                                Activar Capex
                            </button>
                            <button type="submit" onclick="cierraCapex()" id="cierracapex" class="btn btn-success" style="display: none">
                                <i class="fa fa-fw fa-lock"></i>
                                Cerrar Capex
                            </button>
                        @endif
                    </div>
                @else
                    <h3 class="card-title">Visualizar Capex - Número {{ $data->codigo ?? '' }} - Id {{ $data->id }} - Proyecto {{ $data->codigoproyecto ?? '' }}</h3>
                @endif
            </div>
            <form action="{{ route('actualizar_capex', ['id' => $data->id]) }}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off" @if(!empty($soloConsulta) && empty($puedeActualizarCapex)) onsubmit="return false;" @endif>
                @csrf @method("put")
                @if (! empty($soloConsulta))
                    <input type="hidden" name="origen" value="modal_consulta">
                    <input type="hidden" name="vista" value="consulta">
                @endif
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
                <div class="card-body @if(!empty($soloConsulta) && empty($puedeActualizarCapex)) pe-none @endif" @if(!empty($soloConsulta) && empty($puedeActualizarCapex)) style="opacity:.92" @endif>
                    @include('presupuesto.capex.form')
                    @include('presupuesto.capex.form2')
                    @include('presupuesto.capex.form3')
                    @include('presupuesto.capex.form4')
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
