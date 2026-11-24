{{-- Aviso El Bierzo: cliente sin «Emite NC». Abrir ABM en solapa sin salir del facturador. --}}
@once('anita-modal-aviso-cliente-emite-nc')
<div class="modal fade" id="modalAvisoClienteEmiteNc" tabindex="-1" role="dialog"
    aria-labelledby="modalAvisoClienteEmiteNcTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title" id="modalAvisoClienteEmiteNcTitulo">
          <i class="fa fa-exclamation-triangle"></i> Nota de cr&eacute;dito
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p class="mb-2" id="modal-aviso-emite-nc-mensaje"></p>
        <p class="mb-0 text-muted small" id="modal-aviso-emite-nc-cliente"></p>
        <p class="mt-2 mb-0 small text-muted d-none" id="modal-aviso-emite-nc-ayuda-abm">
          Abr&iacute; la ficha, habilit&aacute; <strong>Emite NC</strong>, guard&aacute; y cerr&aacute; la solapa.
          Despu&eacute;s volv&eacute; a cargar el cliente en el facturador.
        </p>
      </div>
      <div class="modal-footer">
        <a href="#" id="modal-aviso-emite-nc-abrir" class="btn btn-info d-none" target="_blank" rel="noopener">
          <i class="fa fa-edit"></i> Abrir ficha del cliente
        </a>
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>
@endonce
