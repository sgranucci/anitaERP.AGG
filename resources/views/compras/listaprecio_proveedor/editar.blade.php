@extends("theme.$theme.layout")
@section('titulo')
Editar lista de precios proveedor
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/crear.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/stock/articulo/consulta.js")}}" type="text/javascript"></script>
<script src="{{asset("assets/pages/scripts/compras/listaprecio_proveedor/crear.js")}}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row" id="editar">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">
                    @if (! empty($soloConsulta) && empty($puedeModificarLista))
                        Consultar
                    @else
                        Editar
                    @endif
                    lista: {{ $data->nombre }}
                </h3>
                <div class="card-tools">
                    @if (empty($ocultarVolver))
                    <a href="{{ route('consultar_listaprecio_proveedor') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                    @endif
                </div>
            </div>
            <form action="{{ route('actualizar_listaprecio_proveedor', ['id' => $data->id]) }}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off" @if(!empty($soloConsulta) && empty($puedeModificarLista)) onsubmit="return false;" @endif>
                @csrf @method('put')
                @if (! empty($soloConsulta))
                    <input type="hidden" name="origen" value="modal_consulta">
                @endif
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">Datos principales</button>
                    <button type="button" id="botonform3" class="btn btn-info btn-sm">Historia estados</button>
                    <button type="button" id="botonform4" class="btn btn-info btn-sm"><span class="fa fa-copy"></span> Archivos asociados</button>
                    @if (can('actualizar-listaprecio-proveedor', false) && empty($visualizar))
                    <button type="button" id="botonform-importexcel" class="btn btn-success btn-sm">
                        <i class="fa fa-file-excel-o"></i> Importar Excel
                    </button>
                    @endif
                </div>
                <div class="@if(!empty($soloConsulta) && empty($puedeModificarLista)) pe-none @endif" @if(!empty($soloConsulta) && empty($puedeModificarLista)) style="opacity:.92" @endif>
                <div class="card-body">
                    @include('compras.listaprecio_proveedor.form', ['visualizar' => $visualizar ?? false])
                    <div class="form3" style="display:none;">
                        <h5>Historia de estados</h5>
                        <table class="table table-bordered">
                            <thead><tr><th>Fecha / hora</th><th>Estado</th><th>Usuario</th><th>Observación</th></tr></thead>
                            <tbody class="container-historia"></tbody>
                        </table>
                    </div>
                    @include('compras.listaprecio_proveedor.form_archivos')
                </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-12 text-center">
                            @if (! empty($soloConsulta))
                                @if (! empty($puedeModificarLista))
                                    @include('includes.boton-form-editar')
                                @endif
                                <button type="button" class="btn btn-secondary @if(!empty($puedeModificarLista)) ml-2 @endif" onclick="window.close()">Cerrar solapa</button>
                            @else
                                @include('includes.boton-form-editar')
                            @endif
                        </div>
                    </div>
                </div>
            </form>

            @if (can('actualizar-listaprecio-proveedor', false) && empty($visualizar))
            <div class="card-body border-top" id="importar-excel" style="display: none;">
                <h5 class="mb-2"><i class="fa fa-file-excel-o text-success"></i> Importar precios desde Excel</h5>
                <p class="small text-muted mb-2">Columnas: <strong>A</strong> = SKU artículo, <strong>B</strong> = precio, <strong>C</strong> = % descuento (opcional), <strong>D</strong> = código artículo proveedor (opcional). La primera fila puede ser encabezado (SKU, …).</p>
                <form action="{{ route('importar_excel_listaprecio_proveedor', ['id' => $data->id]) }}" method="POST" enctype="multipart/form-data" class="form-inline flex-wrap align-items-end">
                    @csrf
                    <div class="form-group mr-2 mb-2">
                        <label for="fechavigencia_import" class="d-block">Fecha de vigencia de los precios importados</label>
                        <input type="date" name="fechavigencia" id="fechavigencia_import" class="form-control" required value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="form-group mr-2 mb-2">
                        <label for="archivoexcel" class="d-block">Archivo (.xlsx, .xls, .csv)</label>
                        <input type="file" name="archivoexcel" id="archivoexcel" class="form-control-file" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <button type="submit" class="btn btn-success mb-2">Importar</button>
                </form>
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
