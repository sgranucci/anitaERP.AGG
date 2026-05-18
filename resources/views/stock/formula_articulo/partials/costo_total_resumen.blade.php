@php
    /** @var \App\Support\Stock\FormulaArticuloCostoTotalResult|null $costoTotal */
    use App\Support\Stock\FormulaArticuloGastronomia;
    $costoTotal = $costoTotal ?? null;
    $tituloCosto = 'Suma cant. × factor costo × última compra (Anita). Subfórmulas: recursivo.';
    if (FormulaArticuloGastronomia::opcionalesHabilitados()) {
        $tituloCosto .= ' Opcionales: 1.º de cada orden 1…N.';
    }
    if ($costoTotal && ! $costoTotal->completo && count($costoTotal->advertencias) > 0) {
        $tituloCosto .= ' — '.implode(' · ', array_slice($costoTotal->advertencias, 0, 3));
    }
@endphp
@if ($costoTotal && ($costoTotal->total > 0 || ! $costoTotal->completo))
<p class="mb-2 text-muted" id="formula-costo-total-resumen" style="font-size: 0.95rem; line-height: 1.35;" title="{{ $tituloCosto }}">
    <strong class="text-body">Costo estimado:</strong>
    <span class="text-monospace font-weight-bold {{ $costoTotal->completo ? 'text-dark' : 'text-warning' }}" style="font-size: 1.1rem;">{{ number_format($costoTotal->total, 2, ',', '.') }}</span>
    @if ($costoTotal->cantidadUnidad > 0 && abs($costoTotal->cantidadUnidad - 1.0) > 0.0001)
    <span class="text-muted" style="font-size: 0.9rem;"> / u. {{ number_format($costoTotal->totalPorUnidadFormula(), 2, ',', '.') }}</span>
    @endif
    @if (! $costoTotal->completo)
    <span class="text-warning" style="font-size: 0.9rem;"> (parcial)</span>
    @endif
</p>
@endif
