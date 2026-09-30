<div class="modal fade" id="modal-borrar-historial-certsan" tabindex="-1" role="dialog" aria-hidden="true"
     data-url-preview="{{ route('preview_borrar_historial_certificado_sanitario') }}"
     data-url-borrar="{{ route('borrar_historial_certificado_sanitario') }}">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Borrar historial por rango de fechas</h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted small mb-2">
                    Borra los certificados sanitarios cuya fecha está entre las dos fechas, inclusive,
                    con sus líneas y los XML guardados. Queda registrado en auditoría y no se puede deshacer.
                </p>
                <div class="form-row">
                    <div class="form-group col-md-6">
                        <label for="certsan-borrar-fecha-desde">Fecha desde</label>
                        <input type="date" id="certsan-borrar-fecha-desde" class="form-control" required>
                    </div>
                    <div class="form-group col-md-6">
                        <label for="certsan-borrar-fecha-hasta">Fecha hasta</label>
                        <input type="date" id="certsan-borrar-fecha-hasta" class="form-control" required>
                    </div>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="certsan-borrar-preview">
                    <i class="fa fa-search"></i> Ver cuántos hay
                </button>
                <div id="certsan-borrar-error" class="alert alert-danger mt-3 d-none"></div>
                <div id="certsan-borrar-resumen" class="mt-3 d-none">
                    <div class="alert alert-warning mb-2" id="certsan-borrar-resumen-texto"></div>
                    <div class="table-responsive d-none" id="certsan-borrar-muestra-wrap">
                        <table class="table table-sm table-bordered mb-0">
                            <thead style="background:#F5B7B1;color:#17202A;">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Nro</th>
                                </tr>
                            </thead>
                            <tbody id="certsan-borrar-muestra"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="certsan-borrar-confirmar" disabled>
                    <i class="fa fa-trash"></i> Borrar historial
                </button>
            </div>
        </div>
    </div>
</div>
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'certsan-borrar-overlay',
    'tituloId' => 'certsan-borrar-overlay-titulo',
    'subtituloId' => 'certsan-borrar-overlay-subtitulo',
    'titulo' => 'Borrando historial…',
    'subtitulo' => 'Puede demorar según la cantidad. No cierre la página.',
])
