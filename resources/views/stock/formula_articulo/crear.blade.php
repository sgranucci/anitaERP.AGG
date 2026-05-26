@extends("theme.$theme.layout")
@section('titulo')
Nueva f&oacute;rmula de art&iacute;culo
@endsection

@section("scripts")
<script>
window.formulaArticuloSubformulaConsulta = {
    urlFormulaBase: @json(rtrim(config('app.app_carpeta'), '/') . '/stock/formula-articulo'),
    urlArticuloBase: @json(rtrim(config('app.app_carpeta'), '/') . '/stock/articulo'),
    urlCostosUltimaCompra: @json(route('costos_ultima_compra_formula_articulo')),
    mostrarCodigoComoNumero: @json(\App\Support\Stock\FormulaArticuloNumero::mostrarCodigo())
};
</script>
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/formula_articulo/formulario.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row" id="crear">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-danger">
            <div class="card-header">
                <h3 class="card-title">Nueva f&oacute;rmula de art&iacute;culo</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_formula_articulo') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_formula_articulo') }}" id="form-general" class="form-horizontal form--label-right" method="POST" enctype="multipart/form-data" autocomplete="off">
                @csrf
                <div align="center" style="margin: 5px;">
                    <button type="button" id="botonform1" class="btn btn-primary btn-sm">
                        <i class="fa fa-flask"></i> Datos principales
                    </button>
                    <button type="button" id="botonform4" class="btn btn-info btn-sm">
                        <span class="fa fa-paperclip"></span> Archivos asociados
                    </button>
                </div>
                <div class="card-body">
                    @include('stock.formula_articulo.form')
                    <div class="form4 formula-solapa-archivos" id="formula-solapa-archivos" style="display:none;">
                        <p class="text-muted small mb-2">Archivos actuales</p>
                        @include('stock.formula_articulo.partials.archivos_adjuntos', ['data' => $data ?? null, 'ocultarInputsConservar' => false])
                        @include('stock.formula_articulo.partials.solapa_agregar_archivos_formula', ['data' => $data ?? null])
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Guardar</button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.stock.modalconsultaarticulo')
@include('includes.stock.modalconsultaformula')
@include('stock.formula_articulo.partials.modal_ver_formula_articulo')
@endsection
