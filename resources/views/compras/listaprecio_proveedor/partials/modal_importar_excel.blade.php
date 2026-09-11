@php
    $filtrosQuery = $filtrosQuery ?? [];
    $importAction = route('importar_excel_listaprecio_proveedor', ['id' => $data->id] + $filtrosQuery);
@endphp
<div class="modal fade" id="modal-importar-excel-lp" tabindex="-1" role="dialog" aria-labelledby="modal-importar-excel-lp-titulo" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form action="{{ $importAction }}" method="POST" enctype="multipart/form-data" id="form-importar-excel-lp">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title" id="modal-importar-excel-lp-titulo">
                        <i class="fa fa-file-excel-o text-success"></i> Importar precios desde Excel
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-3">
                        Columnas: <strong>A</strong> = SKU art&iacute;culo, <strong>B</strong> = precio,
                        <strong>C</strong> = % descuento (opcional), <strong>D</strong> = c&oacute;digo art&iacute;culo proveedor (opcional).
                        La primera fila puede ser encabezado (SKU, &hellip;).
                    </p>
                    <div class="form-group">
                        <label for="fechavigencia_import" class="control-label">Fecha de vigencia de los precios importados</label>
                        <input type="date" name="fechavigencia" id="fechavigencia_import" class="form-control" required value="{{ date('Y-m-d') }}">
                    </div>
                    <div class="form-group mb-0">
                        <label for="archivoexcel" class="control-label">Archivo (.xlsx, .xls, .csv)</label>
                        <input type="file" name="archivoexcel" id="archivoexcel" class="form-control" accept=".xlsx,.xls,.csv" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fa fa-upload"></i> Importar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
