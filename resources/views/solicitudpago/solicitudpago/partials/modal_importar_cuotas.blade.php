{{-- Modal importación cuotas SP (preview + mapeo, estilo novedades sueldos) --}}
@php
    use App\Support\Solicitudpago\SolicitudpagoCuotasImportColumnasSupport;
    $camposMetaCuotas = SolicitudpagoCuotasImportColumnasSupport::metaCampos();
    $prefsImportCuotas = $prefsImportCuotas ?? [];
    $prefsMappingCuotas = $prefsMappingCuotas ?? [];
    $prefCuota = static function (string $campo, mixed $default = null) use ($prefsImportCuotas): mixed {
        return array_key_exists($campo, $prefsImportCuotas) && $prefsImportCuotas[$campo] !== null && $prefsImportCuotas[$campo] !== ''
            ? $prefsImportCuotas[$campo]
            : $default;
    };
    $modoColumnasCuotas = (string) $prefCuota('modo_columnas', SolicitudpagoCuotasImportColumnasSupport::MODO_COLUMNAS_TITULO);
    $csvSepCuotas = (string) $prefCuota('csv_separador', 'auto');
    $filaEncabezadoCuotas = $prefCuota('fila_encabezado', '');
@endphp

@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'sp-cuotas-import-overlay',
    'tituloId' => 'sp-cuotas-import-overlay-titulo',
    'subtituloId' => 'sp-cuotas-import-overlay-subtitulo',
    'titulo' => 'Analizando archivo…',
    'subtitulo' => 'Leyendo cuotas. No cierre la página.',
])

