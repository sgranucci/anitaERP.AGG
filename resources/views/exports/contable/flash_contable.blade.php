@php
    use App\Support\Caja\Flash\FlashCajaLFlashFormatoSupport as F;
    use App\Support\Contable\FlashContableReporteSupport;
    use App\Support\Export\ExcelFormatoNumero;

    $formatoNumero = $formatoNumero ?? ExcelFormatoNumero::preferenciaGlobal();
    $empresas = $reporte['empresas'] ?? [];
    $filas = $reporte['filas'] ?? [];
    $totales = $reporte['totales'] ?? [];
    $etiquetas = $reporte['etiquetas'] ?? FlashContableReporteSupport::ETIQUETAS;
    $metricas = $reporte['metricas'] ?? FlashContableReporteSupport::METRICAS;
    $colspan = max(2, (int) ($reporte['cantidad_columnas'] ?? (1 + count($empresas) * count($metricas))));
    $fe = fn ($v) => F::enteroExcelFormato($v, $formatoNumero);
    $fn = fn ($v) => F::nExcelFormato($v, $formatoNumero, 2);
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr><td colspan="{{ $colspan }}" style="height: 52px;"></td></tr>
    @endif
    <tr>
        <td colspan="{{ $colspan }}"><strong style="font-size: 16px;">{{ $reporte['titulo'] ?? 'Flash — Contabilidad e Impuestos' }}</strong></td>
    </tr>
    <tr>
        <td colspan="{{ $colspan }}">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    <tr>
        <td colspan="{{ $colspan }}">
            {{ $reporte['empresas_texto'] ?? '' }}
            &mdash; {{ $reporte['periodo'] ?? '' }}
        </td>
    </tr>
    <tr>
        <th>Fecha</th>
        @foreach ($empresas as $empresa)
            <th colspan="{{ count($metricas) }}">{{ $empresa['nombre'] ?? '' }}</th>
        @endforeach
    </tr>
    <tr>
        <th>Fecha</th>
        @foreach ($empresas as $empresa)
            @foreach ($metricas as $clave)
                <th>{{ $etiquetas[$clave] ?? $clave }}</th>
            @endforeach
        @endforeach
    </tr>
    @foreach ($filas as $fila)
        <tr>
            <td>{{ $fila['fecha'] ?? '' }}</td>
            @foreach ($empresas as $empresa)
                @php $m = $fila['empresas'][$empresa['id']] ?? [] @endphp
                @foreach ($metricas as $clave)
                    <td>
                        @if (FlashContableReporteSupport::esTilde($clave))
                            {{ ! empty($m['flash_cerrado']) ? 'Sí' : '' }}
                        @elseif (FlashContableReporteSupport::esEntero($clave))
                            {{ $fe($m[$clave] ?? 0) }}
                        @else
                            {{ $fn($m[$clave] ?? 0) }}
                        @endif
                    </td>
                @endforeach
            @endforeach
        </tr>
    @endforeach
    @if (! empty($filas) && ! empty($totales))
        <tr>
            <td>Total</td>
            @foreach ($empresas as $empresa)
                @php $m = $totales[$empresa['id']] ?? [] @endphp
                @foreach ($metricas as $clave)
                    <td>
                        @if (FlashContableReporteSupport::esTilde($clave))
                            {{ $m['flash_cerrado_texto'] ?? '' }}
                        @elseif (FlashContableReporteSupport::esEntero($clave))
                            {{ $fe($m[$clave] ?? 0) }}
                        @else
                            {{ $fn($m[$clave] ?? 0) }}
                        @endif
                    </td>
                @endforeach
            @endforeach
        </tr>
    @endif
</table>
