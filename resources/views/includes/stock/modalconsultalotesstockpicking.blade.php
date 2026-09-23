@once('anita-modal-consulta-lotes-stock-picking')
<div class="modal fade" id="consultalotesstockpickingModal" role="dialog" aria-labelledby="consultalotesstockpickingModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="consultalotesstockpickingModalLabel">M&oacute;dulos / lotes de stock pendientes</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <p class="small text-muted mb-2">
          <i class="fa fa-info-circle"></i>
          Eleg&iacute; lote <strong>y</strong> dep&oacute;sito con saldo. Al Preparar se descuenta el stock (queda atrapado hasta facturar o quitar).
        </p>
        <div class="form-group row mb-2">
          <label for="consultalotesstockpicking" class="col-form-label col-auto pr-2">Buscar:</label>
          <div class="col">
            <input type="text" name="consultalotesstockpicking" id="consultalotesstockpicking" class="form-control form-control-sm" autocomplete="off" placeholder="Lote / OT / m&oacute;dulo">
          </div>
          <div class="col-auto">
            <div class="custom-control custom-checkbox mt-1">
              <input type="checkbox" class="custom-control-input" id="consultalotesstockpicking_solo_modulo" checked>
              <label class="custom-control-label" for="consultalotesstockpicking_solo_modulo">Solo m&oacute;dulo de la l&iacute;nea</label>
            </div>
          </div>
        </div>
        <div id="consultalotesstockpicking-contexto" class="consultalotesstockpicking-contexto mb-3" aria-live="polite"></div>
        <style>
          .consultalotesstockpicking-contexto .clsp-banner {
            background: #D6EAF8;
            border: 1px solid #85C1E9;
            border-left: 5px solid #2471A3;
            border-radius: 4px;
            padding: 0.75rem 1rem;
          }
          .consultalotesstockpicking-contexto .clsp-articulo {
            font-size: 1.15rem;
            font-weight: 700;
            color: #1B4F72;
            line-height: 1.3;
            margin-bottom: 0.45rem;
          }
          .consultalotesstockpicking-contexto .clsp-sku {
            font-weight: 600;
            color: #2471A3;
          }
          .consultalotesstockpicking-contexto .clsp-meta {
            color: #566573;
            font-size: 0.92rem;
            margin-bottom: 0.55rem;
          }
          .consultalotesstockpicking-contexto .clsp-pares {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
          }
          .consultalotesstockpicking-contexto .clsp-numeracion {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 0.5rem;
            background: #FCF3CF;
            border: 1px solid #F4D03F;
            color: #6E2C00;
            font-weight: 700;
            padding: 0.4rem 0.7rem;
            border-radius: 4px;
            margin-bottom: 0.55rem;
            line-height: 1.3;
          }
          .consultalotesstockpicking-contexto .clsp-numeracion .clsp-badge-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            opacity: 0.85;
          }
          .consultalotesstockpicking-contexto .clsp-numeracion .clsp-numeracion-txt {
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: 0.01em;
          }
          .consultalotesstockpicking-contexto .clsp-numeracion .clsp-numeracion-mod {
            font-size: 0.95rem;
            font-weight: 600;
            color: #7D6608;
          }
          #tabla-data-lotes-stock-picking td.clsp-medidas {
            font-weight: 700;
            color: #1B4F72;
            white-space: normal;
          }
          #tabla-data-lotes-stock-picking tr.clsp-fila-cubre {
            background: #D5F5E3;
          }
          #tabla-data-lotes-stock-picking tr.clsp-fila-cubre td.clsp-medidas {
            color: #145A32;
          }
          .consultalotesstockpicking-contexto .clsp-badge {
            display: inline-flex;
            align-items: baseline;
            gap: 0.35rem;
            background: #F9E79F;
            border: 1px solid #F4D03F;
            color: #7D6608;
            font-weight: 700;
            font-size: 1rem;
            padding: 0.35rem 0.7rem;
            border-radius: 4px;
            line-height: 1.2;
          }
          .consultalotesstockpicking-contexto .clsp-badge .clsp-badge-label {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.02em;
            opacity: 0.85;
          }
          .consultalotesstockpicking-contexto .clsp-badge .clsp-badge-num {
            font-size: 1.35rem;
            font-weight: 800;
            color: #6E2C00;
          }
          .consultalotesstockpicking-contexto .clsp-vacio,
          .consultalotesstockpicking-contexto .clsp-error {
            color: #7B241C;
            font-weight: 600;
            margin: 0;
          }
        </style>
        <div class="table-responsive">
          <table class="table table-sm table-striped table-bordered table-hover mb-0" id="tabla-data-lotes-stock-picking">
            <thead style="background:#85C1E9;color:#17202A;">
              <tr>
                <th>Lote / OT</th>
                <th>M&oacute;dulo</th>
                <th>Medidas</th>
                <th>Dep&oacute;sito</th>
                <th class="text-right">Saldo (pares)</th>
                <th style="width:1%;">Acciones</th>
              </tr>
            </thead>
            <tbody id="datoslotesstockpicking"></tbody>
          </table>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" id="cierraconsultalotesstockpickingModal" class="btn btn-secondary" data-dismiss="modal">Cierra</button>
      </div>
    </div>
  </div>
</div>
@endonce
