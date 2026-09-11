<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="8" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="8"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listas de precio de proveedores</h2></td>
        </tr>
    </tbody>
    <thead>
        <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>Nombre</th>
            <th>C&oacute;d. prov.</th>
            <th>Proveedor</th>
            <th>Moneda</th>
            <th>Estado</th>
            <th>Usuario</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($listas as $data)
            <tr>
                <td>{{ $data->id }}</td>
                <td>{{ $data->fecha ? date('d/m/Y', strtotime($data->fecha)) : '' }}</td>
                <td>{{ $data->nombre }}</td>
                <td>{{ $data->codigoproveedor ?? '' }}</td>
                <td>{{ $data->nombreproveedor }}</td>
                <td>{{ $data->abreviaturamoneda ?: ($data->nombremoneda ?? '') }}</td>
                <td>{{ $data->estado }}</td>
                <td>{{ $data->nombreusuario }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
