<h2>Clientes</h2>
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Nombre</th>
            <th>Vendedor</th>
            <th>C.U.I.T.</th>
            <th>Domicilio</th>
            <th>Localidad</th>
            <th>Provincia</th>
            <th>C&oacute;d.</th>
            @if (config('app.empresa') == 'EL BIERZO')
                <th>Reparto</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach ($clientes as $cliente)
            <tr>
                <td>{{ $cliente->id }}</td>
                <td>{{ $cliente->nombre }}</td>
                <td>{{ trim(($cliente->cvendedor ?? '').($cliente->nombrevendedor ? '-'.$cliente->nombrevendedor : '')) }}</td>
                <td>{{ $cliente->numerodocumento }}</td>
                <td>{{ $cliente->domicilio }}</td>
                <td>{{ $cliente->nombrelocalidad ?? '' }}</td>
                <td>{{ $cliente->nombreprovincia ?? '' }}</td>
                <td>{{ $cliente->codigo }}</td>
                @if (config('app.empresa') == 'EL BIERZO')
                    <td>{{ $cliente->ctransporte }}-{{ $cliente->nombretransporte }}</td>
                @endif
            </tr>
        @endforeach
    </tbody>
</table>
