@php
    $tieneProveedor = isset($data) && $data && ($data->id ?? null);
    $docsFiscales = $tieneProveedor
        ? ($data->proveedor_documentos_fiscales ?? collect())
        : collect();
    $mostrarCm05Jurisdicciones = !empty($mostrar_solapa_salas);
    $cm05Filas = collect();
    if ($mostrarCm05Jurisdicciones) {
        if (old('cm05_provincia_ids') !== null) {
            $oldIds = (array) old('cm05_provincia_ids', []);
            $oldCodigos = (array) old('cm05_codigoprovincias', []);
            $oldNombres = (array) old('cm05_nombreprovincias', []);
            $oldFechas = (array) old('cm05_fechavigencias', []);
            foreach ($oldIds as $i => $pid) {
                if ((int) $pid <= 0 && trim((string) ($oldCodigos[$i] ?? '')) === '') {
                    continue;
                }
                $cm05Filas->push((object) [
                    'provincia_id' => $pid,
                    'codigo' => $oldCodigos[$i] ?? '',
                    'nombre' => $oldNombres[$i] ?? '',
                    'fechavigencia' => $oldFechas[$i] ?? '',
                ]);
            }
        } elseif ($tieneProveedor) {
            $cm05Filas = ($data->proveedor_cm05s ?? collect())->map(function ($fila) {
                return (object) [
                    'provincia_id' => $fila->provincia_id,
                    'codigo' => optional($fila->provincias)->codigo ?? '',
                    'nombre' => optional($fila->provincias)->nombre ?? '',
                    'fechavigencia' => optional($fila->fechavigencia)->format('Y-m-d') ?? '',
                ];
            });
        }
    }
