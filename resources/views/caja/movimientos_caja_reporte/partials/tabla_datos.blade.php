@php
    $puedeIe = $puede_ver_ingresoegreso ?? false;
    $puedeCob = $puede_ver_cobranza ?? false;
    $puedeOpp = $puede_ver_pagoproveedor ?? false;
    $puedeCuenta = $puede_ver_cuentacaja ?? false;
    $filasTabla = $filas ?? [];
@endphp
<table class="table table-bordered table-hover table-sm mb-0" id="tabla-movimientos-caja-reporte" style="font-size: 0.85rem;">
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            <th>Fecha</th>
            <th>Nro.</th>
            <th>Tip</th>
            <th>Código</th>
            <th>Cliente / Proveedor</th>
            <th>Concepto</th>
            <th>Detalle</th>
            <th>Estado</th>
            <th class="text-right">Ingreso</th>
            <th class="text-right">Egreso</th>
            <th>Empresa</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($filasTabla as $fila)
        @php $tipoFila = $fila['tipo_fila'] ?? 'dato'; @endphp
        @if ($tipoFila === 'grupo')
            <tr class="mov-caja-grupo">
                <td colspan="11">
                    <strong>Cuenta:
                        @if ($puedeCuenta && (int) ($fila['cuenta_id'] ?? 0) > 0)
                            <a href="{{ route('editar_cuentacaja', ['id' => $fila['cuenta_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                               class="text-primary" target="_blank" rel="noopener">{{ $fila['cuenta_etiqueta'] ?? '' }}</a>
                        @else
                            {{ $fila['cuenta_etiqueta'] ?? '' }}
                        @endif
                    </strong>
                </td>
            </tr>
        @elseif ($tipoFila === 'total_cuenta' || $tipoFila === 'total_general')
            <tr class="{{ $tipoFila === 'total_general' ? 'mov-caja-total-general' : 'mov-caja-total' }}">
                <td colspan="8" class="text-right">{{ $fila['cuenta_etiqueta'] ?? ($fila['clipro_nombre'] ?? 'Total') }}</td>
                <td class="text-right">{{ number_format((float) ($fila['ingreso'] ?? 0), 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float) ($fila['egreso'] ?? 0), 2, ',', '.') }}</td>
                <td></td>
            </tr>
        @else
            <tr>
                <td>{{ $fila['fecha'] ?? '' }}</td>
                <td>
                    @php
                        $linkTipo = $fila['link_tipo'] ?? 'ingresoegreso';
                        $nro = $fila['numero'] ?? '';
                        $url = null;
                        if ($linkTipo === 'cobranza' && $puedeCob && (int) ($fila['cobranza_id'] ?? 0) > 0) {
                            $url = route('editar_cobranza', ['id' => $fila['cobranza_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']);
                        } elseif ($linkTipo === 'pagoproveedor' && $puedeOpp && (int) ($fila['pagoproveedor_id'] ?? 0) > 0) {
                            $url = route('editar_pagoproveedor', ['id' => $fila['pagoproveedor_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']);
                        } elseif ($puedeIe && (int) ($fila['id'] ?? 0) > 0) {
                            $url = route('editar_ingresoegreso', ['id' => $fila['id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']);
                        }
                    @endphp
                    @if ($url)
                        <a href="{{ $url }}" class="text-primary" target="_blank" rel="noopener">{{ $nro }}</a>
                    @else
                        {{ $nro }}
                    @endif
                </td>
                <td title="{{ $fila['tipo_nombre'] ?? '' }}">{{ $fila['tipo_abrev'] ?? '' }}</td>
                <td>{{ $fila['clipro_codigo'] ?? '' }}</td>
                <td>{{ $fila['clipro_nombre'] ?? '' }}</td>
                <td>{{ $fila['concepto'] ?? '' }}</td>
                <td>{{ \Illuminate\Support\Str::limit($fila['detalle'] ?? '', 60) }}</td>
                <td>{{ $fila['estado_nombre'] ?? '' }}</td>
                <td class="text-right">
                    @if ((float) ($fila['ingreso'] ?? 0) > 0)
                        {{ number_format((float) $fila['ingreso'], 2, ',', '.') }}
                    @endif
                </td>
                <td class="text-right">
                    @if ((float) ($fila['egreso'] ?? 0) > 0)
                        {{ number_format((float) $fila['egreso'], 2, ',', '.') }}
                    @endif
                </td>
                <td>{{ $fila['nombreempresa'] ?? '' }}</td>
            </tr>
        @endif
    @empty
        <tr>
            <td colspan="11" class="text-center text-muted py-4">Sin movimientos para los filtros aplicados.</td>
        </tr>
    @endforelse
    </tbody>
</table>
