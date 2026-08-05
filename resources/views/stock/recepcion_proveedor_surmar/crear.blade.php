@extends("theme.$theme.layout")
@section('titulo')
Nueva recepción Surmar
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-truck"></i> Nueva recepción Surmar</h3>
                <div class="card-tools">
                    <a href="{{ route('recepcion_proveedor_surmar') }}" class="btn btn-outline-info btn-sm"><i class="fa fa-reply-all"></i> Volver</a>
                </div>
            </div>
            <form action="{{ route('guardar_recepcion_proveedor_surmar') }}" method="POST" id="form-recepcion-surmar" class="form-horizontal" autocomplete="off">
                @csrf
                <input type="hidden" name="empresa_id" id="empresa_id" value="{{ $empresa_id }}">
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Se crea la recepción en estado <strong>provisorio</strong>. Después vas picando ítems: cada línea se graba al cerrarla (como en Anita).
                    </p>
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2">Empresa</label>
                        <div class="col-lg-6">
                            <input type="text" class="form-control" value="Surmar" readonly>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2 requerido">Fecha</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha" id="fecha" class="form-control" value="{{ old('fecha', date('Y-m-d')) }}" required>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2 requerido">Proveedor</label>
                        <div class="col-lg-6">
                            <div class="input-group">
                                <input type="hidden" name="proveedor_id" id="proveedor_id" class="proveedor_id" value="{{ old('proveedor_id') }}">
                                <input type="text" id="codigoproveedor" class="form-control codigoproveedor" placeholder="Cód." style="max-width:7rem;" title="Código proveedor">
                                <input type="text" id="nombreproveedor" class="form-control nombreproveedor descripcionproveedor" placeholder="Proveedor" readonly>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary consultaproveedor" title="Consultar proveedores (F1)"><i class="fa fa-search"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    @include('stock.partials.campo_consulta_deposito', [
                        'prefix' => 'recepcion_surmar',
                        'layout' => 'form_row',
                        'inputName' => 'deposito_id',
                        'inputId' => 'deposito_id',
                        'depositoId' => old('deposito_id'),
                        'codigo' => old('deposito_codigo', ''),
                        'descripcion' => old('deposito_descripcion', ''),
                        'col_label' => 'col-lg-4 control-label text-right pr-2 requerido',
                        'col_input' => 'col-lg-6',
                    ])
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2">Observación</label>
                        <div class="col-lg-6">
                            <textarea name="observacion" class="form-control" rows="2">{{ old('observacion') }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-arrow-right"></i> Iniciar piqueo</button>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.compras.modalconsultaproveedor')
@include('includes.stock.modalconsultadeposito')
@endsection
