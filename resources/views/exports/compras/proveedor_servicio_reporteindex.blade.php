<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="8" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="8">
                <strong style="font-size: 16pt;">{{ $titulo }}</strong>
            </td>
        </tr>
        <tr>
            <td colspan="8">Generado {{ date('d/m/Y H:i') }}</td>
        </tr>
        @if (!empty($subtitulo))
            <tr>
                <td colspan="8">{{ $subtitulo }}</td>
            </tr>
        @endif
        @if (!empty($totales))
            <tr>
                <td colspan="8">
                    Proveedores: {{ (int) ($totales['proveedores'] ?? 0) }}
                    · Clientes / medidores: {{ (int) ($totales['renglones'] ?? 0) }}
                </td>
            </tr>
        @endif
    </tbody>
    @include('compras.proveedor_servicio_reporte.partials.tabla_datos', [
        'filas' => $filas ?? [],
        'para_pdf' => true,
        'para_excel' => true,
        'puede_ver_proveedor' => false,
    ])
</table>
