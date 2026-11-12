@php
    use App\Support\Caja\Flash\FlashCajaLFlashFormatoSupport as F;
    use App\Support\Export\ExcelFormatoNumero;

    $excel = ! empty($excel);
    $formatoNumero = $formatoNumero ?? ExcelFormatoNumero::preferenciaGlobal();
    $filasMostrar = array_values(array_filter(
        array_merge($seccion['filas'] ?? [], [($seccion['total'] ?? null), ($seccion['promedio'] ?? null)]),
        static fn ($fila) => is_array($fila) && $fila !== []
    ));
@endphp
@if (! $excel)
    <div class="table-responsive mb-3">
@endif
<table cellspacing="0" cellpadding="3" style="border-collapse:collapse; font-family:Arial, sans-serif; font-size:11px; color:#17202A; width:max-content; min-width:100%;">
    <thead>
        <tr>
            @foreach ($grupos as $grupo)
                <th colspan="{{ $grupo['span'] }}" style="background:#85C1E9; color:#17202A; text-align:center; border:1px solid #AED6F1; white-space:nowrap;">
                    {{ $grupo['titulo'] }}
                </th>
            @endforeach
        </tr>
        <tr>
            @foreach ($columnas as $col)
                <th style="background:#85C1E9; color:#17202A; text-align:{{ in_array($col['clave'], ['day', 'fecha'], true) ? 'left' : 'right' }}; border:1px solid #AED6F1; white-space:nowrap; padding:4px 6px;">
                    {{ $col['titulo'] }}
                </th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($filasMostrar as $fila)
            @php
                $peso = (! empty($fila['es_total']) || ! empty($fila['es_promedio'])) ? 'font-weight:700;' : '';
                $fondo = ! empty($fila['es_total']) ? 'background:#D6EAF8;' : (! empty($fila['es_promedio']) ? 'background:#EAF2F8;' : '');
            @endphp
            <tr style="{{ $fondo }}">
                @foreach ($columnas as $col)
                    @php
                        $clave = $col['clave'] ?? '';
                        $valor = $fila[$clave] ?? '';
                        if ($clave === 'day' || $clave === 'fecha') {
                            $texto = (string) $valor;
                        } elseif ($excel) {
                            // Número crudo para que Excel sume (máscara en AfterSheet).
                            if (! empty($col['pct'])) {
                                $texto = F::nExcelFormato($valor, $formatoNumero, 2);
                            } elseif (! empty($col['entero'])) {
                                $texto = F::enteroExcelFormato($valor, $formatoNumero);
                            } else {
                                $texto = F::nExcelFormato($valor, $formatoNumero, 2);
                            }
                        } elseif (! empty($col['entero'])) {
                            $texto = F::n($valor, 0);
                        } elseif (! empty($col['pct'])) {
                            $texto = F::pct($valor, 2);
                        } else {
                            $texto = F::n($valor, 2);
                        }
                    @endphp
                    <td style="padding:2px 6px; white-space:nowrap; border:1px solid #D5D8DC; text-align:{{ in_array($clave, ['day', 'fecha'], true) ? 'left' : 'right' }}; {{ $peso }}">
                        {{ $texto }}
                    </td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
@if (! $excel)
    </div>
@endif
