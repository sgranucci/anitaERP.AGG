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
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="7">&#160;</td>
        </tr>
    @endif
    <tr>
        <td colspan="7"><strong>{{ $titulo }}</strong></td>
    </tr>
    <tr>
        <td colspan="7">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    <tr>
        <td colspan="7">{{ $subtitulo ?? '' }} — {{ count($filas) }} líneas — {{ $monto($total) }}</td>
    </tr>
    <tr>
        <td>Fecha</td>
        <td>Comprobante</td>
        <td>Emisor</td>
        <td>CUIT</td>
        <td>Descripción</td>
        <td>Jurisdicción</td>
        <td>Importe</td>
    </tr>
    @foreach ($filas as $fila)
        <tr>
            <td>{{ ($fila['fecha'] ?? '') !== '' ? date('d/m/Y', strtotime((string) $fila['fecha'])) : '' }}</td>
            <td>{{ trim(($fila['tipo'] ?? '').' '.($fila['comprobante'] ?? '')) }}</td>
            <td>{{ ($fila['emisor_nombre'] ?? '') !== '' ? $fila['emisor_nombre'] : ($fila['emisor'] ?? '') }}</td>
            <td>{{ PercepcionSufridaArchivoSupport::cuitConGuiones((string) ($fila['cuit'] ?? '')) }}</td>
            <td>{{ $fila['descripcion'] ?? '' }}</td>
            <td>{{ (int) ($fila['jurisdiccion'] ?? 0) }}</td>
            <td>{{ $monto($fila['importe_pesos'] ?? $fila['importe'] ?? 0) }}</td>
        </tr>
    @endforeach
</table>
