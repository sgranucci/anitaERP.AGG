<style>
    #modal-ie-liquidacion-tarjeta .ie-lt-dialog { max-width: 1280px; }
    #modal-ie-liquidacion-tarjeta .ie-lt-col-netos { border-right: 1px solid #d5d8dc; }
    #modal-ie-liquidacion-tarjeta .ie-lt-col-titulo {
        background: #D4E6F1;
        color: #1B4F72;
        font-size: .75rem;
        font-weight: 700;
        letter-spacing: .03em;
        text-transform: uppercase;
        padding: .35rem .6rem;
    }
    #modal-ie-liquidacion-tarjeta .ie-lt-total-col {
        background: #E8F8F5;
        font-weight: 700;
    }
    #modal-ie-liquidacion-tarjeta .modal-header { border-bottom: 0; padding-bottom: .35rem; }
    #modal-ie-liquidacion-tarjeta .ie-lt-subtitulo { opacity: .85; font-size: .8rem; font-weight: 400; }
    #modal-ie-liquidacion-tarjeta .modal-body { padding: 0; background: #f4f6f7; }
    #modal-ie-liquidacion-tarjeta .ie-lt-bloque {
        background: #fff;
        margin: .75rem 1rem;
        border: 1px solid #d5d8dc;
        border-radius: .35rem;
        overflow: hidden;
    }
    #modal-ie-liquidacion-tarjeta .ie-lt-bloque-head {
        background: #1B4F72;
        color: #fff;
        font-size: .78rem;
        font-weight: 700;
        letter-spacing: .04em;
        text-transform: uppercase;
        padding: .45rem .85rem;
    }
    #modal-ie-liquidacion-tarjeta .ie-lt-bloque-head.ie-lt-head-conceptos { background: #1A5276; }
    #modal-ie-liquidacion-tarjeta .ie-lt-bloque-body { padding: .85rem .9rem .7rem; }
    #modal-ie-liquidacion-tarjeta .ie-lt-label {
        display: block;
        font-size: .72rem;
        font-weight: 700;
        color: #1B4F72;
        margin-bottom: .2rem;
        text-transform: uppercase;
        letter-spacing: .02em;
        text-align: left;
    }
    #modal-ie-liquidacion-tarjeta .ie-lt-conceptos-scroll { max-height: 320px; overflow-y: auto; }
    #modal-ie-liquidacion-tarjeta .ie-lt-resumen {
        background: #EAF2F8;
        border-top: 1px solid #d5d8dc;
        padding: .45rem .85rem;
        font-size: .85rem;
    }
    #modal-ie-liquidacion-tarjeta .ie-lt-cobrado-box {
        background: #D6EAF8;
        font-weight: 700;
    }
    #modal-ie-liquidacion-tarjeta #tm_cuentacaja_ie_liquidacion_caja.d-flex {
        display: block !important;
    }
    #modal-ie-liquidacion-tarjeta #tm_cuentacaja_ie_liquidacion_tarjeta > label,
    #modal-ie-liquidacion-tarjeta #tm_cuentacaja_ie_liquidacion_caja > label {
        display: block;
        font-size: .72rem;
        font-weight: 700;
        color: #1B4F72;
        margin-bottom: .2rem;
        text-transform: uppercase;
        letter-spacing: .02em;
        text-align: left;
    }
