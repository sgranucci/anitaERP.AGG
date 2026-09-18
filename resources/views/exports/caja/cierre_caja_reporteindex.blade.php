@php
    use App\Support\Caja\CierreCajaReporteSecciones;

    $titulo = $titulo ?? 'Cierre de caja';
    $subtitulo = $subtitulo ?? '';
    $resultado = $resultado ?? [];
    $bloques = CierreCajaReporteSecciones::bloques($resultado);
    $colspan = 7;
@endphp
<table>
    @if (!empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="{{ $colspan }}" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ $colspan }}"><strong style="font-size:16pt;">{{ $titulo }}</strong></td>
    </tr>
    <tr>
        <td colspan="{{ $colspan }}">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if (trim($subtitulo) !== '')
        <tr>
            <td colspan="{{ $colspan }}">{{ $subtitulo }}</td>
        </tr>
    @endif
    @forelse ($bloques as $bloque)
        <tr>
            <td colspan="{{ $colspan }}"></td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}"><strong>{{ $bloque['titulo'] }}</strong></td>
        </tr>
        <tr>
            @foreach ($bloque['columnas'] as $columna)
                <td>{{ $columna }}</td>
            @endforeach
        </tr>
        @foreach ($bloque['filas'] as $fila)
            @include('caja.cierre_caja_reporte.partials.fila_seccion_export', [
                'fila' => $fila,
                'clave' => $bloque['clave'],
            ])
        @endforeach
    @empty
        <tr>
            <td colspan="{{ $colspan }}">Sin datos para los filtros aplicados.</td>
        </tr>
    @endforelse
</table>
