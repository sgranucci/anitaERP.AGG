@php
    use App\Support\Compras\Tracking\TrackingAntiguedadDeuda;

    /**
     * Franja de totales + dashboard de aging.
     *
     * Se calcula con el mismo filtro que la grilla, así que responde siempre
     * por lo que el usuario está viendo y no por el universo completo.
     */
    $r = $resumen ?? [];
    $registros = (int) ($r['registros'] ?? 0);
    $conPdf = (int) ($r['con_pdf'] ?? 0);
    $sinPdf = (int) ($r['sin_pdf'] ?? 0);
    $sinPdfExternos = (int) ($r['sin_pdf_externos'] ?? $sinPdf);
    $sinResolver = (int) ($r['sin_resolver'] ?? 0);
    $deuda90 = (int) ($r['deuda_90_mas'] ?? 0);
    $conDeuda = (int) ($r['con_deuda'] ?? 0);
    $tramoActivo = (string) ($filtros['tramo_antiguedad'] ?? '');

    $resueltos = $conPdf + $sinPdf;
    $coberturaPdf = $resueltos > 0 ? round(100 * $conPdf / $resueltos) : null;

    $mostrarAlertas = $sinPdfExternos > 0 || $deuda90 > 0 || (int) ($r['sin_contabilizar'] ?? 0) > 0;

    $urlTramo = function (string $tramo) use ($filtrosQuery) {
        $q = $filtrosQuery ?? [];
        unset($q['page'], $q['tramo_antiguedad'], $q['segmento']);
        if ($tramo !== '') {
            $q['tramo_antiguedad'] = $tramo;
        }

        return route('tracking_facturas', $q);
    };

    $tramosAging = [
        ['clave' => 'deuda_corriente', 'saldo' => 'saldo_corriente', 'tramo' => TrackingAntiguedadDeuda::CORRIENTE],
        ['clave' => 'deuda_0_30', 'saldo' => 'saldo_0_30', 'tramo' => TrackingAntiguedadDeuda::HASTA_30],
        ['clave' => 'deuda_31_60', 'saldo' => 'saldo_31_60', 'tramo' => TrackingAntiguedadDeuda::DE_31_A_60],
        ['clave' => 'deuda_61_90', 'saldo' => 'saldo_61_90', 'tramo' => TrackingAntiguedadDeuda::DE_61_A_90],
        ['clave' => 'deuda_90_mas', 'saldo' => 'saldo_90_mas', 'tramo' => TrackingAntiguedadDeuda::MAS_DE_90],
    ];
@endphp
<div class="tf-resumen">
    <div class="tf-item">
        <div class="tf-label">Comprobantes</div>
        <div class="tf-valor">{{ number_format($registros, 0, ',', '.') }}</div>
    </div>
    <div class="tf-item">
        <div class="tf-label">Importe total</div>
        <div class="tf-valor">$ {{ number_format((float) ($r['total'] ?? 0), 2, ',', '.') }}</div>
    </div>
    <div class="tf-item">
        <div class="tf-label">Saldo pendiente</div>
        <div class="tf-valor {{ (float) ($r['saldo'] ?? 0) != 0 ? 'tf-malo' : 'tf-bueno' }}">
            $ {{ number_format((float) ($r['saldo'] ?? 0), 2, ',', '.') }}
        </div>
        <div class="tf-nota">{{ number_format($conDeuda, 0, ',', '.') }} sin pagar</div>
    </div>
    <div class="tf-item">
        <div class="tf-label">Sin contabilizar</div>
        <div class="tf-valor {{ (int) ($r['sin_contabilizar'] ?? 0) > 0 ? 'tf-malo' : 'tf-bueno' }}">
            {{ number_format((int) ($r['sin_contabilizar'] ?? 0), 0, ',', '.') }}
        </div>
    </div>
    <div class="tf-item">
        <div class="tf-label">Cobertura de PDF</div>
        @if ($coberturaPdf === null)
            <div class="tf-valor">—</div>
            <div class="tf-nota">Sin resolver todavía</div>
        @else
            <div class="tf-valor {{ $coberturaPdf >= 90 ? 'tf-bueno' : 'tf-malo' }}">{{ $coberturaPdf }}%</div>
            <div class="tf-nota">
                {{ number_format($sinPdfExternos, 0, ',', '.') }} externos sin escanear
                @if ($sinPdf > $sinPdfExternos)
                    · {{ number_format($sinPdf - $sinPdfExternos, 0, ',', '.') }} internos
                @endif
            </div>
        @endif
    </div>
</div>

@if ($conDeuda > 0)
    <div class="tf-aging" aria-label="Antigüedad de la deuda">
        <div class="tf-aging-cabeza">
            <div class="tf-aging-titulo">Antigüedad de la deuda</div>
            <div class="tf-aging-ayuda">Por vencimiento · click para filtrar</div>
        </div>
        <div class="tf-aging-tramos">
            @foreach ($tramosAging as $meta)
                @php
                    $n = (int) ($r[$meta['clave']] ?? 0);
                    $monto = (float) ($r[$meta['saldo']] ?? 0);
                    $activo = $tramoActivo === $meta['tramo'];
                    $urgente = $meta['tramo'] === TrackingAntiguedadDeuda::MAS_DE_90 && $n > 0;
                @endphp
                <a href="{{ $urlTramo($activo ? '' : $meta['tramo']) }}"
                   class="tf-aging-item {{ $urgente ? 'tf-aging-urgente' : '' }} {{ $activo ? 'tf-aging-activo' : '' }}"
                   title="{{ $activo ? 'Quitar filtro de este tramo' : 'Ver sólo este tramo' }}">
                    <div class="tf-aging-label">{{ TrackingAntiguedadDeuda::etiqueta($meta['tramo']) }}</div>
                    <div class="tf-aging-valor">{{ number_format($n, 0, ',', '.') }}</div>
                    <div class="tf-aging-monto">$ {{ number_format($monto, 0, ',', '.') }}</div>
                </a>
            @endforeach
        </div>
    </div>
@endif

@if ($mostrarAlertas)
    <div class="tf-alertas" role="status">
        @if ($sinPdfExternos > 0)
            <a class="tf-alerta-item"
               href="{{ route('tracking_facturas', array_merge($filtrosQuery ?? [], ['segmento' => 'sin_pdf', 'page' => null])) }}">
                <i class="fa fa-file-excel-o"></i>
                <strong>{{ number_format($sinPdfExternos, 0, ',', '.') }}</strong> externos sin PDF escaneado
            </a>
        @endif
        @if ($deuda90 > 0)
            <a class="tf-alerta-item tf-alerta-urgente"
               href="{{ $urlTramo(TrackingAntiguedadDeuda::MAS_DE_90) }}">
                <i class="fa fa-clock-o"></i>
                <strong>{{ number_format($deuda90, 0, ',', '.') }}</strong>
                · $ {{ number_format((float) ($r['saldo_90_mas'] ?? 0), 0, ',', '.') }}
                con más de 90 días de atraso
            </a>
        @endif
        @if ((int) ($r['sin_contabilizar'] ?? 0) > 0)
            <a class="tf-alerta-item"
               href="{{ route('tracking_facturas', array_merge($filtrosQuery ?? [], ['segmento' => 'sin_contabilizar', 'page' => null])) }}">
                <i class="fa fa-hourglass-half"></i>
                <strong>{{ number_format((int) $r['sin_contabilizar'], 0, ',', '.') }}</strong> sin contabilizar
            </a>
        @endif
    </div>
@endif
