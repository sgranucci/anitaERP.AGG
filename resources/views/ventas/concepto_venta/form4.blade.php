@php
    use App\Support\Ventas\ConceptoVentaPlantillaMotor;

    $tagsRegimen = [];
    if (old('tag_claves') !== null) {
        foreach (old('tag_claves', []) as $i => $clave) {
            $tagsRegimen[] = (object) [
                'clave' => $clave,
                'etiqueta' => old('tag_etiquetas.'.$i, ''),
                'tipo' => old('tag_tipos.'.$i, 'texto'),
                'origen' => old('tag_origenes.'.$i, ConceptoVentaPlantillaMotor::ORIGEN_PEDIBLE),
                'obligatorio' => (bool) old('tag_obligatorios.'.$i, true),
                'orden' => old('tag_ordenes.'.$i, $i + 1),
                'largo_max' => old('tag_largo_max.'.$i),
                'opciones' => old('tag_opciones.'.$i),
            ];
        }
    } elseif (isset($data) && $data->relationLoaded('tags')) {
        foreach ($data->tags as $tag) {
            $tagsRegimen[] = (object) [
                'clave' => $tag->clave,
                'etiqueta' => $tag->etiqueta,
                'tipo' => $tag->tipo,
                'origen' => $tag->origen ?? ConceptoVentaPlantillaMotor::ORIGEN_PEDIBLE,
                'obligatorio' => (bool) $tag->obligatorio,
                'orden' => $tag->orden,
                'largo_max' => $tag->largo_max,
                'opciones' => $tag->opciones,
            ];
        }
    }
@endphp
<div class="card card-outline card-info mt-3">
    <div class="card-header">
        <h3 class="card-title">Tags de la plantilla</h3>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-2">
            Cada <code>@clave@</code> de la descripción ARCA debe estar en esta grilla.
            Origen <strong>sistema</strong> se completa al facturar; <strong>pedible</strong> se pide en modal o abono.
            Opciones (tipo lista): valores separados por <code>|</code>.
        </p>
        <div class="table-responsive">
            <table class="table table-sm table-bordered" id="cv-tag-table">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width:14%;">Clave</th>
                        <th style="width:16%;">Etiqueta</th>
                        <th style="width:10%;">Tipo</th>
                        <th style="width:10%;">Origen</th>
                        <th style="width:8%;">Oblig.</th>
                        <th style="width:7%;">Orden</th>
                        <th style="width:8%;">Largo</th>
                        <th>Opciones</th>
                        <th class="width80">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tbody-cv-tag-table">
                    @foreach ($tagsRegimen as $tag)
                        <tr class="item-cv-tag">
                            <td>
                                <input type="text" name="tag_claves[]" class="form-control form-control-sm cv-tag-clave"
                                    maxlength="40" value="{{ $tag->clave ?? '' }}" placeholder="periodo">
                            </td>
                            <td>
                                <input type="text" name="tag_etiquetas[]" class="form-control form-control-sm"
                                    maxlength="80" value="{{ $tag->etiqueta ?? '' }}" placeholder="Período a facturar">
                            </td>
                            <td>
                                <select name="tag_tipos[]" class="form-control form-control-sm">
                                    <option value="texto" @selected(($tag->tipo ?? 'texto') === 'texto')>Texto</option>
                                    <option value="fecha" @selected(($tag->tipo ?? '') === 'fecha')>Fecha</option>
                                    <option value="periodo" @selected(($tag->tipo ?? '') === 'periodo')>Período</option>
                                    <option value="lista" @selected(($tag->tipo ?? '') === 'lista')>Lista</option>
                                </select>
                            </td>
                            <td>
                                <select name="tag_origenes[]" class="form-control form-control-sm">
                                    <option value="pedible" @selected(($tag->origen ?? 'pedible') === 'pedible')>Pedible</option>
                                    <option value="sistema" @selected(($tag->origen ?? '') === 'sistema')>Sistema</option>
                                </select>
                            </td>
                            <td class="text-center align-middle">
                                <input type="hidden" name="tag_obligatorios[]" value="0" class="cv-tag-obligatorio-hidden">
                                <input type="checkbox" class="cv-tag-obligatorio" value="1"
                                    {{ ($tag->obligatorio ?? true) ? 'checked' : '' }}>
                            </td>
                            <td>
                                <input type="number" name="tag_ordenes[]" class="form-control form-control-sm" min="1" max="999"
                                    value="{{ $tag->orden ?? 1 }}">
                            </td>
                            <td>
                                <input type="number" name="tag_largo_max[]" class="form-control form-control-sm" min="1" max="255"
                                    value="{{ $tag->largo_max ?? '' }}" placeholder="—">
                            </td>
                            <td>
                                <input type="text" name="tag_opciones[]" class="form-control form-control-sm"
                                    maxlength="255" value="{{ $tag->opciones ?? '' }}" placeholder="a|b|c">
                            </td>
                            <td>
                                <button type="button" title="Elimina esta línea" class="btn-accion-tabla eliminar_cv_tag tooltipsC">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <template id="cv-template-renglon-tag">
            <tr class="item-cv-tag">
                <td>
                    <input type="text" name="tag_claves[]" class="form-control form-control-sm cv-tag-clave"
                        maxlength="40" value="" placeholder="periodo">
                </td>
                <td>
                    <input type="text" name="tag_etiquetas[]" class="form-control form-control-sm"
                        maxlength="80" value="" placeholder="Período a facturar">
                </td>
                <td>
                    <select name="tag_tipos[]" class="form-control form-control-sm">
                        <option value="texto" selected>Texto</option>
                        <option value="fecha">Fecha</option>
                        <option value="periodo">Período</option>
                        <option value="lista">Lista</option>
                    </select>
                </td>
                <td>
                    <select name="tag_origenes[]" class="form-control form-control-sm">
                        <option value="pedible" selected>Pedible</option>
                        <option value="sistema">Sistema</option>
                    </select>
                </td>
                <td class="text-center align-middle">
                    <input type="hidden" name="tag_obligatorios[]" value="1" class="cv-tag-obligatorio-hidden">
                    <input type="checkbox" class="cv-tag-obligatorio" value="1" checked>
                </td>
                <td>
                    <input type="number" name="tag_ordenes[]" class="form-control form-control-sm" min="1" max="999" value="1">
                </td>
                <td>
                    <input type="number" name="tag_largo_max[]" class="form-control form-control-sm" min="1" max="255"
                        value="" placeholder="—">
                </td>
                <td>
                    <input type="text" name="tag_opciones[]" class="form-control form-control-sm"
                        maxlength="255" value="" placeholder="a|b|c">
                </td>
                <td>
                    <button type="button" title="Elimina esta línea" class="btn-accion-tabla eliminar_cv_tag tooltipsC">
                        <i class="fa fa-times-circle text-danger"></i>
                    </button>
                </td>
            </tr>
        </template>
        <button type="button" id="cv-agrega_renglon_tag" class="btn btn-outline-primary btn-sm">
            + Agrega tag
        </button>
    </div>
</div>
