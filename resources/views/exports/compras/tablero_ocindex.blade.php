<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="19" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="19"><strong style="font-size: 16pt;">{{ $titulo }}</strong></td>
        </tr>
        <tr>
            <td colspan="19">Generado {{ date('d/m/Y H:i') }}</td>
        </tr>
        @if (!empty($subtitulo))
            <tr>
                <td colspan="19">{{ $subtitulo }}</td>
            </tr>
        @endif
        <tr>
            <td colspan="19">Líneas: {{ count($filas) }}</td>
        </tr>
    </tbody>
    @include('compras.tablero_oc.partials.tabla_detalle', [
        'filas' => $filas,
        'pantalla' => false,
        'para_pdf' => false,
        'para_excel' => true,
    ])
</table>
