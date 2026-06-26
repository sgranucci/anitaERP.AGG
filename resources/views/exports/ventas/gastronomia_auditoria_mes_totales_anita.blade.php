@php
    $colspan = 11;
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
                <strong style="font-size: 16pt;">{{ $titulo ?? 'Auditoría mensual Anita' }}</strong>
            </td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                Generado {{ date('d/m/Y H:i') }}
            </td>
        </tr>
        @if (! empty($subtitulo))
            <tr>
                <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                    {{ $subtitulo }}
                </td>
            </tr>
        @endif
        @if (! empty($hay_alertas))
            <tr>
                <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                    Hay d&iacute;as con huecos correlativos en numeraci&oacute;n Anita.
                </td>
            </tr>
        @endif
        @if (($total_lineas ?? 0) > 0)
            <tr>
                <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                    Registros d&iacute;a a d&iacute;a: {{ (int) $total_lineas }}
                </td>
            </tr>
        @endif
    </tbody>
    <thead>
        <tr>
            <th>Empresa id</th>
            <th>Empresa</th>
            <th>Jornada</th>
            <th>Total venta Anita</th>
            <th>Total vengrav Anita</th>
            <th>Total ctamov Anita</th>
            <th>Total rendg neto Anita</th>
            <th>Cabeceras venta</th>
            <th>Huecos corr.</th>
            <th>Estado</th>
            <th>Observaciones</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas ?? [] as $fila)
            <tr>
                <td>{{ $fila['empresa_id'] ?? '' }}</td>
                <td>{{ $fila['empresa_nombre'] ?? '' }}</td>
                <td>{{ $fila['fecha_jornada'] ?? '' }}</td>
                <td>{{ (float) ($fila['total_venta_anita'] ?? 0) }}</td>
                <td>{{ (float) ($fila['total_vengrav_anita'] ?? 0) }}</td>
                <td>{{ (float) ($fila['total_ctamov_anita'] ?? 0) }}</td>
                <td>{{ (float) ($fila['total_rendg_anita'] ?? 0) }}</td>
                <td>{{ (int) ($fila['cant_cabeceras_venta_anita'] ?? 0) }}</td>
                <td>{{ (int) ($fila['huecos_corr_anita'] ?? 0) }}</td>
                <td>{{ $fila['estado'] ?? '' }}</td>
                <td>{{ $fila['observaciones'] ?? '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
