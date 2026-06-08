<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $titulo ?? 'Cierre jornada Waitry' }}</title>
    <style>
        table { font-family: DejaVu Sans, Arial, sans-serif; border-collapse: collapse; width: 100%; font-size: 9px; }
        th, td { border: 1px solid #666; padding: 4px 6px; text-align: left; }
        th { background: #d4e6f1; }
        .num { text-align: right; }
        h2 { font-size: 14px; margin-bottom: 4px; }
        .resumen { font-size: 10px; margin-bottom: 10px; }
    </style>
</head>
<body>
    <h2>{{ $titulo ?? 'Cierre jornada Waitry' }}</h2>
    @if (! empty($resumen))
        <p class="resumen">
            Órdenes Waitry: {{ $resumen['ordenes_waitry'] ?? 0 }}
            · Facturas Anita: {{ $resumen['facturas_anita_waitry'] ?? 0 }}
            · Tramo Waitry: ${{ number_format((float) ($resumen['total_waitry'] ?? 0), 2, ',', '.') }}
            · Anita→Waitry: ${{ number_format((float) ($resumen['total_anita_enviadas_waitry'] ?? 0), 2, ',', '.') }}
            · Anita jornada: ${{ number_format((float) ($resumen['total_anita_facturado'] ?? 0), 2, ',', '.') }}
            · Dif. global: ${{ number_format((float) ($resumen['diferencia_global'] ?? 0), 2, ',', '.') }}
        </p>
    @endif
    <table>
        <thead>
            <tr>
                <th>Orden Waitry</th>
                <th>Ref.</th>
                <th>Fecha/hora Waitry</th>
                <th class="num">Importe Waitry</th>
                <th>Pagada W.</th>
                <th>Venta Anita</th>
                <th class="num">Total Anita</th>
                <th>Medio Waitry</th>
                <th>Cta. caja esp.</th>
                <th>Cta. caja Anita</th>
                <th class="num">Diferencia</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $fila)
            <tr>
                <td>{{ $fila['waitry_order_id'] }}</td>
                <td>{{ $fila['referencia_waitry'] ?: '—' }}</td>
                <td>{{ $fila['fecha_hora_waitry'] ?: ($fila['hora_waitry'] ?: '—') }}</td>
                <td class="num">
                    @if ($fila['waitry_total'] !== null)
                        {{ number_format((float) $fila['waitry_total'], 2, ',', '.') }}
                    @else
                        —
                    @endif
                </td>
                <td>
                    @if ($fila['waitry_paid'] === null)
                        —
                    @elseif ($fila['waitry_paid'])
                        Sí
                    @else
                        No
                    @endif
                </td>
                <td>{{ $fila['anita_codigo'] ?? ($fila['anita_venta_id'] ? '#'.$fila['anita_venta_id'] : '—') }}</td>
                <td class="num">
                    @if ($fila['anita_total'] !== null)
                        {{ number_format((float) $fila['anita_total'], 2, ',', '.') }}
                    @else
                        —
                    @endif
                </td>
                <td>{{ $fila['waitry_medio_label'] ?? ($fila['anita_totem'] ? 'TOTEM' : '—') }}</td>
                <td>{{ $fila['cuentacaja_esperada_label'] ?? '—' }}</td>
                <td>{{ $fila['anita_cuentacaja_label'] ?? '—' }}</td>
                <td class="num">
                    @if ($fila['diferencia'] !== null)
                        {{ number_format((float) $fila['diferencia'], 2, ',', '.') }}
                    @else
                        —
                    @endif
                </td>
                <td>{{ $fila['estado_label'] }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="10">Sin datos.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
