@php
    $reservarFilaLogoExcel = $reservarFilaLogoExcel ?? false;
    $totalFilas = is_countable($datas) ? count($datas) : 0;
@endphp
<table>
    <thead>
        @if ($reservarFilaLogoExcel)
            <tr><td colspan="8" style="height: 52px;"></td></tr>
        @endif
        <tr>
            <td colspan="8"><strong>Planos de mesas — generado {{ date('d/m/Y H:i') }} · {{ $totalFilas }} registro(s)</strong></td>
        </tr>
        <tr>
            <th>ID</th>
            <th>Código</th>
            <th>Nombre</th>
            <th>Empresa</th>
            <th>Sala</th>
            <th>Mesas</th>
            <th>Orden</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($datas as $data)
            <tr>
                <td>{{ $data->id }}</td>
                <td>{{ $data->codigo }}</td>
                <td>{{ $data->nombre }}</td>
                <td>{{ optional($data->empresa)->nombre }}</td>
                <td>{{ trim((optional($data->sala)->codigo ?? '').' '.(optional($data->sala)->nombre ?? '')) }}</td>
                <td>{{ $data->cantidadMesas() }}</td>
                <td>{{ (int) $data->orden }}</td>
                <td>{{ $data->activo ? 'Activo' : 'Inactivo' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
