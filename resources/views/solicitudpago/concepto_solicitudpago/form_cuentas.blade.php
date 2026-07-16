<div class="table-responsive">
    <table class="table table-sm table-bordered" id="concepto-cuenta-table">
        <thead class="thead-light">
            <tr>
                <th style="width: 18%;">Empresa</th>
                <th style="width: 42%;">Cuenta contable</th>
                <th style="width: 22%;">Centro de costo</th>
                <th style="width: 10%;">D/H</th>
                <th style="width: 8%;"></th>
            </tr>
        </thead>
        <tbody id="tbody-concepto-cuenta-table">
            @php
                $filasCta = old('cuentacontable_ids') !== null
                    ? collect(old('cuentacontable_ids', []))->map(function ($cuentaId, $i) {
                        return (object) [
                            'empresa_id' => old('empresa_ids.'.$i),
                            'cuentacontable_id' => $cuentaId,
                            'centrocosto_id' => old('centrocosto_ids.'.$i),
                            'debe_haber' => old('debe_haberes.'.$i, 'D'),
                            'empresas' => null,
                            'cuentacontables' => null,
                            'centrocostos' => null,
                        ];
                    })
                    : collect(isset($data) ? ($data->cuentas ?? []) : []);
            @endphp
            @foreach ($filasCta as $fila)
                @include('solicitudpago.concepto_solicitudpago.partials.fila_cuenta', ['fila' => $fila])
            @endforeach
        </tbody>
    </table>
</div>
@include('solicitudpago.concepto_solicitudpago.partials.template_cuenta')
<div class="row mt-2">
    <div class="col-12 text-right">
        <button type="button" id="agrega_renglon_concepto_cuenta" class="btn btn-outline-danger btn-sm">
            <i class="fa fa-plus"></i> Agregar cuenta
        </button>
    </div>
</div>
@include('includes.contable.modalconsultacuentacontable')
