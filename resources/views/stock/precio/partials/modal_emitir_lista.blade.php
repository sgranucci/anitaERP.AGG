<div class="modal fade" id="modal-emitir-lista-vigente" tabindex="-1" role="dialog" aria-labelledby="modalEmitirListaTitulo" aria-hidden="true"
     data-url-pdf="{{ route('listar_precio', ['formato' => 'PDF']) }}"
     data-url-excel="{{ route('listar_precio', ['formato' => 'EXCEL']) }}"
     data-url-csv="{{ route('listar_precio', ['formato' => 'CSV']) }}">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalEmitirListaTitulo">Emitir lista vigente</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body form-horizontal">
                <p class="small text-muted">
                    Genera la lista de precios vigentes a la fecha, con los mismos art&iacute;culos facturables del listado.
                    Un criterio vac&iacute;o no filtra. La salida no cambia la grilla.
                </p>
                <div class="form-group row">
                    <label for="emit_fecha_vigencia" class="col-lg-3 control-label text-right pr-2">Vigente al</label>
                    <div class="col-lg-4">
                        <input type="date" id="emit_fecha_vigencia" class="form-control" value="{{ $fechaVigenciaFiltro ?? date('Y-m-d') }}">
                    </div>
                </div>
                @include('stock.partials.campo_consulta_listaprecio', [
                    'prefix' => 'emitlista',
                    'label' => 'Lista de precios',
                    'layout' => 'form_row',
                    'inputName' => 'listaprecio_id',
                    'inputId' => 'emit_listaprecio_id',
                    'listaprecioId' => '',
                    'codigo' => '',
                    'nombre' => '',
                    'required' => false,
                    'col_label' => 'col-lg-3 control-label text-right pr-2',
                    'col_input' => 'col-lg-9',
                    'siguiente' => '#emit_mventa_id_codigo',
                ])
                @include('stock.partials.campo_consulta_mventa', [
                    'prefix' => 'emitlista',
                    'label' => 'Marca',
                    'inputName' => 'mventa_id',
                    'inputId' => 'emit_mventa_id',
                    'siguiente' => '#emit_categoria_id_codigo',
                    'col_label' => 'col-lg-3 control-label text-right pr-2',
                    'col_input' => 'col-lg-9',
                ])
                @include('stock.partials.campo_consulta_categoria', [
                    'prefix' => 'emitlista',
                    'label' => 'Categoría',
                    'inputName' => 'categoria_id',
                    'inputId' => 'emit_categoria_id',
                    'siguiente' => '#emit_texto',
                    'col_label' => 'col-lg-3 control-label text-right pr-2',
                    'col_input' => 'col-lg-9',
                ])
                <div class="form-group row">
                    <label for="emit_texto" class="col-lg-3 control-label text-right pr-2">Texto</label>
                    <div class="col-lg-9">
                        <input type="text" id="emit_texto" class="form-control" autocomplete="off"
                               placeholder="SKU, descripci&oacute;n o categor&iacute;a. Vac&iacute;o = todos">
                    </div>
                </div>
                <div class="form-group row mb-0">
                    <div class="col-lg-9 offset-lg-3">
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="emit_ocultar_precio_cero" checked>
                            <label class="custom-control-label" for="emit_ocultar_precio_cero">Ocultar precios en 0</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-danger" data-emitir-formato="PDF">
                    <i class="fas fa-file-pdf"></i> PDF
                </button>
                <button type="button" class="btn btn-success" data-emitir-formato="EXCEL">
                    <i class="fas fa-file-excel"></i> Excel
                </button>
                <button type="button" class="btn btn-warning" data-emitir-formato="CSV">
                    <i class="fas fa-file-csv"></i> CSV
                </button>
            </div>
        </div>
    </div>
</div>
