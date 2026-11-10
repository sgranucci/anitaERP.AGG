@php
    $pesos = fn ($importe) => $importe === null || $importe === '' ? '' : number_format((float) $importe, 2, ',', '.');
    $cant = fn ($importe) => $importe === null || $importe === '' ? '' : number_format((float) $importe, 2, ',', '.');
    $enteros = fn ($importe) => $importe === null || $importe === '' ? '' : number_format((int) $importe, 0, ',', '.');
@endphp
<div class="card card-outline card-info mb-3">
    <div class="card-header py-2">
        <h3 class="card-title mb-0"><i class="fa fa-university"></i> Anexo VIII — Estado de bóveda</h3>
    </div>
    <div class="card-body pb-2">
        @include('caja.tesoreria_crown.jornada.partials.anexos_tabs')
        <p class="text-muted small mb-2">
            Formato de <code>l-estboveda.c</code>: primero stock de fichas (informativo, no entra al total),
            después efectivo (arqueo del día + slots + drops) + resumen final + propina + FX.
            Fuentes ERP: stock del día / arqueo conforme, bóveda, propina_dia / mesas F, monextr.
            <strong>Diferencia tesorería</strong> va dentro del resumen final (movimientos 35/36).
        </p>
        @if ($anexo8 === null)
            <div class="alert alert-warning mb-0">No se pudo armar el anexo VIII. {{ $anexo8Error }}</div>
        @else
            @foreach ($anexo8['avisos'] as $aviso)
                <div class="alert alert-warning py-2">{{ $aviso }}</div>
            @endforeach
            @php
                $c = $anexo8['componentes'] ?? null;
                $fichas = $anexo8['fichas'] ?? null;
            @endphp
            @if (is_array($fichas) && ($fichas['filas'] ?? []) !== [])
                <h5 class="mt-1 mb-2">Stock de fichas <span class="text-muted small">(no entra al total del VIII)</span></h5>
                <div class="table-responsive mb-2">
                    <table class="table table-sm table-bordered mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>Serie</th>
                                <th>Descripción</th>
                                <th class="text-right">Valor</th>
                                <th class="text-right">Cant. fichas</th>
                                <th class="text-right">Monto fichas</th>
                                <th class="text-right">Cant. placas</th>
                                <th class="text-right">Monto placas</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($fichas['filas'] as $fila)
                                <tr>
                                    <td>{{ $fila['serie'] }}</td>
                                    <td>{{ $fila['descripcion'] }}</td>
                                    <td class="text-right">{{ $pesos($fila['valor']) }}</td>
                                    @if ($fila['canje'])
                                        <td class="text-right"></td>
                                        <td class="text-right"></td>
                                        <td class="text-right">{{ $enteros($fila['cantidad']) }}</td>
                                        <td class="text-right">{{ $pesos($fila['monto']) }}</td>
                                    @else
                                        <td class="text-right">{{ $enteros($fila['cantidad']) }}</td>
                                        <td class="text-right">{{ $pesos($fila['monto']) }}</td>
                                        <td class="text-right"></td>
                                        <td class="text-right"></td>
                                    @endif
                                </tr>
                            @endforeach
                            <tr style="font-weight:bold;background:#f5f5f5;">
                                <td colspan="3">Total</td>
                                <td class="text-right">{{ $enteros($fichas['cant_fichas']) }}</td>
                                <td class="text-right">{{ $pesos($fichas['monto_fichas']) }}</td>
                                <td class="text-right">{{ $enteros($fichas['cant_placas']) }}</td>
                                <td class="text-right">{{ $pesos($fichas['monto_placas']) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="mb-1">Total existente en conversión valorizado: <strong>{{ $pesos($fichas['conversion_valorizada']) }}</strong></p>
                <p class="mb-1">Total en poder del público valorizado: <strong>{{ $pesos($fichas['poder_valorizado']) }}</strong></p>
                <p class="mb-3">Total en poder del público diario: <strong>{{ $pesos($fichas['diario_valorizado']) }}</strong></p>
            @endif
            @if (is_array($c))
                <div class="table-responsive mb-3">
                    <table class="table table-sm table-bordered mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr data-tc-fija="1"><th colspan="2">Componentes del total (ítem 130)</th></tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Pesos en bóveda</td>
                                <td class="text-right">{{ $pesos($c['pesos'] ?? 0) }}</td>
                            </tr>
                            <tr>
                                <td>Slots (recuento, no DROP)</td>
                                <td class="text-right">{{ $pesos($c['slots'] ?? 0) }}</td>
                            </tr>
                            <tr>
                                <td>Drops de mesa</td>
                                <td class="text-right">{{ $pesos($c['drops_mesa'] ?? 0) }}</td>
                            </tr>
                            <tr>
                                <td>
                                    Items resumen final
                                    @if (!empty($c['resumen_detalle']))
                                        <span class="text-muted small">
                                            (@foreach ($c['resumen_detalle'] as $i => $item)@if ($i > 0); @endif{{ $item['descripcion'] }} {{ $pesos($item['monto']) }}@endforeach)
                                        </span>
                                    @endif
                                </td>
                                <td class="text-right">{{ $pesos($c['resumen'] ?? 0) }}</td>
                            </tr>
                            <tr>
                                <td>Propina acumulada</td>
                                <td class="text-right">{{ $pesos($c['propina'] ?? 0) }}</td>
                            </tr>
                            @if (((float) ($c['usd_pesos'] ?? 0)) > 0.001)
                                <tr>
                                    <td>
                                        Total dolares convertidos a pesos
                                        <span class="text-muted small">EQUIVALENTE A {{ $pesos($c['usd_face'] ?? 0) }} DOLARES</span>
                                    </td>
                                    <td class="text-right">{{ $pesos($c['usd_pesos'] ?? 0) }}</td>
                                </tr>
                            @endif
                            @if (((float) ($c['eur_pesos'] ?? 0)) > 0.001)
                                <tr>
                                    <td>
                                        Total euros convertidos a pesos
                                        <span class="text-muted small">EQUIVALENTE A {{ $pesos($c['eur_face'] ?? 0) }} EUROS</span>
                                    </td>
                                    <td class="text-right">{{ $pesos($c['eur_pesos'] ?? 0) }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td>Otras monedas segun cotiz. (suma USD+EUR)</td>
                                <td class="text-right">{{ $pesos($c['moneda_extranjera'] ?? 0) }}</td>
                            </tr>
                            <tr style="font-weight:bold;background:#f5f5f5;">
                                <td>Total Anexo VIII</td>
                                <td class="text-right">{{ $pesos($anexo8['total']) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            @endif
            @forelse ($anexo8['lugares'] as $lugar)
                <h5 class="mt-2 mb-2">{{ $lugar['lugar'] }}</h5>
                @foreach ($lugar['grupos'] as $grupo)
                    <div class="tc-orden-wrap">
                        @include('caja.tesoreria_crown.jornada.partials.anexo_orden_barra')
                        <div class="table-responsive mb-3">
                            <table class="table table-sm table-bordered mb-0 tc-tabla-ordenable">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th colspan="3">{{ $grupo['titulo'] }}</th>
                                    </tr>
                                    <tr>
                                        <th>Denominación</th>
                                        <th class="text-right">Cantidad</th>
                                        <th class="text-right">Monto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($grupo['filas'] as $fila)
                                        <tr>
                                            <td>{{ $fila['denominacion'] }}</td>
                                            <td class="text-right">{{ $cant($fila['cantidad']) }}</td>
                                            <td class="text-right">{{ $pesos($fila['monto']) }}</td>
                                        </tr>
                                    @endforeach
                                    <tr style="font-weight:bold;background:#f5f5f5;" data-tc-fija="1">
                                        <td>Subtotal {{ $grupo['titulo'] }}</td>
                                        <td></td>
                                        <td class="text-right">{{ $pesos($grupo['monto']) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endforeach
                <p class="font-weight-bold">Total {{ $lugar['lugar'] }}: {{ $pesos($lugar['total']) }}</p>
            @empty
                <p class="text-muted mb-0">Sin lugares de bóveda.</p>
            @endforelse
            @if ($anexo8['lugares'] !== [])
                <p class="font-weight-bold mb-0">Total general: {{ $pesos($anexo8['total']) }}</p>
            @endif
        @endif
    </div>
</div>
