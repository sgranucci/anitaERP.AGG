@extends("theme.$theme.layout")
@section('titulo')
Editar f&oacute;rmula de art&iacute;culo
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/formula_articulo/formulario.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Editar f&oacute;rmula Id: {{ $data->id }}</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_formula_articulo') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('actualizar_formula_articulo', ['id' => $data->id]) }}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf
                @method('put')
                <input type="hidden" id="formula_articulo_id_edit" value="{{ $data->id }}" />
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">
                        <i class="fa fa-flask"></i> Datos principales
                    </button>
                    <button type="button" id="botonform3" class="btn btn-info btn-sm">
                        <i class="fa fa-history"></i> Historia
                    </button>
                    <button type="button" id="botonform4" class="btn btn-info btn-sm">
                        <span class="fa fa-paperclip"></span> Archivos asociados
                    </button>
                </div>
                <div class="card-body">
                    @include('stock.formula_articulo.form')
                    <div class="form3 formula-solapa-historia" id="formula-solapa-historia" style="display:none;">
                        <h5 class="mb-3">Historia de estados</h5>
                        <table class="table table-bordered table-sm">
                            <thead class="thead-light">
                                <tr>
                                    <th>Fecha y hora</th>
                                    <th>Estado</th>
                                    <th>Usuario</th>
                                    <th>Observaci&oacute;n</th>
                                </tr>
                            </thead>
                            <tbody class="container-historia-formula"></tbody>
                        </table>
                        <div class="form-group mt-3">
                            <label for="observacion_estado">Observaci&oacute;n del cambio de estado</label>
                            <input type="text" name="observacion_estado" id="observacion_estado" class="form-control" value="{{ old('observacion_estado') }}" maxlength="500" placeholder="Obligatoria solo si modifica el estado en Datos principales" autocomplete="off" />
                            <small class="form-text text-muted">Si cambia el estado en la solapa <strong>Datos principales</strong>, debe completar este campo antes de guardar.</small>
                        </div>
                    </div>
                    <div class="form4 formula-solapa-archivos" id="formula-solapa-archivos" style="display:none;">
                        <p class="text-muted small mb-2">Archivos actuales</p>
                        @include('stock.formula_articulo.partials.archivos_adjuntos', ['data' => $data, 'ocultarInputsConservar' => false])
                        @include('stock.formula_articulo.partials.solapa_agregar_archivos_formula', ['data' => $data])
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Actualizar</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.stock.modalconsultaarticulo')
@include('includes.stock.modalconsultaformula')
@include('stock.formula_articulo.partials.modal_articulos_formula')
@endsection
