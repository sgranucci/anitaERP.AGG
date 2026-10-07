@php
    use App\Support\Contable\PercepcionSufrida\PercepcionSufridaArchivoSupport;

    $filas = $filas ?? [];
    $monto = static function ($valor): string {
        return number_format((float) $valor, 2, '.', '');
    };
    $total = 0.0;
    foreach ($filas as $fila) {
        $total += (float) ($fila['importe_pesos'] ?? $fila['importe'] ?? 0);
    }
    $conJurisdiccion = ! empty($conJurisdiccion);
    $cols = $conJurisdiccion ? 7 : 6;
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="{{ $cols }}">&#160;</td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ $cols }}"><strong>{{ $titulo }}</strong></td>
    </tr>
    <tr>
        <td colspan="{{ $cols }}">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    <tr>
        <td colspan="{{ $cols }}">{{ $subtitulo ?? '' }} — {{ count($filas) }} líneas — Total {{ $monto($total) }}</td>
    </tr>
    <tr>
        <td>Fecha</td>
        <td>Comprobante</td>
        <td>Emisor</td>
        <td>CUIT</td>
        <td>Descripción</td>
        @if ($conJurisdiccion)
            <td>Jurisdicción</td>
        @endif
        <td>Importe</td>
    </tr>
    @foreach ($filas as $fila)
        <tr>
            <td>{{ ($fila['fecha'] ?? '') !== '' ? date('d/m/Y', strtotime((string) $fila['fecha'])) : '' }}</td>
            <td>{{ trim(($fila['tipo'] ?? '').' '.($fila['comprobante'] ?? '')) }}</td>
            <td>{{ ($fila['emisor_nombre'] ?? '') !== '' ? $fila['emisor_nombre'] : ($fila['emisor'] ?? '') }}</td>
            <td>{{ PercepcionSufridaArchivoSupport::cuitConGuiones((string) ($fila['cuit'] ?? '')) }}</td>
            <td>{{ $fila['descripcion'] ?? '' }}</td>
            @if ($conJurisdiccion)
                <td>{{ (int) ($fila['jurisdiccion'] ?? 0) }}</td>
            @endif
            <td>{{ $monto($fila['importe_pesos'] ?? $fila['importe'] ?? 0) }}</td>
        </tr>
    @endforeach
</table>
