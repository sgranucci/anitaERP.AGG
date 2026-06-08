@if (empty($visualizar ?? null))
    @php $tieneRecuento = isset($data) && $data && ($data->id ?? null); @endphp
    <div class="card card-outline card-primary mb-4">
        <div class="card-header py-2">
            <h5 class="card-title mb-0">Agregar archivos nuevos</h5>
        </div>
        <div class="card-body">
            <table class="table" id="archivo-table">
                <thead>
                    <tr><th>Archivo nuevo</th><th style="width:90px;"></th></tr>
                </thead>
                <tbody id="tbody-tabla-archivo">
                    @if (! $tieneRecuento)
                        <tr class="item-archivo">
                            <td><input type="file" name="nombrearchivos[]" class="form-control nombrearchivos"></td>
                            <td>
                                <button type="button" title="Elimina esta línea" class="btn-accion-tabla eliminararchivo tooltipsC">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
            @include('stock.recuento.partials.template_archivos_recuento')
            <button id="agrega_renglon_archivo" type="button" class="pull-right btn btn-danger btn-sm">+ Agrega renglón</button>
        </div>
    </div>
@endif
