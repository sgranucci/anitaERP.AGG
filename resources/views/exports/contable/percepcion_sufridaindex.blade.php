@php
    use App\Support\Contable\PercepcionSufrida\PercepcionSufridaArchivoSupport;

    $diferencias = $diferencias ?? [];
    $tot = $totales ?? [];
    $esIibb = ! empty($esIibb);
    $monto = static function ($valor): string {
        return number_format((float) $valor, 2, '.', '');
    };
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="8">&#160;</td>
        </tr>
    @endif
    <tr>
        <td colspan="8"><strong>{{ $titulo }}</strong></td>
    </tr>
    <tr>
        <td colspan="8">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    <tr>
        <td colspan="8">{{ $subtitulo ?? '' }}</td>
    </tr>
    <tr>
        <td colspan="8">Total mayor: {{ $monto($tot['mayor'] ?? 0) }}</td>
    </tr>
    @if ($esIibb)
        <tr>
            <td colspan="8">Cruzado 901 (CABA): {{ $monto($tot['cruzado_901'] ?? 0) }}</td>
        </tr>
        <tr>
            <td colspan="8">Cruzado 902 (Buenos Aires): {{ $monto($tot['cruzado_902'] ?? 0) }}</td>
        </tr>
    @else
        <tr>
            <td colspan="8">Total cruzado: {{ $monto($tot['cruzado'] ?? 0) }}</td>
        </tr>
    @endif
    <tr>
        <td colspan="8">Diferencias: {{ $monto($tot['diferencias'] ?? 0) }}</td>
    </tr>
    <tr>
        <th>Fecha</th>
        <th>Comprobante</th>
        <th>Emisor</th>
        <th>CUIT</th>
        <th>Descripción</th>
        <th>Importe mayor</th>
        <th>Importe reporte</th>
        <th>Diferencia</th>
    </tr>
    @foreach ($diferencias as $fila)
        <tr>
            <td>{{ ($fila['fecha'] ?? '') !== '' ? date('d/m/Y', strtotime($fila['fecha'])) : '' }}</td>
            <td>{{ trim(($fila['tipo'] ?? '').' '.($fila['comprobante'] ?? '')) }}</td>
            <td>{{ ($fila['emisor_nombre'] ?? '') !== '' ? $fila['emisor_nombre'] : ($fila['emisor'] ?? '') }}</td>
            <td>{{ PercepcionSufridaArchivoSupport::cuitConGuiones((string) ($fila['cuit'] ?? '')) }}</td>
            <td>{{ $fila['descripcion'] ?? '' }}</td>
            <td>{{ $monto($fila['importe_mayor'] ?? 0) }}</td>
            <td>{{ $monto($fila['importe_reporte'] ?? 0) }}</td>
            <td>{{ $monto($fila['diferencia'] ?? 0) }}</td>
        </tr>
    @endforeach
</table>
