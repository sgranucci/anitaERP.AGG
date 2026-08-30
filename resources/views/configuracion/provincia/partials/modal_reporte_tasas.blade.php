<div class="modal fade" id="modal-reporte-tasas-iibb" tabindex="-1" role="dialog"
     aria-labelledby="modal-reporte-tasas-iibb-titulo" aria-hidden="true"
     data-preview-url="{{ route('preview_provincia_tasas_iibb') }}">
    <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document" style="max-width: 96%;">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title" id="modal-reporte-tasas-iibb-titulo">
                    Tasas y m&iacute;nimos IIBB por provincia
                </h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted mb-3">
                    Control de al&iacute;cuotas y m&iacute;nimos cargados en el ABM.
                    Solo jurisdicciones con tasas. Las tasas son patrimonio de cada fisco;
                    Percibe / Retiene indica qu&eacute; empresa es agente.
                </p>
                <div class="mb-3">
                    <button type="button" class="btn btn-primary btn-sm" id="btn-consultar-tasas-iibb">
                        <i class="fa fa-search"></i> Consultar
                    </button>
                </div>
                <div id="reporte-tasas-iibb-error" class="alert alert-danger d-none" role="alert"></div>
                <div id="reporte-tasas-iibb-cargando" class="text-center text-muted py-4 d-none">
                    <i class="fa fa-spinner fa-spin"></i> Generando vista previa…
                </div>
                <div id="reporte-tasas-iibb-resultado"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
