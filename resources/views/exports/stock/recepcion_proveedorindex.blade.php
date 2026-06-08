<table>
    <thead>
        <tr>
            <th colspan="10" style="font-size:14px;font-weight:bold;text-align:center;">Listado de recepciones de proveedores</th>
        </tr>
        <tr>
            <th>Nº recepción</th>
            <th>Fecha</th>
            <th>Tipo</th>
            <th>Nº OC</th>
            <th>Estado</th>
            <th>Proveedor</th>
            <th>Empresa</th>
            <th>Precio diff.</th>
            <th>Cant. diff.</th>
            <th>Resumen diferencias</th>
        </tr>
    </thead>
    <tbody>
        @foreach($datas as $row)
        <tr>
            <td>{{ $row->numerorecepcion }}</td>
            <td>{{ $row->fecha ? date('d/m/Y', strtotime($row->fecha)) : '' }}</td>
            <td>{{ $row->tipo }}</td>
            <td>{{ $row->numeroordencompra }}</td>
            <td>{{ $row->estado }}</td>
            <td>{{ $row->nombreproveedor }}</td>
            <td>{{ $row->nombreempresa }}</td>
            <td>{{ $row->fl_precio_diferencia ? 'Sí' : 'No' }}</td>
            <td>{{ $row->fl_diferencia_cantidad ? 'Sí' : 'No' }}</td>
            <td>{{ \Illuminate\Support\Str::limit($row->resumen_diferencias ?? '', 120) }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
