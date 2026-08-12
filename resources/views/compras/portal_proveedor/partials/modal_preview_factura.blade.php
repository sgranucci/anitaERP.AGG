<div class="modal fade" id="portal-preview-factura-modal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header py-2" style="background:#85C1E9;color:#17202A;">
                <h5 class="modal-title" id="portal-preview-factura-titulo">Vista previa de factura</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-0">
                <iframe id="portal-preview-factura-frame" class="portal-preview-frame" title="Vista previa factura"></iframe>
            </div>
            <div class="modal-footer py-2">
                <a id="portal-preview-factura-abrir" href="#" class="btn btn-outline-primary btn-sm" target="_blank" rel="noopener">
                    <i class="fa fa-external-link"></i> Abrir en solapa
                </a>
                <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<script>
(function () {
    function bindPreview() {
        document.querySelectorAll('.portal-preview-factura').forEach(function (btn) {
            if (btn.dataset.boundPreview === '1') {
                return;
            }
            btn.dataset.boundPreview = '1';
            btn.addEventListener('click', function () {
                var url = btn.getAttribute('data-preview-url') || '';
                var titulo = btn.getAttribute('data-preview-titulo') || 'Vista previa de factura';
                var frame = document.getElementById('portal-preview-factura-frame');
                var titleEl = document.getElementById('portal-preview-factura-titulo');
                var openEl = document.getElementById('portal-preview-factura-abrir');
                if (!frame || !url) {
                    return;
                }
                if (titleEl) {
                    titleEl.textContent = titulo;
                }
                if (openEl) {
                    openEl.href = url;
                }
                frame.src = url;
                if (window.jQuery) {
                    window.jQuery('#portal-preview-factura-modal').modal('show');
                }
            });
        });
        if (window.jQuery) {
            window.jQuery('#portal-preview-factura-modal').on('hidden.bs.modal', function () {
                var frame = document.getElementById('portal-preview-factura-frame');
                if (frame) {
                    frame.src = 'about:blank';
                }
            });
        }
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bindPreview);
    } else {
        bindPreview();
    }
})();
</script>
