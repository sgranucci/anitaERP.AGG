@php
    $colspan = 6;
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="{{ $colspan }}">
                <strong style="font-size: 16pt;">{{ $titulo ?? 'Auditoría de notas CRM' }}</strong>
            </td>
        </tr>
        @if (! empty($subtitulo))
            <tr>
                <td colspan="{{ $colspan }}" style="font-size: 12pt; color: #333;">
                    <strong>{{ $subtitulo }}</strong>
                </td>
            </tr>
        @endif
        @if (($totalFilas ?? 0) > 0)
            <tr>
                <td colspan="{{ $colspan }}" style="font-size: 9pt; color: #666;">
                    Registros: {{ (int) $totalFilas }}
                </td>
            </tr>
        @endif
    </tbody>
    @include('ventas.suitecrm_nota_auditoria.partials.tabla_datos', [
        'filas' => $filas ?? [],
        'mostrarLinks' => false,
        'puede_ver_cliente' => false,
        'modo' => 'excel',
    ])
</table>