@endphp
<div id="tab6" class="card form6 tab-content" style="display: none">
    <div class="card-body">
        @if ($mostrarCm05Jurisdicciones)
            <div class="card card-outline card-info mb-3">
                <div class="card-header py-2">
                    <h3 class="card-title mb-0">Jurisdicciones CM05</h3>
                </div>
                <div class="card-body">
                    <p class="text-muted small mb-3">
                        Provincias donde el proveedor est&aacute; inscripto (convenio multilateral).
                        Si incluye <strong>R&iacute;o Negro</strong>, la retenci&oacute;n IIBB usa el 1&nbsp;% de Convenio/Local;
                        sin RN aplica el % fuera de jurisdicci&oacute;n.
                    </p>
                    <input type="hidden" name="cm05_presentes" value="1">
                    <table class="table table-sm table-bordered" id="proveedor-cm05-table">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th style="width: 28%;">Provincia</th>
                                <th>Nombre</th>
                                <th style="width: 18%;">Vigencia desde</th>
                                <th style="width: 70px;"></th>
                            </tr>
                        </thead>
                        <tbody id="tbody-proveedor-cm05">
                            @foreach ($cm05Filas as $cm05)
                                <tr class="item-proveedor-cm05">
                                    <td>
                                        <div class="d-flex flex-nowrap align-items-center" style="gap:2px;">
                                            <input type="hidden" class="provincia_id" name="cm05_provincia_ids[]"
                                                   value="{{ $cm05->provincia_id }}">
                                            <button type="button" title="Consulta provincias (F1)"
                                                    class="btn-accion-tabla consultaprovincia tooltipsC flex-shrink-0">
                                                <i class="fa fa-search text-primary"></i>
                                            </button>
                                            <input type="text" class="codigoprovincia form-control form-control-sm"
                                                   name="cm05_codigoprovincias[]"
                                                   value="{{ $cm05->codigo }}"
                                                   style="width:70px;" autocomplete="off">
                                        </div>
                                    </td>
                                    <td>
                                        <input type="text" class="nombreprovincia form-control form-control-sm"
                                               name="cm05_nombreprovincias[]"
                                               value="{{ $cm05->nombre }}" readonly>
                                    </td>
                                    <td>
                                        <input type="date" class="form-control form-control-sm"
                                               name="cm05_fechavigencias[]"
                                               value="{{ $cm05->fechavigencia }}">
                                    </td>
                                    <td class="text-center">
                                        <button type="button" title="Elimina esta linea"
                                                class="btn-accion-tabla eliminar_proveedor_cm05 tooltipsC">
                                            <i class="fa fa-times-circle text-danger"></i>
                                        </button>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <button type="button" id="agrega_renglon_proveedor_cm05" class="btn btn-outline-primary btn-sm">
                        + Agrega jurisdicci&oacute;n
                    </button>
                    <template id="proveedor-template-renglon-cm05">
                        <tr class="item-proveedor-cm05">
                            <td>
                                <div class="d-flex flex-nowrap align-items-center" style="gap:2px;">
                                    <input type="hidden" class="provincia_id" name="cm05_provincia_ids[]" value="">
                                    <button type="button" title="Consulta provincias (F1)"
                                            class="btn-accion-tabla consultaprovincia tooltipsC flex-shrink-0">
                                        <i class="fa fa-search text-primary"></i>
                                    </button>
                                    <input type="text" class="codigoprovincia form-control form-control-sm"
                                           name="cm05_codigoprovincias[]" value=""
                                           style="width:70px;" autocomplete="off">
                                </div>
                            </td>
                            <td>
                                <input type="text" class="nombreprovincia form-control form-control-sm"
                                       name="cm05_nombreprovincias[]" value="" readonly>
                            </td>
                            <td>
                                <input type="date" class="form-control form-control-sm"
                                       name="cm05_fechavigencias[]" value="">
                            </td>
                            <td class="text-center">
                                <button type="button" title="Elimina esta linea"
                                        class="btn-accion-tabla eliminar_proveedor_cm05 tooltipsC">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                </div>
            </div>
        @endif

        <p class="text-muted small mb-3">
            Constancia de CUIT y CM05 anual. Los archivos presentados desde el
            <strong>Portal de proveedores</strong> aparecen aquí automáticamente.
        </p>

        @if ($tieneProveedor)
            <div class="card card-outline card-info mb-3">
                <div class="card-header py-2">
                    <h3 class="card-title mb-0">Documentos actuales</h3>
                </div>
                <div class="card-body">
                    @if ($docsFiscales->count())
                        <div class="row">
                            @foreach ($docsFiscales as $doc)
                                @php
                                    $est = method_exists($doc, 'estadoVigencia') ? $doc->estadoVigencia() : 'sin_fecha';
                                    $url = method_exists($doc, 'urlArchivo')
                                        ? $doc->urlArchivo()
                                        : \App\Support\Archivos\ArchivoAdjuntoCacheSupport::urlStoragePublico(
                                            'archivos/proveedores/'.$data->id.'/fiscal/'.$doc->nombrearchivo
                                        );
                                    $badge = match ($est) {
                                        'vigente' => 'badge-success',
                                        'proximo' => 'badge-warning',
                                        'vencido' => 'badge-danger',
                                        default => 'badge-secondary',
                                    };
                                @endphp
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card card-outline card-secondary h-100 mb-0">
                                        <div class="card-body p-2 d-flex flex-column">
                                            <div class="d-flex justify-content-between align-items-start mb-1">
                                                <strong>{{ method_exists($doc, 'etiquetaTipo') ? $doc->etiquetaTipo() : $doc->tipo }}</strong>
                                                <span class="badge {{ $badge }}">{{ strtoupper($est) }}</span>
                                            </div>
                                            <div class="small text-muted mb-1">
                                                Vence:
                                                {{ optional($doc->fecha_vencimiento)->format('d/m/Y') ?: '—' }}
                                                @if ($doc->anio_ejercicio)
                                                    · Año {{ $doc->anio_ejercicio }}
                                                @endif
                                                · {{ $doc->origen }}
                                            </div>
                                            <div class="small text-truncate mb-2" title="{{ $doc->nombrearchivo }}">
                                                {{ $doc->nombrearchivo }}
                                            </div>
                                            <div class="mt-auto">
                                                <a href="{{ $url }}" class="btn btn-sm btn-outline-primary" target="_blank" rel="noopener">
                                                    <i class="fa fa-external-link"></i> Abrir
                                                </a>
                                                <a href="{{ $url }}" class="btn btn-sm btn-outline-secondary" download>
                                                    <i class="fa fa-download"></i> Descargar
                                                </a>
                                            </div>
                                            <input type="hidden" name="documento_fiscal_ids_existentes[]" value="{{ $doc->id }}">
                                            <button type="button"
                                                    class="btn btn-sm btn-outline-danger mt-2 eliminar-documento-fiscal-proveedor"
                                                    title="Quitar; se elimina al guardar el proveedor">
                                                <i class="fa fa-times"></i> Quitar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-muted mb-0">No hay CUIT / CM05 cargados.</p>
                    @endif
                </div>
            </div>
        @endif

        <div class="card card-outline card-primary mb-0">
            <div class="card-header py-2">
                <h3 class="card-title mb-0">Agregar documento fiscal</h3>
            </div>
            <div class="card-body">
                @if (! $tieneProveedor)
                    <p class="text-muted mb-0">Guarde el proveedor primero para adjuntar CUIT / CM05.</p>
                @else
                    <table class="table table-sm table-bordered" id="documento-fiscal-table">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Tipo</th>
                                <th>Vencimiento</th>
                                <th>Año CM05</th>
                                <th>Archivo</th>
                                <th style="width:70px;"></th>
                            </tr>
                        </thead>
                        <tbody id="tbody-tabla-documento-fiscal">
                            <tr class="item-documento-fiscal">
                                <td>
                                    <select name="documento_fiscal_tipos[]" class="form-control form-control-sm">
                                        <option value="CUIT">Constancia CUIT</option>
                                        <option value="CM05">CM05 anual</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="date" name="documento_fiscal_vencimientos[]" class="form-control form-control-sm">
                                </td>
                                <td>
                                    <input type="number" name="documento_fiscal_anios[]" class="form-control form-control-sm"
                                           min="2000" max="2100" placeholder="Año">
                                </td>
                                <td>
                                    <input type="file" name="documento_fiscal_archivos[]" class="form-control form-control-sm"
                                           accept=".pdf,.jpg,.jpeg,.png">
                                </td>
                                <td>
                                    <button type="button" class="btn-accion-tabla eliminar-renglon-documento-fiscal tooltipsC" title="Quitar renglón">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <button type="button" id="agrega_renglon_documento_fiscal" class="btn btn-outline-primary btn-sm">
                        + Agrega renglón
                    </button>
                    <template id="proveedor-template-renglon-documento-fiscal">
                        <tr class="item-documento-fiscal">
                            <td>
                                <select name="documento_fiscal_tipos[]" class="form-control form-control-sm">
                                    <option value="CUIT">Constancia CUIT</option>
                                    <option value="CM05">CM05 anual</option>
                                </select>
                            </td>
                            <td>
                                <input type="date" name="documento_fiscal_vencimientos[]" class="form-control form-control-sm">
                            </td>
                            <td>
                                <input type="number" name="documento_fiscal_anios[]" class="form-control form-control-sm"
                                       min="2000" max="2100" placeholder="Año">
                            </td>
                            <td>
                                <input type="file" name="documento_fiscal_archivos[]" class="form-control form-control-sm"
                                       accept=".pdf,.jpg,.jpeg,.png">
                            </td>
                            <td>
                                <button type="button" class="btn-accion-tabla eliminar-renglon-documento-fiscal tooltipsC" title="Quitar renglón">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            </td>
                        </tr>
                    </template>
                @endif
            </div>
        </div>
    </div>
</div>
