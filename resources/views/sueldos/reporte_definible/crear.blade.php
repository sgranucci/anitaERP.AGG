@extends("theme.$theme.layout")
@section('titulo')
    Nuevo listado definible
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/sueldos/reporte_definible/asociado-consulta.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-8">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Nuevo listado definible</h3>
                <div class="card-tools">
                    <a href="{{ route('reporte_sueldos_definible') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Volver
                    </a>
                </div>
            </div>
            <form method="post" action="{{ route('guardar_reporte_sueldos_definible') }}" class="form-horizontal" id="form-reporte-sueldos-definible">
                @csrf
                <div class="card-body">
                    <div class="form-group row">
                        <label for="codigo" class="col-lg-4 control-label text-right pr-2">Código</label>
                        <div class="col-lg-4">
                            <input type="number" name="codigo" id="codigo" class="form-control" value="{{ old('codigo') }}" min="1"
                                   placeholder="Vacío = automático">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="titulo" class="col-lg-4 control-label text-right pr-2 requerido">Título</label>
                        <div class="col-lg-8">
                            <input type="text" name="titulo" id="titulo" class="form-control" value="{{ old('titulo') }}" required maxlength="80">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label for="tipo" class="col-lg-4 control-label text-right pr-2 requerido">Tipo</label>
                        <div class="col-lg-4">
                            <select name="tipo" id="tipo" class="form-control" required>
                                @foreach ($tiposListado as $k => $v)
                                    <option value="{{ $k }}" {{ old('tipo', 'generico') === $k ? 'selected' : '' }}>{{ $v }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @include('sueldos.reporte_definible.partials.campo_consulta_asociado', [
                        'codigo' => old('asociado_codigo'),
                        'col_label' => 'col-lg-4 control-label text-right pr-2',
                        'col_input' => 'col-lg-8',
                    ])
                    @include('includes.form-empresa-asignada', [
                        'empresa_query' => $empresa_query,
                        'empresa_id' => old('empresa_id'),
                        'required' => false,
                        'permite_vacio' => true,
                        'opcion_vacia' => '— Todas / sin filtro —',
                        'col_label' => 'col-lg-4 control-label text-right pr-2',
                        'col_input' => 'col-lg-8',
                    ])
                    <div class="form-group row">
                        <label for="observaciones" class="col-lg-4 control-label text-right pr-2">Observaciones</label>
                        <div class="col-lg-8">
                            <textarea name="observaciones" id="observaciones" class="form-control" rows="2">{{ old('observaciones') }}</textarea>
                        </div>
                    </div>
                    <div class="form-group row">
                        <div class="col-lg-8 offset-lg-4">
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" class="custom-control-input" name="activo" id="activo" value="1" checked>
                                <label class="custom-control-label" for="activo">Activo</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.sueldos.modalconsultaasociado_reporte')
@endsection
