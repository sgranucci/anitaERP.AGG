<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="10" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="10"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de clientes VIP caja</h2></td>
        </tr>
    </tbody>
    <thead>
        <tr>
            <th>ID</th>
            <th>Nro Anita</th>
            <th>Documento</th>
            <th>Apellido</th>
            <th>Nombre</th>
            <th>Nickname</th>
            <th>Localidad</th>
            <th>Empresa</th>
            <th>F. alta</th>
            <th>F. mod.</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($datas as $data)
            <tr>
                <td>{{ $data->id }}</td>
                <td>{{ $data->numeroid }}</td>
                <td>{{ $data->nrodocumento }}</td>
                <td>{{ $data->apellido }}</td>
                <td>{{ $data->nombre }}</td>
                <td>{{ $data->nickname }}</td>
                <td>{{ $data->localidad }}</td>
                <td>{{ $data->nombreempresa ?? ($data->empresa->nombre ?? '') }}</td>
                <td>{{ $data->fecha_alta_formato }}</td>
                <td>{{ $data->fecha_mod_formato }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
