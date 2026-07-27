@php
    $multiempresa = ! empty($multiempresa);
    $fmt = $excel_formato_numero ?? 'ar';
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="{{ $multiempresa ? 8 : 7 }}" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ $multiempresa ? 8 : 7 }}"><strong style="font-size: 16pt;">{{ $titulo ?? 'Balance de sumas y saldos' }}</strong></td>
    </tr>
    <tr>
        <td colspan="{{ $multiempresa ? 8 : 7 }}">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if (! empty($subtitulo))
        <tr>
            <td colspan="{{ $multiempresa ? 8 : 7 }}">{{ $subtitulo }}</td>
        </tr>
    @endif
    @if ((int) ($totales['cuentas'] ?? 0) > 0)
        <tr>
            <td colspan="{{ $multiempresa ? 8 : 7 }}">
                {{ (int) ($totales['cuentas'] ?? 0) }} cuentas
                · Débitos {{ number_format((float) ($totales['debe'] ?? 0), 2, ',', '.') }}
                · Créditos {{ number_format((float) ($totales['haber'] ?? 0), 2, ',', '.') }}
            </td>
        </tr>
    @endif
    <thead>
        <tr>
            <th>Nro. Cta.</th>
            <th>Descripción</th>
            <th>Débitos</th>
            <th>Créditos</th>
            <th>Saldo período</th>
            <th>Saldo mes ant.</th>
            <th>Saldo ejercicio</th>
            @if ($multiempresa)
                <th>Empresa</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            @if (($fila['tipo_fila'] ?? '') === 'header_empresa')
                <tr>
                    <td colspan="{{ $multiempresa ? 8 : 7 }}">{{ $fila['nombreempresa'] ?? $fila['nombre'] ?? '' }}</td>
                </tr>
            @else
                <tr>
                    <td>{{ $fila['codigo_fmt'] ?? '' }}</td>
                    <td>{{ $fila['nombre'] ?? '' }}</td>
                    <td>{{ (float) ($fila['debe'] ?? 0) }}</td>
                    <td>{{ (float) ($fila['haber'] ?? 0) }}</td>
                    <td>{{ (float) ($fila['saldo_periodo'] ?? 0) }}</td>
                    <td>{{ (float) ($fila['saldo_mes_anterior'] ?? 0) }}</td>
                    <td>{{ (float) ($fila['saldo_ejercicio'] ?? 0) }}</td>
                    @if ($multiempresa)
                        <td>{{ $fila['empresa_id'] ?? '' }}</td>
                    @endif
                </tr>
            @endif
        @endforeach
        @if ((int) ($totales['cuentas'] ?? 0) > 0
            || abs((float) ($totales['debe'] ?? 0)) > 0.005
            || abs((float) ($totales['haber'] ?? 0)) > 0.005)
            {{-- Anita l-sumsal.c: fila "Total general" --}}
            <tr>
                <td><strong>Total general</strong></td>
                <td></td>
                <td><strong>{{ (float) ($totales['debe'] ?? 0) }}</strong></td>
                <td><strong>{{ (float) ($totales['haber'] ?? 0) }}</strong></td>
                <td><strong>{{ (float) ($totales['saldo_periodo'] ?? 0) }}</strong></td>
                <td><strong>{{ (float) ($totales['saldo_mes_anterior'] ?? 0) }}</strong></td>
                <td><strong>{{ (float) ($totales['saldo_ejercicio'] ?? 0) }}</strong></td>
                @if ($multiempresa)
                    <td></td>
                @endif
            </tr>
        @endif
    </tbody>
</table>
