<table>
    <tr>
        <td colspan="10"><strong>Errores de recepción precarga (API / PDF+IA)</strong></td>
    </tr>
    <thead>
        <tr>
            <th>ID</th>
            <th>Fecha</th>
            <th>Origen</th>
            <th>Fase</th>
            <th>Nº OC</th>
            <th>CUIT proveedor</th>
            <th>CUIT empresa</th>
            <th>HTTP</th>
            <th>Mensaje</th>
            <th>Usuario</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($datas as $data)
            <tr>
                <td>{{ $data->id }}</td>
                <td>{{ optional($data->created_at)->format('d/m/Y H:i:s') }}</td>
                <td>{{ \App\Support\Compras\PrecargaRecepcionErrorRegistrar::etiquetaOrigen($data->origen) }}</td>
                <td>{{ $data->fase }}</td>
                <td>{{ $data->numero_oc }}</td>
                <td>{{ $data->cuit_proveedor }}</td>
                <td>{{ $data->cuit_empresa }}</td>
                <td>{{ $data->http_status }}</td>
                <td>{{ $data->mensaje }}</td>
                <td>{{ $data->usuario->nombre ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
