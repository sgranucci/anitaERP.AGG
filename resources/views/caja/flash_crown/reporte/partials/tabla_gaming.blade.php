@php
    use App\Support\Caja\Flash\FlashCrownNumeroSupport as N;
    use App\Support\Caja\Flash\FlashCajaLFlashFormatoSupport as F;
    use App\Support\Export\ExcelFormatoNumero;

    $excel = ! empty($modo_excel);
    $formatoNumero = $formatoNumero ?? ExcelFormatoNumero::preferenciaGlobal();
    $tituloExcel = $titulo_excel ?? null;
@endphp
<table cellspacing="0" cellpadding="4" style="border-collapse:collapse; font-family:Arial, sans-serif; font-size:13px; color:#17202A;">
    @if ($excel && $tituloExcel)
        <tr>
            <td colspan="{{ count($informe['columnas']) + 1 }}">
                <strong style="font-size:16px;">{{ $tituloExcel }}</strong>
            </td>
        </tr>
        <tr>
            <td colspan="{{ count($informe['columnas']) + 1 }}">Generado {{ date('d/m/Y H:i') }}</td>
        </tr>
    @endif
    <thead>
        <tr>
            <th style="background:#85C1E9; color:#17202A; text-align:left; min-width:11rem;"></th>
            @foreach ($informe['columnas'] as $columna)
                <th style="background:#85C1E9; color:#17202A; text-align:right; white-space:nowrap; padding:6px 10px;">
                    {{ $columna['titulo'] }}
                    @if (($columna['subtitulo'] ?? '') !== '')
                        <br>{{ $columna['subtitulo'] }}
                    @endif
                </th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($informe['casinos'] as $casino)
            <tr>
                <td colspan="{{ count($informe['columnas']) + 1 }}" style="padding-top:14px; font-weight:700;">
                    {{ $casino['titulo'] }}
                </td>
            </tr>
            @foreach ($casino['filas'] as $fila)
                <tr>
                    <td style="padding:2px 8px; {{ ! empty($fila['es_total']) ? 'font-weight:700;' : '' }}">
                        {{ $fila['etiqueta'] }}
                    </td>
                    @foreach ($informe['columnas'] as $columna)
                        @php
                            $dec = ! empty($fila['entero']) ? 0 : 2;
                            $valor = $fila[$columna['clave']] ?? 0;
                            $negrita = ! empty($fila['es_total']);
                        @endphp
                        <td style="text-align:right; padding:2px 10px; white-space:nowrap; {{ $negrita ? 'font-weight:700;' : '' }}">
                            @if ($excel)
                                {{-- Número crudo: AfterSheet fuerza TYPE_NUMERIC + máscara de miles --}}
                                {{ F::nExcelFormato($valor, $formatoNumero, $dec) }}
                            @else
                                {!! N::html($valor, $dec, $negrita) !!}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        @endforeach
    </tbody>
</table>
