@php
    $depositos = $datos['depositos'] ?? [];
    $colspan = ($modo ?? '') === 'subtotal'
        ? 2 + (count($depositos) * 2)
        : 8;
    $tituloSolapa = trim((string) ($titulo_solapa ?? ''));
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ $colspan }}"><strong style="font-size: 16pt;">{{ $titulo ?? 'Inventario para contaduría' }}</strong></td>
    </tr>
    @if ($tituloSolapa !== '')
        <tr>
            <td colspan="{{ $colspan }}"><strong style="font-size: 13pt;">{{ $tituloSolapa }}</strong></td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    <tr>
        <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">{{ $subtitulo ?? '' }} · Valorizado a precio de última compra</td>
    </tr>
    @if (($modo ?? '') === 'subtotal')
        <tr>
            <th>Rubro</th>
            @foreach ($depositos as $deposito)
                <th>Cant. dep. {{ $deposito['codigo'] }}</th>
                <th>Valor dep. {{ $deposito['codigo'] }} {{ $deposito['nombre'] }}</th>
            @endforeach
            <th>Cantidad total</th>
            <th>Valorizado</th>
        </tr>
        @foreach ($datos['rubros'] ?? [] as $rubro)
            @if (($rubro['clave'] ?? '') === 'otros' && abs((float) ($rubro['total'] ?? 0)) <= 0.0001 && abs((float) ($rubro['total_valor'] ?? 0)) <= 0.0001)
                @continue
            @endif
            <tr>
                <td>{{ $rubro['titulo'] ?? '' }}</td>
                @foreach ($depositos as $deposito)
                    <td>{{ $rubro['saldos'][$deposito['id']] ?? 0 }}</td>
                    <td>{{ $rubro['valores'][$deposito['id']] ?? 0 }}</td>
                @endforeach
                <td>{{ $rubro['total'] ?? 0 }}</td>
                <td>{{ $rubro['total_valor'] ?? 0 }}</td>
            </tr>
        @endforeach
    @else
        <tr>
            <th>SKU</th>
            <th>Descripción</th>
            <th>Categoría</th>
            <th>Uso</th>
            <th>Tipo</th>
            <th>Cantidad dep. {{ $datos['deposito_codigo'] ?? '' }}</th>
            <th>Precio última compra</th>
            <th>Valorizado</th>
        </tr>
        @foreach ($datos['filas'] ?? [] as $fila)
            <tr>
                <td>{{ $fila['sku'] ?? '' }}</td>
                <td>{{ $fila['descripcion'] ?? '' }}</td>
                <td>{{ $fila['categoria'] ?? '' }}</td>
                <td>{{ $fila['uso'] ?? '' }}</td>
                <td>{{ $fila['tipo'] ?? '' }}</td>
                <td>{{ $fila['saldo'] ?? 0 }}</td>
                <td>{{ $fila['precio'] ?? 0 }}</td>
                <td>{{ $fila['valor'] ?? 0 }}</td>
            </tr>
        @endforeach
        <tr>
            <td colspan="5"><strong>Total {{ $datos['deposito_codigo'] ?? '' }} {{ $datos['deposito_nombre'] ?? '' }}</strong></td>
            <td><strong>{{ $datos['total'] ?? 0 }}</strong></td>
            <td></td>
            <td><strong>{{ $datos['total_valor'] ?? 0 }}</strong></td>
        </tr>
    @endif
</table>
