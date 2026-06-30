@php use App\Models\Contable\BienUso; @endphp
<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="14" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="14"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de bienes de uso</h2></td>
        </tr>
    </tbody>
    <thead>
        <tr>
            <th>ID</th>
            <th>UID</th>
            <th>C&oacute;d. inv.</th>
            <th>Empresa</th>
            <th>Hostname</th>
            <th>IP</th>
            <th>Modelo</th>
            <th>Vendor</th>
            <th>Tema</th>
            <th>N&ordm; serie</th>
            <th>Estado</th>
            <th>C. costo</th>
            <th>Tipo bien</th>
            <th>Observaciones</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($datas as $data)
            <tr>
                <td>{{ $data->id }}</td>
                <td>{{ $data->uid }}</td>
                <td>{{ $data->codigo_inventario }}</td>
                <td>{{ $data->empresa->nombre ?? '' }}</td>
                <td>{{ $data->hostname }}</td>
                <td>{{ $data->ip }}</td>
                <td>{{ $data->modelo }}</td>
                <td>{{ $data->vendor }}</td>
                <td>{{ $data->tema }}</td>
                <td>{{ $data->numero_serie }}</td>
                <td>{{ BienUso::labelEstado($data->estado) }}</td>
                <td>{{ $data->centrocostos->codigo ?? '' }} — {{ $data->centrocostos->nombre ?? '' }}</td>
                <td>{{ BienUso::labelTipoBien($data->tipo_bien) }}</td>
                <td>{{ $data->observaciones }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
