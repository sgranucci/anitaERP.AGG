@php
    $fmt = function ($valor, bool $entero): string {
        if ($entero) {
            return number_format((int) round((float) $valor), 0, '.', ',');
        }

        return number_format((float) round((float) $valor), 0, '.', ',');
    };
@endphp
<table cellspacing="0" cellpadding="4" style="border-collapse:collapse; font-family:Arial, sans-serif; font-size:13px; color:#17202A;">
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
                        <td style="text-align:right; padding:2px 10px; white-space:nowrap; {{ ! empty($fila['es_total']) ? 'font-weight:700;' : '' }}">
                            {{ $fmt($fila[$columna['clave']] ?? 0, ! empty($fila['entero'])) }}
                        </td>
                    @endforeach
                </tr>
            @endforeach
        @endforeach
    </tbody>
</table>
