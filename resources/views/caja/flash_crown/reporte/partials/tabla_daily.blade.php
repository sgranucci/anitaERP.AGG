@php
    $columnas = $informe['columnas'] ?? \App\Support\Caja\Flash\FlashCrownInformeSupport::columnasDaily();
    $fmt = function ($valor, array $col): string {
        if (! empty($col['entero'])) {
            return number_format((int) round((float) $valor), 0, ',', '.');
        }
        if (! empty($col['pct'])) {
            return number_format((float) $valor, 2, ',', '.').'%';
        }
        if (($col['clave'] ?? '') === 'day' || ($col['clave'] ?? '') === 'fecha') {
            return (string) $valor;
        }

        return number_format((float) round((float) $valor), 0, ',', '.');
    };
    $grupos = [];
    foreach ($columnas as $col) {
        $g = (string) ($col['grupo'] ?? '');
        if ($g === '') {
            $grupos[] = ['titulo' => '', 'span' => 1];
            continue;
        }
        $ultimo = $grupos[count($grupos) - 1] ?? null;
        if ($ultimo && ($ultimo['titulo'] ?? null) === $g) {
            $grupos[count($grupos) - 1]['span']++;
        } else {
            $grupos[] = ['titulo' => $g, 'span' => 1];
        }
    }
@endphp
@foreach ($informe['secciones'] as $seccion)
    <h6 class="mt-3 mb-1 font-weight-bold">{{ $seccion['titulo'] }}</h6>
    <div class="table-responsive mb-3">
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
                @foreach (array_merge($seccion['filas'], [$seccion['total'], $seccion['promedio']]) as $fila)
                    @php
                        $peso = (! empty($fila['es_total']) || ! empty($fila['es_promedio'])) ? 'font-weight:700;' : '';
                        $fondo = ! empty($fila['es_total']) ? 'background:#D6EAF8;' : (! empty($fila['es_promedio']) ? 'background:#EAF2F8;' : '');
                    @endphp
                    <tr style="{{ $fondo }}">
                        @foreach ($columnas as $col)
                            <td style="padding:2px 6px; white-space:nowrap; border:1px solid #D5D8DC; text-align:{{ in_array($col['clave'], ['day', 'fecha'], true) ? 'left' : 'right' }}; {{ $peso }}">
                                {{ $fmt($fila[$col['clave']] ?? '', $col) }}
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endforeach
