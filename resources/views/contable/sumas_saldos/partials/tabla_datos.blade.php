@php
    $multiempresa = ! empty($multiempresa);
    $puedeVerCuenta = ! empty($puede_ver_cuenta);
    $tot = $totales ?? [];
    // Como l-sumsal.c (fl_imp_total): solo si hubo cuentas/movimientos en el filtro.
    $mostrarTotalGeneral = (int) ($tot['cuentas'] ?? 0) > 0
        || abs((float) ($tot['debe'] ?? 0)) > 0.005
        || abs((float) ($tot['haber'] ?? 0)) > 0.005;
@endphp
<thead>
<tr>
    <th style="width: 12%;">Nro. Cta.</th>
    <th style="width: {{ $multiempresa ? '24%' : '28%' }};">Descripción</th>
    <th class="text-right" style="width: 12%;">Débitos</th>
    <th class="text-right" style="width: 12%;">Créditos</th>
    <th class="text-right" style="width: 12%;">Saldo período</th>
    <th class="text-right" style="width: 12%;">Saldo mes ant.</th>
    <th class="text-right" style="width: 12%;">Saldo ejercicio</th>
    @if ($multiempresa)
        <th style="width: 8%;">Empresa</th>
    @endif
</tr>
</thead>
<tbody>
@forelse ($filas as $fila)
    @if (($fila['tipo_fila'] ?? '') === 'header_empresa')
        <tr class="table-secondary">
            <td colspan="{{ $multiempresa ? 8 : 7 }}" class="font-weight-bold">
                {{ $fila['nombreempresa'] ?? $fila['nombre'] ?? '' }}
            </td>
        </tr>
    @else
        <tr>
            <td>
                @if ($puedeVerCuenta && ! empty($fila['cuentacontable_id']))
                    <a class="text-primary" target="_blank" rel="noopener"
                        href="{{ route('editar_cuentacontable', [
                            'id' => $fila['cuentacontable_id'],
                            'origen' => 'modal_consulta',
                            'vista' => 'consulta',
                        ]) }}">
                        {{ $fila['codigo_fmt'] ?? '' }}
                    </a>
                @else
                    {{ $fila['codigo_fmt'] ?? '' }}
                @endif
            </td>
            <td>{{ $fila['nombre'] ?? '' }}</td>
            <td class="text-right">{{ number_format((float) ($fila['debe'] ?? 0), 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) ($fila['haber'] ?? 0), 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) ($fila['saldo_periodo'] ?? 0), 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) ($fila['saldo_mes_anterior'] ?? 0), 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) ($fila['saldo_ejercicio'] ?? 0), 2, ',', '.') }}</td>
            @if ($multiempresa)
                <td>{{ $fila['empresa_id'] ?? '' }}</td>
            @endif
        </tr>
    @endif
@empty
    <tr>
        <td colspan="{{ $multiempresa ? 8 : 7 }}" class="text-center text-muted">Sin cuentas para el filtro.</td>
    </tr>
@endforelse
</tbody>
@if ($mostrarTotalGeneral)
    {{-- Anita l-sumsal.c: fila "Total general" con los 5 importes --}}
    <tfoot>
        <tr class="font-weight-bold" style="background-color: #e9ecef; border-top: 2px solid #6c757d;">
            <td colspan="2">Total general</td>
            <td class="text-right">{{ number_format((float) ($tot['debe'] ?? 0), 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) ($tot['haber'] ?? 0), 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) ($tot['saldo_periodo'] ?? 0), 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) ($tot['saldo_mes_anterior'] ?? 0), 2, ',', '.') }}</td>
            <td class="text-right">{{ number_format((float) ($tot['saldo_ejercicio'] ?? 0), 2, ',', '.') }}</td>
            @if ($multiempresa)
                <td></td>
            @endif
        </tr>
    </tfoot>
@endif
