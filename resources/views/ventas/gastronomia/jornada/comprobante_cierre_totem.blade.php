@php
    $d = $datos;
    $lineas = $d['lineas'] ?? [];
    $porTotem = $d['por_totem'] ?? [];
    $totalGeneral = $d['total_general'] ?? [];
    $auditoria = $d['auditoria'] ?? [];
    $hayDiscrepancias = ! empty($d['hay_discrepancias']);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $d['titulo'] ?? 'Cierre Waitry tótem' }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #222; margin: 10px 14px; }
        h1 { font-size: 15px; margin: 0 0 4px 0; }
        h2 { font-size: 10px; margin: 10px 0 5px 0; border-bottom: 1px solid #333; padding-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { border: 1px solid #666; padding: 3px 4px; vertical-align: top; }
        th { background: #d4e6f1; font-weight: bold; text-align: left; }
        .cabecera-doc td { border: none !important; vertical-align: middle; }
        .cabecera-doc { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .logo { max-height: 50px; max-width: 180px; }
        .subtitulo { font-size: 9px; color: #444; }
        .lbl { background: #f0f0f0; font-weight: bold; width: 22%; }
        .num { text-align: right; white-space: nowrap; }
        .muted { color: #555; font-size: 7px; }
        .total-grande { font-size: 10px; font-weight: bold; background: #e8f4fc; }
        .fila-discrepancia { background: #fff3cd; }
        .bloque-totem { margin-bottom: 12px; page-break-inside: avoid; }
        .ok-box { background: #d4edda; padding: 6px 8px; margin-bottom: 8px; }
    </style>
</head>
<body>
    <table class="cabecera-doc">
        <tr>
            <td style="width: 35%;">
                @if (!empty($d['logo']['uri']))
                    <img src="{{ $d['logo']['uri'] }}" alt="Logo" class="logo">
                @endif
            </td>
            <td style="width: 65%; text-align: right;">
                <h1>{{ $d['titulo'] }}</h1>
                <div class="subtitulo">{{ $d['subtitulo'] }}</div>
                <div class="muted">Emitido: {{ $d['fecha_emision_comprobante'] }}</div>
            </td>
        </tr>
    </table>

    <h2>Resumen del cierre</h2>
    <table>
        <tr>
            <td class="lbl">Empresa</td>
            <td>{{ $d['empresa_nombre'] }}</td>
            <td class="lbl">Fecha jornada</td>
            <td>{{ $d['fecha_jornada'] }}</td>
        </tr>
        <tr>
            <td class="lbl">Comandas Waitry (desde — hasta)</td>
            <td colspan="3"><strong>{{ $d['ventana_operativa'] ?? '—' }}</strong></td>
        </tr>
        <tr>
            <td class="lbl">Días consultados en API</td>
            <td colspan="3">{{ $d['consulta_waitry_rango'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Apertura jornada</td>
            <td>{{ $d['apertura_jornada_en'] ?? '—' }}</td>
            <td class="lbl">Cierre jornada</td>
            <td>{{ $d['cierre_jornada_en'] }}</td>
        </tr>
        <tr>
            <td class="lbl">Usuario</td>
            <td colspan="3">{{ $d['usuario_registro'] }}</td>
        </tr>
        <tr>
            <td class="lbl">Último ID Waitry cierre anterior</td>
            <td>#{{ (int) $d['waitry_order_id_anterior'] }}</td>
            <td class="lbl">Rango incluido</td>
            <td>{{ $d['rango_etiqueta'] }}</td>
        </tr>
        <tr>
            <td class="lbl">Próximo ID (día siguiente)</td>
            <td colspan="3"><strong>#{{ (int) $d['proximo_waitry_order_id'] }}</strong>
                <span class="muted">(consultar órdenes con id &gt; {{ (int) ($d['waitry_order_id_hasta'] ?? $d['waitry_order_id_anterior']) }})</span>
            </td>
        </tr>
        <tr class="total-grande">
            <td class="lbl">Órdenes en ventana</td>
            <td class="num">{{ (int) $d['cantidad_lineas'] }}</td>
            <td class="lbl">Ingreso tótem (cobrado Waitry)</td>
            <td class="num">$ {{ number_format((float) ($totalGeneral['total_ingreso'] ?? 0), 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td class="lbl">Discrepancias</td>
            <td class="num">{{ (int) ($d['cantidad_discrepancias'] ?? 0) }}</td>
            <td class="lbl">Impagas / pagadas / fact. ERP</td>
            <td class="num">{{ (int) $d['cantidad_impagas_waitry'] }} / {{ (int) $d['cantidad_pagadas_waitry'] }} / {{ (int) $d['cantidad_facturadas_erp'] }}</td>
        </tr>
    </table>

    <h2>Totales por tótem y medio de pago (totalizado)</h2>
    <p class="muted" style="margin:0 0 6px;">
        Incluye comandas cobradas en tótem entre apertura y cierre de la jornada (fecha/hora real Waitry).
    </p>

    @forelse ($porTotem as $totem)
        <div class="bloque-totem">
            <table>
                <tr class="total-grande">
                    <td class="lbl" colspan="2">
                        {{ $totem['ubicacion_nombre'] ?? 'Tótem' }}
                        @if (!empty($totem['detalle']))
                            — {{ $totem['detalle'] }}
                        @endif
                        @if (!empty($totem['waitry_table_id']))
                            <span class="muted">(tableId {{ (int) $totem['waitry_table_id'] }})</span>
                        @endif
                    </td>
                    <td class="num">{{ (int) ($totem['cantidad_ordenes'] ?? 0) }} ord.</td>
                    <td class="num">$ {{ number_format((float) ($totem['total_ingreso'] ?? 0), 2, ',', '.') }}</td>
                </tr>
                @forelse ($totem['por_medio_pago'] ?? [] as $medio)
                    <tr>
                        <td class="lbl" style="padding-left:14px;">{{ $medio['etiqueta'] ?? '—' }}</td>
                        <td>{{ $medio['cuentacaja_label'] ?? '—' }}</td>
                        <td class="num">{{ (int) ($medio['cantidad'] ?? 0) }}</td>
                        <td class="num">{{ number_format((float) ($medio['total'] ?? 0), 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted" style="padding-left:14px;">Sin ingresos cobrados en tótem en este equipo.</td></tr>
                @endforelse
            </table>
        </div>
    @empty
        <p class="muted">No hay tótems configurados o no hubo ingresos cobrados en Waitry en la ventana.</p>
    @endforelse

    @php $mediosGlobal = $totalGeneral['por_medio_pago'] ?? []; @endphp
    @if ($mediosGlobal !== [])
        <h2>Total general (todos los tótems)</h2>
        <table>
            <thead>
                <tr>
                    <th>Medio</th>
                    <th>Cuenta caja</th>
                    <th class="num">Cantidad</th>
                    <th class="num">Total ingreso</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($mediosGlobal as $medio)
                    <tr class="total-grande">
                        <td>{{ $medio['etiqueta'] ?? '—' }}</td>
                        <td>{{ $medio['cuentacaja_label'] ?? '—' }}</td>
                        <td class="num">{{ (int) ($medio['cantidad'] ?? 0) }}</td>
                        <td class="num">{{ number_format((float) ($medio['total'] ?? 0), 2, ',', '.') }}</td>
                    </tr>
                @endforeach
                <tr class="total-grande">
                    <td colspan="2"><strong>Total ingreso tótem</strong></td>
                    <td class="num">{{ (int) ($totalGeneral['cantidad_ordenes'] ?? 0) }}</td>
                    <td class="num"><strong>{{ number_format((float) ($totalGeneral['total_ingreso'] ?? 0), 2, ',', '.') }}</strong></td>
                </tr>
            </tbody>
        </table>
    @endif

    @php
        $conciliacionIz = $d['conciliacion_informe_z'] ?? null;
        $informeZCargado = ! empty($d['informe_z_cargado']);
        $informeZMeta = is_array($d['informe_z'] ?? null) ? $d['informe_z'] : null;
    @endphp
    <h2>Conciliación Informe Z (tótem físico)</h2>
    @if (! $informeZCargado || $conciliacionIz === null)
        <p class="muted">Informe Z no cargado en el sistema. Regístrelo desde Gastronomía → Jornada para comparar con los totales anteriores.</p>
    @else
        @if (! empty($conciliacionIz['ok']))
            <div class="ok-box">Informe Z cuadra con el sistema (tolerancia $ {{ number_format((float) ($conciliacionIz['tolerancia'] ?? 0), 2, ',', '.') }}).</div>
        @else
            <p class="muted" style="color:#922b21;margin:0 0 6px;">
                Hay diferencias entre Informe Z y totales del sistema (tolerancia $ {{ number_format((float) ($conciliacionIz['tolerancia'] ?? 0), 2, ',', '.') }}).
            </p>
        @endif
        @if ($informeZMeta && ! empty($informeZMeta['informe_z_en']))
            <p class="muted" style="margin:0 0 6px;">
                Cargado: {{ $informeZMeta['informe_z_en'] }}
                @if (! empty($informeZMeta['usuario_nombre']))
                    — {{ $informeZMeta['usuario_nombre'] }}
                @endif
            </p>
        @endif
        @foreach ($conciliacionIz['totems'] ?? [] as $bloqueIz)
            <div class="bloque-totem">
                <table>
                    <tr class="total-grande">
                        <td colspan="3">
                            {{ $bloqueIz['ubicacion_nombre'] ?? 'Tótem' }}
                            @if (!empty($bloqueIz['detalle']))
                                — {{ $bloqueIz['detalle'] }}
                            @endif
                            @if (!empty($bloqueIz['waitry_table_id']))
                                <span class="muted">(tableId {{ (int) $bloqueIz['waitry_table_id'] }})</span>
                            @endif
                            @if (empty($bloqueIz['ok']))
                                <span style="color:#922b21;"> — DIFERENCIA</span>
                            @endif
                        </td>
                        <td class="num">
                            Sist. $ {{ number_format((float) ($bloqueIz['total_sistema'] ?? 0), 2, ',', '.') }}
                            / Z $ {{ number_format((float) ($bloqueIz['total_informe_z'] ?? 0), 2, ',', '.') }}
                        </td>
                    </tr>
                    <tr>
                        <th>Medio</th>
                        <th class="num">Sistema</th>
                        <th class="num">Informe Z</th>
                        <th class="num">Diferencia</th>
                    </tr>
                    @foreach ($bloqueIz['lineas'] ?? [] as $lnIz)
                        <tr @if (empty($lnIz['ok'])) class="fila-discrepancia" @endif>
                            <td>{{ $lnIz['etiqueta'] ?? '—' }}</td>
                            <td class="num">{{ number_format((float) ($lnIz['monto_sistema'] ?? 0), 2, ',', '.') }}</td>
                            <td class="num">{{ number_format((float) ($lnIz['monto_informe_z'] ?? 0), 2, ',', '.') }}</td>
                            <td class="num">{{ number_format((float) ($lnIz['diferencia'] ?? 0), 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endforeach
    @endif

    <h2>Auditoría — discrepancias</h2>
    @if (! $hayDiscrepancias)
        <div class="ok-box">Sin discrepancias detectadas en este cierre.</div>
    @else
        @php
            $huecosSecuencia = $auditoria['ids_huecos_secuencia'] ?? $auditoria['ids_gap_sin_recuperar'] ?? [];
            $suplementados = $auditoria['ids_suplementados_erp'] ?? [];
        @endphp
        @if ($huecosSecuencia !== [] || $suplementados !== [])
            <table>
                @if ($huecosSecuencia !== [])
                    <tr>
                        <td class="lbl">Huecos en secuencia Waitry</td>
                        <td colspan="3" style="color:#922b21;">
                            {{ implode(', ', array_map(fn ($id) => '#'.$id, array_slice($huecosSecuencia, 0, 60))) }}
                            @if (count($huecosSecuencia) > 60) … @endif
                            <br><small class="muted">Pendiente de auditoría del día (no se consultó getOrdersPOS en el cierre).</small>
                        </td>
                    </tr>
                @endif
                @if ($suplementados !== [])
                    <tr>
                        <td class="lbl">Suplementadas desde ERP</td>
                        <td colspan="3" class="muted">
                            {{ implode(', ', array_map(fn ($id) => '#'.$id, array_slice($suplementados, 0, 40))) }}
                            @if (count($suplementados) > 40) … @endif
                        </td>
                    </tr>
                @endif
            </table>
        @endif

        @if ($lineas === [])
            @if ($huecosSecuencia === [])
                <p class="muted">Hay alertas de sincronización; no hay filas de detalle adicionales.</p>
            @endif
        @else
            <p class="muted" style="margin:0 0 6px;">Solo órdenes que requieren revisión (no se listan las conciliadas correctamente).</p>
            @if (!empty($d['detalle_truncado']))
                <p class="muted" style="color:#922b21;">Listado parcial por límite de filas en PDF.</p>
            @endif
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Motivo</th>
                        <th>Papelito</th>
                        <th>Fecha pedido</th>
                        <th class="num">Total</th>
                        <th>Pagada</th>
                        <th>ERP</th>
                        <th>Factura</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lineas as $ln)
                        <tr class="fila-discrepancia">
                            <td>{{ (int) ($ln['waitry_order_id'] ?? 0) }}</td>
                            <td>{{ $ln['motivo_discrepancia'] ?? '—' }}</td>
                            <td>{{ $ln['display_id'] ?? '' }}</td>
                            <td>{{ $ln['placed_at_fmt'] ?? '' }}</td>
                            <td class="num">{{ number_format((float) ($ln['total'] ?? 0), 2, ',', '.') }}</td>
                            <td>
                                @if (($ln['paid_waitry'] ?? null) === true)
                                    Sí
                                @elseif (($ln['paid_waitry'] ?? null) === false)
                                    No
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ !empty($ln['importada_erp']) ? 'Sí' : 'No' }}</td>
                            <td>{{ $ln['venta_codigo'] ?? '' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif
</body>
</html>
