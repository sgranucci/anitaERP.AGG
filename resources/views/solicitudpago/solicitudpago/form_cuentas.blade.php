<div id="sp-aviso-asiento" class="alert alert-warning mb-3" role="alert">
    <i class="fa fa-exclamation-triangle"></i>
    El <strong>asiento contable es obligatorio</strong> para grabar la solicitud.
    Se arma al elegir el concepto (solapa Cuentas: D/H + Debe / Haber).
    Rev&iacute;selo antes de guardar.
</div>
<div id="sp-aviso-asiento-vacio" class="alert alert-danger mb-3 d-none" role="alert">
    <i class="fa fa-times-circle"></i>
    No hay l&iacute;neas de asiento con cuenta e importe. Elija un concepto con cuentas o cargue el asiento manualmente.
</div>
<div id="sp-aviso-asiento-desbalance" class="alert alert-danger mb-3 d-none" role="alert">
    <i class="fa fa-balance-scale"></i>
    El asiento no balancea: el total <strong>Debe</strong> debe ser igual al total <strong>Haber</strong>.
</div>
<div class="table-responsive">
    <table class="table table-sm table-bordered" id="solicitudpago-cuenta-table">
        <thead class="thead-light">
            <tr>
                <th style="width: 13%;">Empresa</th>
                <th style="width: 30%;">Cuenta contable</th>
                <th style="width: 14%;">Centro de costo</th>
                <th style="width: 8%;" class="text-center">D/H</th>
                <th style="width: 12%;" class="text-right">Debe</th>
                <th style="width: 12%;" class="text-right">Haber</th>
                <th style="width: 7%;"></th>
            </tr>
        </thead>
        <tbody id="tbody-solicitudpago-cuenta-table">
            @php
                $filasCta = old('cuentacontable_ids') !== null
                    ? collect(old('cuentacontable_ids', []))->map(function ($cuentaId, $i) {
                        $dh = strtoupper((string) old('debe_haberes.'.$i, 'D'));
                        $monto = (float) old('montos_cuenta.'.$i, 0);
                        $debeOld = old('montos_debe.'.$i);
                        $haberOld = old('montos_haber.'.$i);
                        if ($debeOld === null && $haberOld === null) {
                            $debeOld = $dh === 'D' ? $monto : 0;
                            $haberOld = $dh === 'H' ? $monto : 0;
                        }

                        return (object) [
                            'empresa_id' => old('empresa_ids.'.$i),
                            'cuentacontable_id' => $cuentaId,
                            'centrocosto_id' => old('centrocosto_ids.'.$i),
                            'debe_haber' => $dh,
                            'monto' => $monto,
                            'monto_debe' => $debeOld,
                            'monto_haber' => $haberOld,
                            'empresas' => null,
                            'cuentacontables' => null,
                            'centrocostos' => null,
                        ];
                    })
                    : collect(isset($data) ? ($data->cuentas ?? []) : []);
            @endphp
            @foreach ($filasCta as $fila)
                @include('solicitudpago.solicitudpago.partials.fila_cuenta', ['fila' => $fila])
            @endforeach
        </tbody>
        <tfoot>
            <tr class="font-weight-bold">
                <td colspan="4" class="text-right">Totales</td>
                <td class="text-right"><span id="sp-total-debe">0,00</span></td>
                <td class="text-right"><span id="sp-total-haber">0,00</span></td>
                <td></td>
            </tr>
        </tfoot>
    </table>
</div>
@include('solicitudpago.solicitudpago.partials.template_cuenta')
<div class="row mt-2">
    <div class="col-12 text-right">
        <button type="button" id="agrega_renglon_sp_cuenta" class="btn btn-outline-danger btn-sm">
            <i class="fa fa-plus"></i> Agregar cuenta
        </button>
    </div>
</div>
