@php
    $separatorPartidas = $separatorPartidas ?? '<br>';
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
        <th class="num">Importe</th>
        <th>OC</th>
        <th>FC</th>
        <th>PAGO</th>
        <th>Partidas</th>
    </tr>
</thead>
<tbody>
    @forelse ($filas as $fila)
        <tr>
            <td>{{ $fila['id'] ?? '' }}</td>
            <td>{{ $fila['empresa'] ?? '' }}</td>
            <td>{{ $fila['presupuesto'] ?? '' }}</td>
            <td>{{ $fila['centrocosto'] ?? '' }}</td>
            <td>{{ $fila['nombre'] ?? '' }}</td>
            <td>{{ $fila['detalle'] ?? '' }}</td>
            <td>{{ $fila['codigoproyecto'] ?? '' }}</td>
            <td>{{ $fila['anio'] ?? '' }}</td>
            <td>{{ $fila['mes'] ?? '' }}</td>
            <td>{{ $fila['nro_proyecto'] ?? '' }}</td>
            <td>{{ $fila['estado'] ?? '' }}</td>
            <td>{{ $fila['moneda'] ?? '' }}</td>
            <td class="num">{{ $fila['importe'] ?? '' }}</td>
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
            <td colspan="17" class="text-center text-muted">Sin registros para los filtros indicados.</td>
        </tr>
    @endforelse
</tbody>
