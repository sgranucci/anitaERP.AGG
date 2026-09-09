{{-- Modal carga masiva SP desde CSV Anita (p-cargasolpm) --}}
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'sp-carga-masiva-overlay',
    'tituloId' => 'sp-carga-masiva-overlay-titulo',
    'subtituloId' => 'sp-carga-masiva-overlay-subtitulo',
    'titulo' => 'Procesando archivo…',
    'subtitulo' => 'Analizando el CSV. No cierre la página.',
])

<div class="modal fade" id="modal-carga-masiva-sp" tabindex="-1" role="dialog"
     aria-labelledby="modal-carga-masiva-sp-titulo" aria-hidden="true"
     data-preview-url="{{ route('preview_carga_masiva_solicitudpago') }}"
     data-confirmar-url="{{ route('confirmar_carga_masiva_solicitudpago') }}"
     data-editar-url-base="{{ url('solicitudpago/solicitudpago') }}">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title" id="modal-carga-masiva-sp-titulo">
                    <i class="fa fa-upload"></i> Carga masiva de solicitudes de pago
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="sp-carga-paso-archivo">
                    <p class="text-muted mb-3">
                        Suba un CSV con el formato Anita (<code>p-cargasolpm</code>):
                        Empresa, Proveedor, Concepto, Sector, Forma de pago, Beneficiario, Moneda,
                        Detalle, Fecha vencimiento, Monto, y pares <strong>Cuenta / Importe</strong>
                        (cuantas columnas haga falta: Haber y Debe).
                        Separador: coma o punto y coma. Codificación UTF-8 o Latin-1 (Excel). Máximo 1000 solicitudes.
                    </p>
                    <div class="alert alert-secondary small py-2">
                        <strong>Asiento:</strong> cada cuenta del CSV se imputa sobre la plantilla del
                        <em>concepto</em> (D/H y centro de costo). Si el encabezado trae “Cuenta Haber / Debe”,
                        también se usa como guía.
                        <br>
                        <strong>Estado al generar:</strong> AUTORIZADA (igual que en Anita).
                        Fecha de entrega = hoy. Sin plan de cuotas ni archivos adjuntos.
                    </div>
                    <div id="sp-carga-archivo-error" class="alert alert-danger d-none"></div>
                    <div class="form-group mb-0">
                        <label class="requerido" for="sp-carga-archivo">Archivo CSV</label>
                        <input type="file" id="sp-carga-archivo" class="form-control" accept=".csv,text/csv,text/plain" required>
                    </div>
                </div>

                <div id="sp-carga-paso-preview" class="d-none">
                    <div class="card border-primary mb-3">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center">
                            <span class="font-weight-bold">
                                <i class="fa fa-table"></i> Vista previa
                            </span>
                            <span id="sp-carga-preview-estado" class="badge badge-secondary">—</span>
                        </div>
                        <div class="card-body py-3">
                            <div id="sp-carga-resumen" class="row mb-3"></div>
                            <div id="sp-carga-por-empresa" class="mb-3"></div>
                            <div id="sp-carga-errores" class="mb-3"></div>

                            <div class="d-flex flex-wrap align-items-center mb-2" style="gap:8px;">
                                <div class="btn-group btn-group-sm" role="group" aria-label="Filtro filas">
                                    <button type="button" class="btn btn-outline-secondary active" data-sp-filtro="todas">Todas</button>
                                    <button type="button" class="btn btn-outline-success" data-sp-filtro="ok">OK</button>
                                    <button type="button" class="btn btn-outline-danger" data-sp-filtro="error">Con error</button>
                                </div>
                                <span class="small text-muted" id="sp-carga-filtro-info"></span>
                            </div>

                            <div class="table-responsive" style="max-height: 420px;">
                                <table class="table table-sm table-bordered table-hover mb-0" id="sp-carga-tabla">
                                    <thead style="background:#85C1E9;color:#17202A;position:sticky;top:0;z-index:1;">
                                        <tr>
                                            <th style="width:36px;"></th>
                                            <th>Línea</th>
                                            <th>Empresa</th>
                                            <th>Proveedor</th>
                                            <th>Concepto</th>
                                            <th>Detalle</th>
                                            <th>Vto</th>
                                            <th class="text-right">Monto</th>
                                            <th class="text-center">Asiento</th>
                                            <th>Estado</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="sp-carga-paso-resultado" class="d-none">
                    <div id="sp-carga-resultado-body"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" id="sp-carga-btn-cancelar">Cancelar</button>
                <button type="button" class="btn btn-outline-secondary d-none" id="sp-carga-btn-volver">
                    <i class="fa fa-arrow-left"></i> Volver
                </button>
                <button type="button" class="btn btn-primary" id="sp-carga-btn-analizar">
                    <i class="fa fa-search"></i> Analizar archivo
                </button>
                <button type="button" class="btn btn-success d-none" id="sp-carga-btn-generar" disabled>
                    <i class="fa fa-check"></i> Generar solicitudes
                </button>
                <button type="button" class="btn btn-primary d-none" id="sp-carga-btn-cerrar">
                    <i class="fa fa-list"></i> Ir al listado
                </button>
            </div>
        </div>
    </div>
</div>
