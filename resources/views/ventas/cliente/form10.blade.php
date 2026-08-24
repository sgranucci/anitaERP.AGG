<div id="tab-exclusion-percepcion" class="tab-pane fade card form10" role="tabpanel">
    <div class="card-body">
        <p class="text-muted mb-2">
            Al&iacute;cuota que se cobra durante la vigencia (0 = no percibe). IVA no lleva provincia; IIBB s&iacute;.
            Solo aplica en facturaci&oacute;n mostrador, pedidos y remitos; no se env&iacute;a a Anita.
        </p>
        <table class="table table-sm table-bordered" id="exclusion-percepcion-table">
            <thead style="background:#85C1E9;color:#17202A;">
                <tr>
                    <th style="width: 16%;">Tipo</th>
                    <th style="width: 10%;">Provincia</th>
                    <th style="width: 22%;">Nombre provincia</th>
                    <th style="width: 12%;">% cobrado</th>
                    <th style="width: 16%;">Desde</th>
                    <th style="width: 16%;">Hasta</th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="tbody-tabla-exclusion-percepcion">
                @php
                    $tipoexclusion_enum = $tipoexclusion_enum ?? \App\Models\Ventas\Cliente_Exclusion_Percepcion::$enumTipo;
                    $exclusiones = isset($data) ? ($data->cliente_exclusion_percepcions ?? collect()) : collect();
                @endphp
                @if ($exclusiones->count() > 0)
                    @foreach ($exclusiones as $exclusion)
                        <tr class="item-exclusion-percepcion">
                            <td>
                                <input type="hidden" name="exclusion_ids[]" value="{{ $exclusion->id ?? '' }}" />
                                <input type="hidden" name="exclusion_creousuario_ids[]" class="form-control exclusion-creousuario-id" value="{{ $exclusion->creousuario_id ?? auth()->id() }}"/>
                                <select name="exclusion_tipos[]" class="form-control tipoexclusion">
                                    <option value="">-- Tipo --</option>
                                    @foreach ($tipoexclusion_enum as $value => $tipo)
                                        <option value="{{ $value }}"
                                            @if (old('exclusion_tipos.' . $loop->parent->index, optional($exclusion)->tipo) == $value)
                                                selected
                                            @endif
                                        >{{ $tipo }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <div class="form-group row mb-0 exclusion-provincia-grupo">
                                    <input type="hidden" class="provincia_id" name="exclusion_provincia_ids[]" value="{{ $exclusion->provincia_id ?? '' }}" >
                                    <input type="hidden" class="provincia_id_previa" name="exclusion_provincia_id_previa[]" value="{{ $exclusion->provincia_id ?? '' }}" >
                                    <button type="button" title="Consulta provincias (F1)" style="padding:1;" class="btn-accion-tabla consultaprovincia tooltipsC">
                                        <i class="fa fa-search text-primary"></i>
                                    </button>
                                    <input type="text" style="WIDTH: 60px;HEIGHT: 38px" class="codigoprovincia form-control" name="exclusion_codigoprovincias[]" value="{{ optional($exclusion->provincias)->codigo ?? '' }}" >
                                    <input type="hidden" class="codigo_previo_provincia" name="exclusion_codigo_previo_provincias[]" value="{{ optional($exclusion->provincias)->codigo ?? '' }}" >
                                </div>
                            </td>
                            <td>
                                <input type="text" style="WIDTH: 180px; HEIGHT: 38px" class="nombreprovincia form-control" name="exclusion_nombreprovincias[]" value="{{ optional($exclusion->provincias)->nombre ?? '' }}" readonly>
                            </td>
                            <td>
                                <input type="text" inputmode="decimal" class="porcentajeexclusion form-control" name="exclusion_porcentajes[]" value="{{ old('exclusion_porcentajes.' . $loop->index, isset($exclusion->porcentaje) ? number_format((float) $exclusion->porcentaje, 4, '.', '') : '0.0000') }}" autocomplete="off">
                            </td>
                            <td>
                                <input type="date" class="desdefechaexclusion form-control" name="exclusion_desdefechas[]" value="{{ substr(old('exclusion_desdefechas.' . $loop->index, $exclusion->desdefecha ?? ''), 0, 10) }}">
                            </td>
                            <td>
                                <input type="date" class="hastafechaexclusion form-control" name="exclusion_hastafechas[]" value="{{ substr(old('exclusion_hastafechas.' . $loop->index, $exclusion->hastafecha ?? ''), 0, 10) }}">
                            </td>
                            <td>
                                <button type="button" title="Elimina esta linea" class="btn-accion-tabla eliminar_exclusion_percepcion tooltipsC">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                @endif
            </tbody>
        </table>
        @include('ventas.cliente.template10')
        <div class="row">
            <div class="col-md-12">
                <button type="button" id="agrega_renglon_exclusion_percepcion" class="pull-right btn btn-danger">+ Agrega rengl&oacute;n</button>
            </div>
        </div>
    </div>
</div>
