@php
    use App\Support\Compras\ProveedorCuentacorrienteGrillaSupport;
    use App\Support\Compras\ProveedorCuentacorrientePreferenciasUsuario;

    $modoDeuda = ($modoVista ?? ProveedorCuentacorrientePreferenciasUsuario::MODO_CUENTA_CORRIENTE)
        === ProveedorCuentacorrientePreferenciasUsuario::MODO_DEUDA;
    $paraExcel = ! empty($para_excel);
    $formatearMonto = static function ($valor) use ($paraExcel) {
        if ($paraExcel) {
            return $valor;
        }

        return number_format((float) $valor, 2, ',', '.');
    };
    $saldo = (float) ($saldoAnterior ?? 0);
@endphp
<thead>
    <tr>
        <th style="width: 5%;">ID</th>
        <th style="width: 12%;">Empresa</th>
        <th style="width: 9%;">Fecha</th>
        <th style="width: 9%;">Vencimiento</th>
        <th style="width: 24%;">Comprobante</th>
        <th style="width: 6%;">Moneda</th>
        @if ($modoDeuda)
            <th style="width: 11%; text-align: right;">Importe</th>
            <th style="width: 11%; text-align: right;">Aplicado</th>
            <th style="width: 13%; text-align: right;">Saldo pendiente</th>
        @else
            <th style="width: 11%; text-align: right;">Debe</th>
            <th style="width: 11%; text-align: right;">Haber</th>
            <th style="width: 13%; text-align: right;">Saldo</th>
        @endif
    </tr>
</thead>
<tbody>
    @foreach ($filas as $data)
        @php
            $etiquetaComprobante = ProveedorCuentacorrienteGrillaSupport::etiquetaComprobante($data);
            $aplicado = (float) ($data->aplicado ?? 0);
            $saldoPendiente = ProveedorCuentacorrienteGrillaSupport::saldoPendienteAbsoluto((float) $data->total, $data->aplicado ?? null);
            if (! $modoDeuda) {
                $saldo += (float) $data->total;
            }
        @endphp
        <tr>
            <td>{{ $data->id }}</td>
            <td>{{ $data->empresas->nombre ?? ($data->nombreempresa ?? '') }}</td>
            <td>{{ date('d/m/Y', strtotime($data->fecha ?? '')) }}</td>
            <td>{{ date('d/m/Y', strtotime($data->fechavencimiento ?? '')) }}</td>
            <td>{{ $etiquetaComprobante }}</td>
            <td>{{ $data->monedas->abreviatura ?? '' }}</td>
            @if ($modoDeuda)
                <td class="text-right" style="text-align: right;">{{ $formatearMonto(abs($data->total)) }}</td>
                <td class="text-right" style="text-align: right;">
                    @if ($aplicado != 0)
                        {{ $formatearMonto(abs($aplicado)) }}
                    @endif
                </td>
                <td class="text-right" style="text-align: right;">{{ $formatearMonto($saldoPendiente) }}</td>
            @else
                <td class="text-right" style="text-align: right;">
                    @if ($data->total >= 0)
                        {{ $formatearMonto($data->total) }}
                    @endif
                </td>
                <td class="text-right" style="text-align: right;">
                    @if ($data->total < 0)
                        {{ $formatearMonto(abs($data->total)) }}
                    @endif
                </td>
                <td class="text-right" style="text-align: right;">{{ $formatearMonto($saldo) }}</td>
            @endif
        </tr>
    @endforeach
</tbody>
