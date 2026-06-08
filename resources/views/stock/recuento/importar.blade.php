@extends("theme.$theme.layout")
@section('titulo')
Importar recuento {{ $recuento->codigo }}
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-8 offset-lg-2">
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-file-excel-o"></i> Importar líneas desde Excel</h3>
                <div class="card-tools">
                    <a href="{{ route('editar_recuento', ['id' => $recuento->id]) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply"></i> Volver al recuento
                    </a>
                </div>
            </div>
            <form action="{{ route('importar_recuento', ['id' => $recuento->id]) }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="card-body">
                    <p class="text-muted">
                        Suba un archivo Excel/CSV con encabezados en la primera fila.
                        Indique el nombre de cada columna según su planilla (no distingue mayúsculas; espacios se convierten en guión bajo).
                    </p>
                    <div class="form-group">
                        <label class="requerido">Archivo</label>
                        <input type="file" name="archivo" class="form-control" accept=".xlsx,.xls,.csv" required>
                    </div>
                    <div class="form-group">
                        <label class="requerido">Columna SKU / código artículo</label>
                        <input type="text" name="col_sku" class="form-control" value="{{ old('col_sku', 'sku') }}" required>
                    </div>
                    <div class="form-group">
                        <label class="requerido">Columna cantidad contada</label>
                        <input type="text" name="col_cantidad" class="form-control" value="{{ old('col_cantidad', 'cantidad_contada') }}" required>
                    </div>
                    <div class="form-group">
                        <label>Columna detalle (opcional)</label>
                        <input type="text" name="col_detalle" class="form-control" value="{{ old('col_detalle', 'detalle') }}">
                    </div>
                    <div class="alert alert-light border small">
                        <strong>Ejemplo de encabezados:</strong> sku, cantidad_contada, detalle<br>
                        Las líneas importadas reemplazarán las actuales al guardar el formulario de edición.
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-upload"></i> Importar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
