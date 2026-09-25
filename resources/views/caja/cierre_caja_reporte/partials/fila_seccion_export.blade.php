@php
    $fila = $fila ?? [];
    $clave = $clave ?? '';
    $esTotal = ($fila['tipo_fila'] ?? '') === 'total';
    $trClass = $esTotal ? 'total' : '';
    $fechaCheque = (string) ($fila['fecha'] ?? '');
    if ($clave === 'cheques_rechazados') {
        $fechaCheque = (string) ($fila['fecha_rechazo'] ?? $fechaCheque);
    } elseif ($clave === 'cheques_caucion') {
        $fechaCheque = (string) ($fila['fecha_caucion'] ?? '');
    } elseif ($clave === 'cheques_recibidos') {
        $fechaCheque = (string) ($fila['fecha_cheque'] ?? $fechaCheque);
        if ($fechaCheque === '') {
            $fechaCheque = (string) ($fila['fecha'] ?? '');
        }
    }
@endphp
@if ($clave === 'saldos')
    <tr class="{{ $trClass }}">
        <td>{{ $fila['codigo'] ?? '' }}</td>
        <td>{{ $fila['nombre'] ?? '' }}</td>
        <td class="text-right">{{ number_format((float) ($fila['saldo_anterior'] ?? 0), 2, ',', '.') }}</td>
        <td class="text-right">{{ number_format((float) ($fila['ingresos'] ?? 0), 2, ',', '.') }}</td>
        <td class="text-right">{{ number_format((float) ($fila['egresos'] ?? 0), 2, ',', '.') }}</td>
        <td class="text-right">{{ number_format((float) ($fila['total_movimiento'] ?? 0), 2, ',', '.') }}</td>
        <td class="text-right">{{ number_format((float) ($fila['saldo_actual'] ?? 0), 2, ',', '.') }}</td>
    </tr>
@elseif ($clave === 'cheques_emitidos')
    <tr class="{{ $trClass }}">
        <td>{{ $fila['codigo'] ?? '' }}</td>
        <td>{{ $fila['nombre'] ?? '' }}</td>
        <td class="text-right">{{ $esTotal ? '' : (int) ($fila['cantidad'] ?? 0) }}</td>
        <td class="text-right">{{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
    </tr>
@elseif ($clave === 'depositos')
    <tr class="{{ $trClass }}">
        <td>{{ $fila['fecha'] ?? '' }}</td>
        <td>{{ $fila['numerocheque'] ?? '' }}</td>
        <td>{{ $fila['cuenta_nombre'] ?? ($fila['banco'] ?? '') }}</td>
        <td>{{ $fila['boleta'] ?? '' }}</td>
        <td class="text-right">{{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
    </tr>
@elseif ($clave === 'cobro_pago')
    <tr class="{{ $trClass }}">
        <td>{{ $fila['aplicacion'] ?? '' }}</td>
        <td class="text-right">{{ number_format((float) ($fila['cobro'] ?? 0), 2, ',', '.') }}</td>
        <td class="text-right">{{ number_format((float) ($fila['pago'] ?? 0), 2, ',', '.') }}</td>
    </tr>
@else
    @php
        $nroCheque = (string) ($fila['numerocheque'] ?? '');
        $nroEcheq = trim((string) ($fila['nro_echeq'] ?? ''));
        if ($nroEcheq !== '' && $nroEcheq !== $nroCheque) {
            $nroCheque = $nroCheque !== '' ? $nroCheque.' / E:'.$nroEcheq : 'E:'.$nroEcheq;
        } elseif ($nroEcheq !== '' && $nroCheque === '') {
            $nroCheque = $nroEcheq;
        }
        $clienteCol = (string) ($fila['cliente_codigo'] ?? '');
        if ($clienteCol === '') {
            $clienteCol = (string) ($fila['cliente_nombre'] ?? '');
        }
    @endphp
    <tr class="{{ $trClass }}">
        <td>{{ $fila['nro_interno'] ?? '' }}</td>
        <td>{{ $clienteCol }}</td>
        <td>{{ $fechaCheque }}</td>
        <td>{{ $nroCheque }}</td>
        <td>{{ $fila['banco'] ?? '' }}</td>
        <td class="text-right">{{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
    </tr>
@endif
