@php
    $resultado = $resultado ?? [];
    $esPdf = $esPdf ?? false;
@endphp

@if (!empty($resultado['saldos']))
    <h3>Resumen de movimientos por cuenta</h3>
    <table class="data">
        <thead>
            <tr>
                <th>Código</th>
                <th>Cuenta</th>
                <th>Saldo anterior</th>
                <th>Ingresos</th>
                <th>Egresos</th>
                <th>Total mov.</th>
                <th>Saldo actual</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($resultado['saldos'] as $fila)
                <tr class="{{ ($fila['tipo_fila'] ?? '') === 'total' ? 'total' : '' }}">
                    <td>{{ $fila['codigo'] ?? '' }}</td>
                    <td>{{ $fila['nombre'] ?? '' }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['saldo_anterior'] ?? 0), 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['ingresos'] ?? 0), 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['egresos'] ?? 0), 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['total_movimiento'] ?? 0), 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['saldo_actual'] ?? 0), 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if (!empty($resultado['cheques_emitidos']))
    <h3>Cheques propios emitidos</h3>
    <table class="data">
        <thead>
            <tr>
                <th>Código</th>
                <th>Cuenta</th>
                <th>Cantidad</th>
                <th>Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($resultado['cheques_emitidos'] as $fila)
                <tr class="{{ ($fila['tipo_fila'] ?? '') === 'total' ? 'total' : '' }}">
                    <td>{{ $fila['codigo'] ?? '' }}</td>
                    <td>{{ $fila['nombre'] ?? '' }}</td>
                    <td class="text-right">{{ ($fila['tipo_fila'] ?? '') === 'total' ? '' : (int) ($fila['cantidad'] ?? 0) }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if (!empty($resultado['depositos']))
    <h3>Depósitos</h3>
    <table class="data">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Cheque</th>
                <th>Banco / Cuenta</th>
                <th>Boleta</th>
                <th>Importe</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($resultado['depositos'] as $fila)
                <tr class="{{ ($fila['tipo_fila'] ?? '') === 'total' ? 'total' : '' }}">
                    <td>{{ $fila['fecha'] ?? '' }}</td>
                    <td>{{ $fila['numerocheque'] ?? '' }}</td>
                    <td>{{ $fila['cuenta_nombre'] ?? ($fila['banco'] ?? '') }}</td>
                    <td>{{ $fila['boleta'] ?? '' }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@if (!empty($resultado['cobro_pago']['filas']))
    <h3>Cobranzas / Pagos</h3>
    <table class="data">
        <thead>
            <tr>
                <th>Aplicación</th>
                <th>Cobro</th>
                <th>Pago</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($resultado['cobro_pago']['filas'] as $fila)
                <tr class="{{ ($fila['tipo_fila'] ?? '') === 'total' ? 'total' : '' }}">
                    <td>{{ $fila['aplicacion'] ?? '' }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['cobro'] ?? 0), 2, ',', '.') }}</td>
                    <td class="text-right">{{ number_format((float) ($fila['pago'] ?? 0), 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
@endif

@foreach ([
    'cheques_recibidos' => 'Cheques de terceros recibidos',
    'cheques_rechazados' => 'Cheques de terceros rechazados',
    'cheques_caucion' => 'Cheques entregados en caución',
] as $clave => $tituloSeccion)
    @if (!empty($resultado[$clave]))
        <h3>{{ $tituloSeccion }}</h3>
        <table class="data">
            <thead>
                <tr>
                    <th>N.Int.</th>
                    <th>Cliente</th>
                    <th>Fecha</th>
                    <th>N.Cheque</th>
                    <th>Banco</th>
                    <th>Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($resultado[$clave] as $fila)
                    <tr class="{{ ($fila['tipo_fila'] ?? '') === 'total' ? 'total' : '' }}">
                        <td>{{ $fila['nro_interno'] ?? '' }}</td>
                        <td>{{ $fila['cliente_nombre'] ?? '' }}</td>
                        <td>
                            @if ($clave === 'cheques_rechazados')
                                {{ $fila['fecha_rechazo'] ?? $fila['fecha'] ?? '' }}
                            @elseif ($clave === 'cheques_caucion')
                                {{ $fila['fecha_caucion'] ?? '' }}
                            @else
                                {{ $fila['fecha'] ?? '' }}
                            @endif
                        </td>
                        <td>{{ $fila['numerocheque'] ?? '' }}</td>
                        <td>{{ $fila['banco'] ?? '' }}</td>
                        <td class="text-right">{{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endforeach
