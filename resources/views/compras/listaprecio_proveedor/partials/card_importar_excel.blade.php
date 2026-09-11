@php
    $visualizar = ! empty($visualizar);
    $esAlta = ! isset($data) || ! $data || empty($data->id);
    $puedeImportarAlta = $esAlta && ! $visualizar && can('crear-listaprecio-proveedor', false);
    $puedeImportarEdicion = ! $esAlta && ! $visualizar && can('actualizar-listaprecio-proveedor', false);
@endphp
@if ($puedeImportarAlta || $puedeImportarEdicion)
<div class="card card-outline card-success mb-3" id="lp-card-importar-excel">
    <div class="card-header py-2">
        <h3 class="card-title mb-0"><i class="fa fa-file-excel-o"></i> Importar precios desde Excel</h3>
    </div>
    <div class="card-body">
        <p class="small text-muted mb-3">
            Columnas: <strong>A</strong> = SKU art&iacute;culo, <strong>B</strong> = precio,
            <strong>C</strong> = % descuento (opcional), <strong>D</strong> = c&oacute;digo art&iacute;culo proveedor (opcional).
            La primera fila puede ser encabezado.
        </p>
        @if ($puedeImportarAlta)
            <p class="small mb-3">
                Elija el archivo ac&aacute; y pulse <strong>Guardar</strong>: los renglones se cargan junto con el alta de la lista.
            </p>
            <div class="form-row">
                <div class="form-group col-md-4 mb-2">
                    <label for="fechavigencia_excel" class="control-label">Fecha de vigencia</label>
                    <input type="date" name="fechavigencia_excel" id="fechavigencia_excel" class="form-control"
                           value="{{ old('fechavigencia_excel', date('Y-m-d')) }}">
                </div>
                <div class="form-group col-md-8 mb-2">
                    <label for="archivoexcel" class="control-label">Archivo (.xlsx, .xls, .csv)</label>
                    <input type="file" name="archivoexcel" id="archivoexcel" class="form-control" accept=".xlsx,.xls,.csv">
                </div>
            </div>
        @else
            <button type="button" class="btn btn-success" id="lp-btn-abrir-import-excel">
                <i class="fa fa-file-excel-o"></i> Elegir archivo e importar
            </button>
        @endif
    </div>
</div>
@endif
