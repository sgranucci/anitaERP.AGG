@php
    $colspan = 7;
    $formatear = static fn ($v) => number_format((float) $v, 2, ',', '.');
@endphp
<table>
    @if (!empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="{{ $colspan }}">
                <h2 style="margin: 0; font-size: 16pt; font-weight: bold;">{{ $titulo ?? 'Kilos por categoría' }}</h2>
                @if (!empty($subtitulo))
                    <div style="font-size: 10pt; color: #444;">{{ $subtitulo }}</div>
                @endif
            </td>
        </tr>
    </tbody>
    @include('ventas.repkilocategoria.partials.tabla_datos', [
        'filas' => $filas,
        'para_pdf' => true,
    ])
</table>
