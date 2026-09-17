<table>
    <tr>
        <td colspan="7"><strong>Programas de pagos</strong></td>
    </tr>
    <tr>
        <td colspan="7">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    <thead>
        <tr>
            <th>Id</th>
            <th>Título</th>
            <th>Empresa</th>
            <th>Fecha base</th>
            <th>Desde</th>
            <th>Meses</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @foreach($datas as $item)
            <tr>
                <td>{{ $item->id }}</td>
                <td>{{ $item->titulo }}</td>
                <td>{{ $item->nombreempresa ?? ($item->empresas->nombre ?? '') }}</td>
                <td>{{ optional($item->fecha_base)->format('d/m/Y') }}</td>
                <td>{{ $item->anio_mes_inicio }}</td>
                <td>{{ $item->cantidad_meses }}</td>
                <td>{{ $item->estado }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
