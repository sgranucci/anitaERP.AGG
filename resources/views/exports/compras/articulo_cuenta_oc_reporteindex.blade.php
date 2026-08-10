<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="13" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="13">
                <strong style="font-size: 16pt;">{{ $titulo }}</strong>
            </td>
        </tr>
        <tr>
            <td colspan="13">Generado {{ date('d/m/Y H:i') }}</td>
        </tr>
        @if (!empty($subtitulo))
            <tr>
                <td colspan="13">{{ $subtitulo }}</td>
            </tr>
        @endif
        @if (!empty($totales))
            <tr>
                <td colspan="13">
                    Artículos: {{ (int) ($totales['total_articulos'] ?? 0) }}
                    · Filas: {{ (int) ($totales['total_filas'] ?? 0) }}
                    · Dif. cuenta: {{ (int) ($totales['con_diferencia_cuenta'] ?? 0) }}
                </td>
            </tr>
        @endif
        @if (!empty($total_lineas))
            <tr>
                <td colspan="13">Líneas: {{ (int) $total_lineas }}</td>
            </tr>
        @endif
    </tbody>
    @include('compras.articulo_cuenta_oc_reporte.partials.tabla_datos', [
        'filas' => $filas ?? [],
        'modo' => $modo ?? 'resumen',
        'para_pdf' => true,
        'para_excel' => true,
        'puede_ver_articulo' => false,
        'puede_ver_proveedor' => false,
        'puede_ver_cuentacontable' => false,
    ])
</table>
