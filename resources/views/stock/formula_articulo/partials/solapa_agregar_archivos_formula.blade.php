@if (empty($visualizar ?? null))
    @php
        $data = $data ?? null;
        $tieneFormula = isset($data) && $data && ($data->id ?? null);
    @endphp
    <div class="card card-outline card-primary mb-4">
        <div class="card-header py-2">
            <h5 class="card-title mb-0">Agregar archivos nuevos</h5>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-2">
                Seleccione un archivo por renglón o use <strong>+ Agrega renglón</strong> para adjuntar varios.
                Los archivos ya cargados aparecen arriba; puede quitarlos con <strong>Quitar</strong> en cada tarjeta.
            </p>
            <table class="table" id="formula-archivo-table">
                <thead>
                    <tr>
                        <th>Archivo nuevo</th>
                        <th style="width: 90px;"></th>
                    </tr>
                </thead>
                <tbody id="formula-tbody-tabla-archivo">
                    @if (! $tieneFormula)
                        <tr class="item-archivo-formula">
                            <td>
                                <input type="file" name="nombrearchivos[]" class="form-control nombrearchivos-formula">
                            </td>
                            <td>
                                <button type="button" title="Elimina esta línea" class="btn-accion-tabla eliminararchivo-formula tooltipsC">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
            @include('stock.formula_articulo.partials.template_archivos_formula')
            <div class="row">
                <div class="col-md-12">
                    <button id="agrega_renglon_archivo_formula" type="button" class="pull-right btn btn-danger">+ Agrega renglón</button>
                </div>
            </div>
        </div>
    </div>
@endif
