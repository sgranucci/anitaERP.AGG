@php
    use App\Support\Caja\CierreCajaReporteSecciones;

    $resultado = $resultado ?? [];
    $bloques = CierreCajaReporteSecciones::bloques($resultado);
@endphp

@foreach ($bloques as $bloque)
    <div class="bloque-cierre">
        <table class="titulo-bloque">
            <tr>
                <td>{{ $bloque['titulo'] }}</td>
            </tr>
        </table>
        <table class="data">
            @if (!empty($bloque['anchos_pdf']))
                <colgroup>
                    @foreach ($bloque['anchos_pdf'] as $ancho)
                        <col style="width: {{ $ancho }};">
                    @endforeach
                </colgroup>
            @endif
            <thead>
                <tr>
                    @foreach ($bloque['columnas'] as $columna)
                        <th>{{ $columna }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($bloque['filas'] as $fila)
                    @include('caja.cierre_caja_reporte.partials.fila_seccion_export', [
                        'fila' => $fila,
                        'clave' => $bloque['clave'],
                    ])
                @endforeach
            </tbody>
        </table>
    </div>
@endforeach
