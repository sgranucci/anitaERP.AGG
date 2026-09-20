@php
    $seccion = $seccion ?? ($fila['seccion'] ?? '');
    $esTotal = $esTotal ?? false;
    $puedeCuenta = $puedeCuenta ?? false;
    $puedeCheque = $puedeCheque ?? false;
@endphp
@if ($seccion === 'saldos')
    <td>
        @if (!$esTotal && $puedeCuenta && (int) ($fila['cuenta_id'] ?? 0) > 0)
            <a href="{{ route('editar_cuentacaja', ['id' => $fila['cuenta_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
               class="text-primary" target="_blank" rel="noopener">{{ $fila['codigo'] ?? '' }}</a>
        @else
            {{ $fila['codigo'] ?? '' }}
        @endif
    </td>
    <td>{{ $fila['nombre'] ?? '' }}</td>
    <td>
        @if ($esTotal)
        @elseif (! empty($fila['es_cheques_cartera']))
            Cartera
        @else
            Saldo / movimientos
        @endif
    </td>
    <td class="text-right">{{ number_format((float) ($fila['saldo_anterior'] ?? 0), 2, ',', '.') }}</td>
    <td class="text-right">{{ number_format((float) ($fila['ingresos'] ?? 0), 2, ',', '.') }}</td>
    <td class="text-right">{{ number_format((float) ($fila['egresos'] ?? 0), 2, ',', '.') }}</td>
    <td class="text-right">{{ number_format((float) ($fila['saldo_actual'] ?? 0), 2, ',', '.') }}</td>
@elseif ($seccion === 'emitidos')
    <td>{{ $fila['codigo'] ?? '' }}</td>
    <td>{{ $fila['nombre'] ?? '' }}</td>
    <td>{{ $esTotal ? '' : ('Cant. '.(int) ($fila['cantidad'] ?? 0)) }}</td>
    <td class="text-right" colspan="1">{{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
    <td></td><td></td><td></td>
@elseif ($seccion === 'depositos')
    <td>
        @if (!$esTotal && $puedeCheque && (int) ($fila['id'] ?? 0) > 0)
            <a href="{{ route('editar_cheque', ['id' => $fila['id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
               class="text-primary" target="_blank" rel="noopener">{{ $fila['numerocheque'] ?? $fila['id'] }}</a>
        @else
            {{ $fila['numerocheque'] ?? '' }}
        @endif
    </td>
    <td>{{ $fila['cuenta_nombre'] ?? ($fila['banco'] ?? '') }}</td>
    <td>{{ trim(($fila['fecha'] ?? '').' '.($fila['boleta'] ?? '')) }}</td>
    <td class="text-right">{{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
    <td></td><td></td><td></td>
@elseif ($seccion === 'cobro_pago')
    <td>{{ $fila['aplicacion'] ?? '' }}</td>
    <td>{{ $esTotal ? 'Total cobranzas / pagos' : 'Aplicación' }}</td>
    <td></td>
    <td class="text-right">{{ number_format((float) ($fila['cobro'] ?? 0), 2, ',', '.') }}</td>
    <td class="text-right">{{ number_format((float) ($fila['pago'] ?? 0), 2, ',', '.') }}</td>
    <td></td><td></td>
@else
    {{-- recibidos / rechazados / caucion --}}
    <td>
        @if (!$esTotal && $puedeCheque && (int) ($fila['id'] ?? 0) > 0)
            <a href="{{ route('editar_cheque', ['id' => $fila['id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
               class="text-primary" target="_blank" rel="noopener">{{ $fila['nro_interno'] ?? $fila['numerocheque'] ?? '' }}</a>
        @else
            {{ $fila['nro_interno'] ?? ($fila['cliente_nombre'] ?? '') }}
        @endif
    </td>
    <td>{{ $fila['cliente_nombre'] ?? ($fila['banco'] ?? '') }}</td>
    <td>
        @if ($seccion === 'rechazados')
            {{ $fila['fecha_rechazo'] ?? $fila['fecha'] ?? '' }}
        @elseif ($seccion === 'caucion')
            {{ $fila['fecha_caucion'] ?? '' }}
        @else
            {{ trim(($fila['fecha'] ?? '').' '.($fila['numerocheque'] ?? '')) }}
        @endif
    </td>
    <td class="text-right">{{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
    <td></td><td></td><td></td>
@endif
