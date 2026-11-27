<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="17" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="17"><strong style="font-size: 16pt;">{{ $titulo }}</strong></td>
        </tr>
        <tr>
            <td colspan="17">Generado {{ date('d/m/Y H:i') }}</td>
        </tr>
        @if (!empty($subtitulo))
            <tr>
                <td colspan="17">{{ $subtitulo }}</td>
            </tr>
        @endif
        <tr>
            <td colspan="17">Líneas con ahorro: {{ count($filas) }}</td>
        </tr>
    </tbody>
    @include('compras.tablero_oc.partials.tabla_ahorros', [
        'filas' => $filas,
        'pantalla' => false,
    ])
</table>
