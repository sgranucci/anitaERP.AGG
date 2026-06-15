@php
    $separatorPartidas = $separatorPartidas ?? '<br>';
    $excelSinFormato = $separatorPartidas === "\n";

    $formatearMonto = static function ($valor) use ($excelSinFormato) {
        if ($valor === null || $valor === '') {
            return '';
        }

        return $excelSinFormato
            ? $valor
            : number_format((float) $valor, 2, '.', ',');
    };
@endphp
<thead>
    <tr>
        <th>ID</th>
        <th>Empresa</th>
        <th>Presupuesto</th>
        <th>Centro de Costo</th>
        <th>Nombre</th>
        <th>Detalle</th>
        <th>Codigo de Proyecto</th>
        <th>Año</th>
        <th>MES</th>
        <th>N° Pro</th>
        <th>Estado</th>
        <th>Moneda</th>
        <th class="num">Monto CAPEX</th>
        <th class="num">Importe OC</th>
        <th class="num">Importe FC</th>
        <th class="num">Cotización FC</th>
        <th>Cuenta contable</th>
        <th class="num">Importe pago</th>
        <th>OC</th>
        <th>FC</th>
        <th>PAGO</th>
        <th>Partidas</th>
    </tr>
</thead>
<tbody>
    @forelse ($filas as $fila)
        <tr>
            <td>
                @include('presupuesto.partials.celda_link_consulta', [
                    'mostrarLinks' => $mostrarLinks ?? false,
                    'puede' => $puede_ver_capex ?? false,
                    'id' => $fila['id'] ?? 0,
                    'routeName' => 'editar_capex',
                    'texto' => $fila['id'] ?? '',
                ])
            </td>
            <td>
                @include('presupuesto.partials.celda_link_consulta', [
                    'mostrarLinks' => $mostrarLinks ?? false,
                    'puede' => $puede_ver_empresa ?? false,
                    'id' => $fila['empresa_id'] ?? 0,
                    'routeName' => 'editar_empresa',
                    'texto' => $fila['empresa'] ?? '',
                ])
            </td>
            <td>
                @include('presupuesto.partials.celda_link_consulta', [
                    'mostrarLinks' => $mostrarLinks ?? false,
                    'puede' => $puede_ver_presupuesto ?? false,
                    'id' => $fila['presupuesto_id'] ?? 0,
                    'routeName' => 'editar_presupuesto',
                    'texto' => $fila['presupuesto'] ?? '',
                ])
            </td>
            <td>
                @include('presupuesto.partials.celda_link_consulta', [
                    'mostrarLinks' => $mostrarLinks ?? false,
                    'puede' => $puede_ver_centrocosto ?? false,
                    'id' => $fila['centrocosto_id'] ?? 0,
                    'routeName' => 'editar_centrocosto',
                    'texto' => $fila['centrocosto'] ?? '',
                ])
            </td>
            <td>
                @include('presupuesto.partials.celda_link_consulta', [
                    'mostrarLinks' => $mostrarLinks ?? false,
                    'puede' => $puede_ver_capex ?? false,
                    'id' => $fila['id'] ?? 0,
                    'routeName' => 'editar_capex',
                    'texto' => $fila['nombre'] ?? '',
                ])
            </td>
            <td>{{ $fila['detalle'] ?? '' }}</td>
            <td>
                @include('presupuesto.partials.celda_link_consulta', [
                    'mostrarLinks' => $mostrarLinks ?? false,
                    'puede' => $puede_ver_capex ?? false,
                    'id' => $fila['id'] ?? 0,
                    'routeName' => 'editar_capex',
                    'texto' => $fila['codigoproyecto'] ?? '',
                ])
            </td>
            <td>{{ $fila['anio'] ?? '' }}</td>
            <td>{{ $fila['mes'] ?? '' }}</td>
            <td>
                @include('presupuesto.partials.celda_link_consulta', [
                    'mostrarLinks' => $mostrarLinks ?? false,
                    'puede' => $puede_ver_capex ?? false,
                    'id' => $fila['id'] ?? 0,
                    'routeName' => 'editar_capex',
                    'texto' => $fila['nro_proyecto'] ?? '',
                ])
            </td>
            <td>{{ $fila['estado'] ?? '' }}</td>
            <td>{{ $fila['moneda'] ?? '' }}</td>
            <td class="num">{{ $formatearMonto($fila['monto_capex'] ?? null) }}</td>
            <td class="num">{{ $formatearMonto($fila['importe_oc'] ?? null) }}</td>
            <td class="num">{{ $formatearMonto($fila['importe_fc'] ?? null) }}</td>
            <td class="num">{{ $formatearMonto($fila['cotizacion_fc'] ?? null) }}</td>
            <td>{{ $fila['cuenta_contable'] ?? '' }}</td>
            <td class="num">{{ $formatearMonto($fila['importe_pago'] ?? null) }}</td>
            <td>{{ $fila['oc'] ?? '' }}</td>
            <td>{{ $fila['fc'] ?? '' }}</td>
            <td>{{ $fila['pago'] ?? '' }}</td>
            <td class="cell-partidas">
                @if ($separatorPartidas === "\n")
                    {{ $fila['partidas'] ?? '' }}
                @else
                    {!! nl2br(e($fila['partidas'] ?? '')) !!}
                @endif
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="22" class="text-center text-muted">Sin registros para los filtros indicados.</td>
        </tr>
    @endforelse
</tbody>
