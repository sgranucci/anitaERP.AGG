@php
    $reservarFilaLogoExcel = $reservarFilaLogoExcel ?? false;
@endphp
<table>
    @if ($reservarFilaLogoExcel)
        <tr><td colspan="10" style="height: 52px;"></td></tr>
    @endif
    <tr>
        <td colspan="10"><strong>Libro de Sueldos Digital — presentaciones</strong></td>
    </tr>
    <thead>
        <tr>
            <th>Período</th>
            <th>Nro AFIP</th>
            <th>ID</th>
            <th>Envío</th>
            <th>Tipo</th>
            <th>Liquidación</th>
            <th>Estado</th>
            <th>Reg. 04</th>
            <th>Fecha pago</th>
            <th>Empresa</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($datas as $p)
            <tr>
                <td>{{ $p->periodoLabel() }}</td>
                <td>{{ $p->nro_liquidacion_afip }}</td>
                <td>{{ $p->id }}</td>
                <td>{{ $p->identificacion }}</td>
                <td>{{ $p->tipo_liquidacion }}</td>
                <td>{{ optional($p->liquidacion)->numero }} {{ optional($p->liquidacion)->descripcion }}</td>
                <td>{{ $p->estadoLabel() }}</td>
                <td>{{ $p->cantidad_registros_04 }}</td>
                <td>{{ optional($p->fecha_pago)->format('d/m/Y') }}</td>
                <td>{{ $p->nombreempresa }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
