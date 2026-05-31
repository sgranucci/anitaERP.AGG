<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="20" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="20">
                <h2 style="margin: 0; font-size: 18pt; font-weight: bold;">{{ $titulo }}</h2>
                @if (!empty($subtitulo))
                    <div style="font-size: 10pt; color: #555;">{{ $subtitulo }}</div>
                @endif
            </td>
        </tr>
    </tbody>
    @include('presupuesto.capex_reporte.partials.tabla_datos', [
        'filas' => $filas,
        'separatorPartidas' => "\n",
    ])
</table>