</style>
<div class="modal fade" id="modal-ie-liquidacion-tarjeta" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable ie-lt-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header" style="background:#1A5276;color:#fff;">
                <div>
                    <h5 class="modal-title mb-0">
                        <i class="fa fa-credit-card"></i> Liquidación de tarjeta
                    </h5>
                    <div class="ie-lt-subtitulo">
                        El bruto acredita la tarjeta. Los gastos van al DTC. El neto entra en la cuenta de caja de esta pantalla.
                    </div>
                </div>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="ie-lt-bloque">
                    <div class="ie-lt-bloque-head">Encabezado</div>
                    <div class="ie-lt-bloque-body">
                        <div class="form-row">
                            <div class="col-md-6">
                                @include('caja.partials.campo_consulta_cuentacaja', [
                                    'prefix' => 'ie_liquidacion_tarjeta',
                                    'layout' => 'inline',
                                    'label' => 'Tarjeta',
                                    'inputName' => 'ie_liquidacion_cuentacaja_id',
                                    'inputId' => 'ie-lt-cuentacaja-id',
                                    'required' => true,
                                    'mostrar_editar' => true,
                                    'ayuda' => 'La cuenta marcada como tarjeta. Los cupones se acreditan en su cuenta contable.',
                                ])
                            </div>
                            <div class="col-md-6">
                                @include('caja.partials.campo_consulta_cuentacaja', [
                                    'prefix' => 'ie_liquidacion_caja',
                                    'layout' => 'inline',
                                    'label' => 'Cuenta de caja',
                                    'inputName' => 'ie_liquidacion_caja_deposito_id',
                                    'inputId' => 'ie-lt-caja-id',
                                    'required' => true,
                                    'mostrar_editar' => true,
                                    'ayuda' => 'Donde entra el neto. Al armar el DTC se carga en la pantalla principal.',
                                ])
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-4 mb-2">
                                <label class="ie-lt-label" for="ie-lt-bruto">Bruto de cupones</label>
                                <input type="text" inputmode="decimal" autocomplete="off" class="form-control form-control-sm text-right font-weight-bold" id="ie-lt-bruto">
                            </div>
                        </div>
                        <div class="form-row">
                            <div class="form-group col-md-5 mb-2">
                                <label class="ie-lt-label" for="ie-lt-nombre">Emisor</label>
                                <input type="text" class="form-control form-control-sm" id="ie-lt-nombre" maxlength="120" placeholder="Nombre en el DTC">
                            </div>
                            <div class="form-group col-md-3 mb-2">
                                <label class="ie-lt-label" for="ie-lt-cuit">CUIT</label>
                                <input type="text" class="form-control form-control-sm" id="ie-lt-cuit" maxlength="13" autocomplete="off" placeholder="XX-XXXXXXXX-X" oninput="formatarCUIT(this)">
                            </div>
                            <div class="form-group col-md-4 mb-2">
                                <label class="ie-lt-label" for="ie-lt-condicioniva">Condición IVA</label>
                                <select class="form-control form-control-sm" id="ie-lt-condicioniva">
                                    <option value="">Condición IVA</option>
                                    @foreach ($condicioniva_query ?? [] as $condicion)
                                        <option value="{{ $condicion->id }}">{{ $condicion->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-row mb-0">
                            <div class="form-group col-md-4 mb-0">
                                <label class="ie-lt-label" for="ie-lt-fecha">Fecha</label>
                                <input type="date" class="form-control form-control-sm" id="ie-lt-fecha">
                            </div>
                            <div class="form-group col-md-8 mb-0 d-flex align-items-end">
                                <p class="small text-muted mb-1">
                                    El número sale del numerador de DTC. Letra A, punto de venta 1. Se asigna al grabar.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="ie-lt-bloque">
                    <div class="ie-lt-bloque-head ie-lt-head-conceptos">Conceptos del DTC</div>
                    <div class="row no-gutters" id="ie-lt-conceptos">
                        <div class="col-md-6 ie-lt-col-netos">
                            <div class="ie-lt-col-titulo">Netos / exentos</div>
                            <div class="table-responsive ie-lt-conceptos-scroll">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead style="background:#85C1E9;color:#17202A;">
                                        <tr>
                                            <th>Concepto</th>
                                            <th class="text-right" style="width:8.5rem;">Importe</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ie-lt-tbody-netos"></tbody>
                                </table>
                            </div>
                            <div class="ie-lt-total-col d-flex justify-content-between px-2 py-1 border-top">
                                <span>Total netos / exentos</span>
                                <span id="ie-lt-total-netos">0,00</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="ie-lt-col-titulo">Impuestos (puestos)</div>
                            <div class="table-responsive ie-lt-conceptos-scroll">
                                <table class="table table-sm table-bordered mb-0">
                                    <thead style="background:#85C1E9;color:#17202A;">
                                        <tr>
                                            <th>Concepto</th>
                                            <th class="text-right" style="width:8.5rem;">Importe</th>
                                        </tr>
                                    </thead>
                                    <tbody id="ie-lt-tbody-puestos"></tbody>
                                </table>
                            </div>
                            <div class="ie-lt-total-col d-flex justify-content-between px-2 py-1 border-top">
                                <span>Total impuestos</span>
                                <span id="ie-lt-total-puestos">0,00</span>
                            </div>
                        </div>
                    </div>
                    <div class="ie-lt-resumen d-flex justify-content-between align-items-center">
                        <span>Total gastos: <strong id="ie-lt-gastos">0,00</strong></span>
                        <span class="ie-lt-cobrado-box px-2 py-1">Total cobrado: <span id="ie-lt-cobrado">0,00</span></span>
                    </div>
                    <div class="px-3 py-2">
                        <p class="small text-muted mb-0" id="ie-lt-cuenta-haber"></p>
                        <p class="small text-danger mb-0 d-none" id="ie-lt-aviso"></p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary btn-sm" id="ie-lt-aceptar">
                    <i class="fa fa-check"></i> Armar DTC
                </button>
            </div>
        </div>
    </div>
</div>
