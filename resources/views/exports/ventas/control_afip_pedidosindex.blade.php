<table>
    @if (! empty($reservarFilaLogoExcel) && ! empty($logosCabecera))
        <tr>
            <td colspan="10">
                @foreach ($logosCabecera as $logo)
                    @if (! empty($logo))
                        <img src="{{ $logo }}" height="40" alt="logo">
                    @endif
                @endforeach
            </td>
        </tr>
    @endif
    <tr>
        <td colspan="10">
            Clientes con problemas de AFIP —
            control {{ isset($informe['fecha_control']) ? \Carbon\Carbon::parse($informe['fecha_control'])->format('d/m/Y') : '' }}
            · pedidos {{ isset($informe['fecha_desde']) ? \Carbon\Carbon::parse($informe['fecha_desde'])->format('d/m/Y') : '' }}
            → {{ isset($informe['fecha_hasta']) ? \Carbon\Carbon::parse($informe['fecha_hasta'])->format('d/m/Y') : '' }}
            · {{ count($filas ?? []) }} registro(s)
        </td>
    </tr>
    <thead>
        <tr>
            <th>Código</th>
            <th>Cliente</th>
            <th>CUIT</th>
            <th>Cond. IVA</th>
            <th>Cód. vend.</th>
            <th>Vendedor</th>
            <th>Email vend.</th>
            <th>Últ. FAC</th>
            <th>Fuente</th>
            <th>Motivo</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            <tr>
                <td>{{ $fila['codigo'] ?? '' }}</td>
                <td>{{ $fila['nombre'] ?? '' }}</td>
                <td>{{ $fila['cuit'] ?? '' }}</td>
                <td>{{ $fila['condicioniva'] ?? '' }}</td>
                <td>{{ $fila['vendedor_codigo'] ?? '' }}</td>
                <td>{{ $fila['vendedor_nombre'] ?? '' }}</td>
                <td>{{ $fila['vendedor_email'] ?? '' }}</td>
                <td>{{ isset($fila['ultima_fac']) ? \Carbon\Carbon::parse($fila['ultima_fac'])->format('d/m/Y') : '' }}</td>
                <td>{{ $fila['fuente'] ?? '' }}</td>
                <td>{{ $fila['motivo'] ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
