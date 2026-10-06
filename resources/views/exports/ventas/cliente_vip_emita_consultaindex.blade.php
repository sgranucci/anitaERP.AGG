<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="8" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="8"><strong style="font-size:16pt;">{{ $titulo }}</strong></td>
    </tr>
    <tr>
        <td colspan="8">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if (! empty($subtitulo))
        <tr>
            <td colspan="8">{{ $subtitulo }}</td>
        </tr>
    @endif
    @if (($total ?? 0) > 0)
        <tr>
            <td colspan="8">Registros: {{ $total }}</td>
        </tr>
    @endif
    <thead>
        <tr>
            <th>Sala</th>
            <th>Origen</th>
            <th>Cuenta Wigos</th>
            <th>Nombre y apellido</th>
            <th>Alias</th>
            <th>Nivel tarjeta</th>
            <th>VIP</th>
            <th>Última visita</th>
        </tr>
    </thead>
    <tbody>
        @include('ventas.gastronomia.canjes.cliente_vip_emita.partials.tabla_filas', ['filas' => $filas])
    </tbody>
</table>