<div class="modal fade" id="modal-importar-cuotas-sp" tabindex="-1" role="dialog"
     aria-labelledby="modal-importar-cuotas-sp-titulo" aria-hidden="true"
     data-preview-url="{{ route('preview_importar_cuotas_solicitudpago') }}"
     data-plantilla-url="{{ route('plantilla_importar_cuotas_solicitudpago') }}">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="modal-importar-cuotas-sp-titulo">
                    <i class="fa fa-upload"></i> Importar cuotas (Excel / CSV)
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="form-importar-cuotas-sp" class="mb-0" autocomplete="off" onsubmit="return false;">
                @csrf
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Indicá si el archivo trae fila de títulos. Si usa otros nombres, corregí el mapeo en la vista previa.
                        Se aplican a la grilla solo las filas en verde; después guardá la solicitud.
                        La configuración (modo, separador, mapeo) se recuerda por usuario.
                    </p>

                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2 requerido">Columnas</label>
                        <div class="col-lg-9">
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" id="sp_cuotas_modo_titulo" name="modo_columnas" value="titulo"
                                       class="custom-control-input js-sp-cuotas-modo-columnas"
                                       @checked($modoColumnasCuotas === SolicitudpagoCuotasImportColumnasSupport::MODO_COLUMNAS_TITULO)>
                                <label class="custom-control-label" for="sp_cuotas_modo_titulo">Por título de encabezado</label>
                            </div>
                            <div class="custom-control custom-radio custom-control-inline">
                                <input type="radio" id="sp_cuotas_modo_numero" name="modo_columnas" value="numero"
                                       class="custom-control-input js-sp-cuotas-modo-columnas"
                                       @checked($modoColumnasCuotas === SolicitudpagoCuotasImportColumnasSupport::MODO_COLUMNAS_NUMERO)>
                                <label class="custom-control-label" for="sp_cuotas_modo_numero">Por número de columna (sin título)</label>
                            </div>
                            <div class="text-muted small mt-1" id="sp-cuotas-ayuda-modo-columnas">
                                Con título: se detecta la fila de encabezados. Sin título: datos desde la primera fila; mapeá Vencimiento / Monto a Columna 1, 2…
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="sp_cuotas_fila_encabezado" id="sp-cuotas-label-fila-encabezado" class="col-lg-3 control-label text-right pr-2">Fila del encabezado</label>
                        <div class="col-lg-2">
                            <input type="number" name="fila_encabezado" id="sp_cuotas_fila_encabezado" class="form-control form-control-sm"
                                   min="1" max="50" value="{{ $filaEncabezadoCuotas }}" placeholder="Auto">
                        </div>
                        <div class="col-lg-5 col-form-label text-muted small" id="sp-cuotas-ayuda-fila-encabezado">Vacío = detectar automáticamente</div>
                    </div>

                    <div class="form-group row">
                        <label for="sp_cuotas_archivo" class="col-lg-3 control-label text-right pr-2 requerido">Archivo</label>
                        <div class="col-lg-5">
                            <input type="file" name="archivo" id="sp_cuotas_archivo" class="form-control form-control-sm"
                                   accept=".xlsx,.xls,.csv,.txt" required>
                        </div>
                        <div class="col-lg-4">
                            <button type="button" id="btn-preview-import-cuotas-sp" class="btn btn-outline-primary btn-sm" disabled>
                                <i class="fa fa-search"></i> Vista previa
                            </button>
                            <a href="{{ route('plantilla_importar_cuotas_solicitudpago') }}" class="btn btn-outline-secondary btn-sm ml-1">
                                <i class="fa fa-download"></i> Plantilla
                            </a>
                        </div>
                    </div>

                    <div class="form-group row" id="sp-cuotas-panel-csv-separador">
                        <label for="sp_cuotas_csv_separador" class="col-lg-3 control-label text-right pr-2">Separador CSV</label>
                        <div class="col-lg-3">
                            <select name="csv_separador" id="sp_cuotas_csv_separador" class="form-control form-control-sm">
                                <option value="auto" @selected($csvSepCuotas === 'auto' || $csvSepCuotas === '')>Automático (detectar)</option>
                                <option value="," @selected($csvSepCuotas === ',')>Coma (,)</option>
                                <option value=";" @selected($csvSepCuotas === ';')>Punto y coma (;)</option>
                                <option value="tab" @selected($csvSepCuotas === 'tab')>Tabulación</option>
                                <option value="|" @selected($csvSepCuotas === '|')>Pipe (|)</option>
                            </select>
                        </div>
                        <div class="col-lg-6 col-form-label text-muted small" id="sp-cuotas-ayuda-csv-separador">
                            Para CSV/TXT. Con «Automático» se detecta por el contenido. En Excel se ignora.
                        </div>
                    </div>

                    <input type="hidden" name="hoja_indice" id="sp_cuotas_hoja_indice" value="1">
                    <div class="form-group row d-none" id="sp-cuotas-panel-hoja-excel">
                        <label for="sp_cuotas_hoja_indice_select" class="col-lg-3 control-label text-right pr-2">Hoja</label>
                        <div class="col-lg-4">
                            <select id="sp_cuotas_hoja_indice_select" class="form-control form-control-sm"></select>
                        </div>
                    </div>

                    <div class="border rounded p-2 mb-3 bg-light" id="sp-cuotas-panel-mapeo-columnas">
                        <div class="small font-weight-bold mb-1">Mapeo de columnas</div>
                        <div class="text-muted small mb-2" id="sp-cuotas-ayuda-mapeo-columnas">
                            Con título: nombre del encabezado (ej. <code>vencimiento</code>). Sin título: número (1 = primera) o letra Excel (A, B…).
                        </div>
                        <div class="row">
                            @foreach ($camposMetaCuotas as $campo => $meta)
                                <div class="col-md-4 mb-2">
                                    <label class="small mb-0" for="sp_map_cuota_{{ $campo }}">
                                        {{ $meta['label'] }}
                                        @if ($meta['requerido'])
                                            <span class="text-danger">*</span>
                                        @endif
                                    </label>
                                    <input type="text" name="mapping[{{ $campo }}]" id="sp_map_cuota_{{ $campo }}"
                                           class="form-control form-control-sm sp-cuotas-map-campo" data-campo="{{ $campo }}"
                                           value="{{ $prefsMappingCuotas[$campo] ?? '' }}"
                                           autocomplete="off" placeholder="—">
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div id="sp-cuotas-panel-preview" class="card border-primary mb-0" style="display:none;">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center">
                            <strong><i class="fa fa-table"></i> Vista previa</strong>
                            <span id="sp-cuotas-preview-estado" class="badge badge-secondary">—</span>
                        </div>
                        <div class="card-body p-2" id="sp-cuotas-preview-contenido">
                            <p class="text-muted small mb-0">Seleccioná un archivo.</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-success" id="btn-aplicar-import-cuotas-sp" disabled
                            title="Primero analizá el archivo con Vista previa.">
                        <i class="fa fa-check"></i> Aplicar a la grilla
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
