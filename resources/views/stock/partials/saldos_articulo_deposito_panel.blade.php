{{--
    Grilla de saldos por depósito (ERP articulo_saldo_deposito).
    Variables: $panelId (identificador único del contenedor)
--}}
@php
    $panelId = $panelId ?? 'saldos';
@endphp
<div class="saldos-articulo-panel" id="saldos-articulo-panel-{{ $panelId }}" data-panel-id="{{ $panelId }}">
    <label class="d-block small font-weight-bold mb-1">
        Saldos por dep&oacute;sito
        <span class="saldos-articulo-um-hint text-muted font-weight-normal d-none"></span>
    </label>
    <div class="saldos-articulo-loading small text-muted d-none">
        <i class="fa fa-spinner fa-spin"></i> Consultando saldos…
    </div>
    <div class="saldos-articulo-error small text-danger d-none"></div>
    <div class="table-responsive saldos-articulo-tabla-wrap d-none" style="max-height: 11rem;">
        <table class="table table-sm table-bordered table-hover mb-1">
            <thead class="thead-light">
                <tr>
                    <th style="width:12%">C&oacute;d.</th>
                    <th>Dep&oacute;sito</th>
                    <th class="saldos-col-empresa d-none" style="width:22%">Empresa</th>
                    <th class="text-right saldos-th-saldo" style="width:16%">Saldo</th>
                    <th class="text-right saldos-th-caja d-none" style="width:12%">Caja</th>
                    <th class="text-right saldos-th-pieza d-none" style="width:12%">Pieza</th>
                    <th style="width:12%" class="text-center">Kardex</th>
                </tr>
            </thead>
            <tbody class="saldos-articulo-tbody"></tbody>
            <tfoot>
                <tr class="font-weight-bold small">
                    <td colspan="2" class="text-right saldos-footer-label">Total autorizado</td>
                    <td class="saldos-col-empresa saldos-footer-empresa d-none"></td>
                    <td class="text-right text-monospace saldos-articulo-total"></td>
                    <td class="text-right text-monospace saldos-articulo-total-caja d-none"></td>
                    <td class="text-right text-monospace saldos-articulo-total-pieza d-none"></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <p class="saldos-articulo-vacio small text-muted d-none mb-0">Sin movimientos en dep&oacute;sitos autorizados.</p>
</div>
