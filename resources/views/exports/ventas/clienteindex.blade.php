@php
    $esBierzo = \App\Support\Configuracion\EntornoEmpresaSupport::esElBierzo();
@endphp
<h2>Clientes</h2>
<table>
    <thead>
        <tr>
            @if ($esBierzo)
                <th>C&oacute;d.</th>
            @else
                <th>ID</th>
            @endif
            <th>Nombre</th>
            <th>Vendedor</th>
            <th>C.U.I.T.</th>
            <th>Domicilio</th>
            <th>Localidad</th>
            <th>Provincia</th>
            @if (! $esBierzo)
                <th>C&oacute;d.</th>
            @endif
            @if ($esBierzo)
                <th>Reparto</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @foreach ($clientes as $cliente)
            <tr>
                @if ($esBierzo)
                    <td>{{ $cliente->codigo }}</td>
                @else
                    <td>{{ $cliente->id }}</td>
                @endif
                <td>{{ $cliente->nombre }}</td>
                <td>{{ trim(($cliente->cvendedor ?? '').($cliente->nombrevendedor ? '-'.$cliente->nombrevendedor : '')) }}</td>
                <td>{{ $cliente->numerodocumento }}</td>
                <td>{{ $cliente->domicilio }}</td>
                <td>{{ $cliente->nombrelocalidad ?? '' }}</td>
                <td>{{ $cliente->nombreprovincia ?? '' }}</td>
                @if (! $esBierzo)
                    <td>{{ $cliente->codigo }}</td>
                @endif
                @if ($esBierzo)
                    <td>{{ $cliente->ctransporte }}-{{ $cliente->nombretransporte }}</td>
                @endif
            </tr>
        @endforeach
    </tbody>
</table>
