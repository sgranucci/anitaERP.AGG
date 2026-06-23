{{--
    Grilla de saldos por depósito (ERP articulo_saldo_deposito).
    Variables: $panelId (identificador único del contenedor)
--}}
@php
    $panelId = $panelId ?? 'saldos';
@endphp
<div class="saldos-articulo-panel" id="saldos-articulo-panel-{{ $panelId }}" data-panel-id="{{ $panelId }}">
    <label class="d-block small font-weight-bold mb-1">Saldos por dep&oacute;sito</label>
    <div class="saldos-articulo-loading small text-muted d-none">
        <i class="fa fa-spinner fa-spin"></i> Consultando saldos…
    </div>
    <div class="saldos-articulo-error small text-danger d-none"></div>
    <div class="table-responsive saldos-articulo-tabla-wrap d-none" style="max-height: 11rem;">
        <table class="table table-sm table-bordered table-hover mb-1">
            <thead class="thead-light">
                <tr>
                    <th style="width:14%">C&oacute;d.</th>
                    <th>Dep&oacute;sito</th>
                    <th class="text-right" style="width:18%">Saldo</th>
                    <th style="width:12%" class="text-center">Kardex</th>
                </tr>
            </thead>
            <tbody class="saldos-articulo-tbody"></tbody>
            <tfoot>
                <tr class="font-weight-bold small">
                    <td colspan="2" class="text-right">Total autorizado</td>
                    <td class="text-right text-monospace saldos-articulo-total"></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <p class="saldos-articulo-vacio small text-muted d-none mb-0">Sin saldo en dep&oacute;sitos autorizados.</p>
</div>
