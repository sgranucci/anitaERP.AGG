@extends("theme.$theme.layout")
@section('titulo')
    Nuevo programa de pagos
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Nuevo programa de pagos</h3>
                <div class="card-tools">
                    <a href="{{ route('programa_pago') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form id="form-programa-pago" method="POST" action="{{ route('guardar_programa_pago') }}" class="form-horizontal">
                @csrf
                <div class="card-body">
                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => old('empresa_id', $data->empresa_id ?? null),
                        'col_label' => 'col-lg-4 control-label text-right pr-2',
                        'col_input' => 'col-lg-6',
                    ])
                    <div class="form-group row">
                        <label for="titulo" class="col-lg-4 control-label text-right pr-2">Título</label>
                        <div class="col-lg-6">
                            <input type="text" name="titulo" id="titulo" class="form-control" maxlength="120"
                                   value="{{ old('titulo', $data->titulo) }}" placeholder="Ej. CASH JULIO 2026">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="fecha_base" class="col-lg-4 control-label text-right pr-2 requerido">Fecha base saldo</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha_base" id="fecha_base" class="form-control" required
                                   value="{{ old('fecha_base', optional($data->fecha_base)->format('Y-m-d') ?? $data->fecha_base) }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="anio_mes_inicio" class="col-lg-4 control-label text-right pr-2 requerido">Mes inicio</label>
                        <div class="col-lg-3">
                            <input type="month" name="anio_mes_inicio" id="anio_mes_inicio" class="form-control" required
                                   value="{{ old('anio_mes_inicio', $data->anio_mes_inicio) }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="cantidad_meses" class="col-lg-4 control-label text-right pr-2 requerido">Cantidad de meses</label>
                        <div class="col-lg-2">
                            <input type="number" name="cantidad_meses" id="cantidad_meses" class="form-control" min="1" max="12" required
                                   value="{{ old('cantidad_meses', $data->cantidad_meses) }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2">Columna TRANSF</label>
                        <div class="col-lg-6">
                            <div class="custom-control custom-checkbox mt-2">
                                <input type="hidden" name="incluye_transf" value="0">
                                <input type="checkbox" class="custom-control-input" id="incluye_transf" name="incluye_transf" value="1"
                                    @checked(old('incluye_transf', $data->incluye_transf))>
                                <label class="custom-control-label" for="incluye_transf">Incluir columna de transferencias inmediatas</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2">Sembrar deuda</label>
                        <div class="col-lg-6">
                            <div class="custom-control custom-checkbox mt-2">
                                <input type="hidden" name="sembrar_deuda" value="0">
                                <input type="checkbox" class="custom-control-input" id="sembrar_deuda" name="sembrar_deuda" value="1"
                                    @checked(old('sembrar_deuda', true))>
                                <label class="custom-control-label" for="sembrar_deuda">Cargar proveedores con saldo adeudado a la fecha base</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="detalle" class="col-lg-4 control-label text-right pr-2">Detalle</label>
                        <div class="col-lg-6">
                            <textarea name="detalle" id="detalle" class="form-control" rows="2">{{ old('detalle', $data->detalle) }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-success botonsubmit">
                        <i class="fa fa-save"></i> Crear programa
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
