@extends("theme.$theme.layout")
@section('titulo')
    Importar novedades
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Importar novedades (Excel / CSV)</h3>
                <div class="card-tools">
                    <a href="{{route('consultar_novedad_sueldos')}}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('procesar_importar_novedad_sueldos') }}" method="POST" enctype="multipart/form-data" class="form-horizontal">
                @csrf
                <div class="card-body">
                    <p class="text-muted">
                        Columnas esperadas (primera fila = encabezados):
                        <code>empresa_id</code>, <code>legajo</code>, <code>concepto_codigo</code>,
                        <code>liquidacion_numero</code> (opc.), <code>valor1</code>, <code>valor2</code>,
                        <code>estado</code>, <code>fecha_vto</code>, <code>fecha_desde</code>, <code>fecha_hasta</code>,
                        <code>nro_interno</code>, <code>periodo</code>, <code>observacion</code>.
                        Con <code>fecha_desde</code> la novedad se repite en cada corrida mientras esté vigente.
                    </p>
                    <div class="form-group row">
                        <label for="archivo" class="col-lg-3 col-form-label requerido">Archivo</label>
                        <div class="col-lg-6">
                            <input type="file" name="archivo" id="archivo" class="form-control" accept=".xlsx,.xls,.csv,.txt" required>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-upload"></i> Importar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
