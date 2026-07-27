@php
    /** @var \Illuminate\Support\Collection<int, mixed> $cuentas */
    $cuentas = collect($cuentas ?? []);
    $grupos = $cuentas->groupBy(static function ($fila) {
        $codigo = trim((string) ($fila->cuentacontable?->codigo ?? ''));

        return $codigo !== '' ? $codigo : 'id:'.(int) ($fila->cuentacontable_id ?? 0);
    });
@endphp
@forelse ($grupos as $filas)
    @php
        $cuenta = $filas->first()?->cuentacontable;
        $empresas = $filas
            ->map(static fn ($f) => trim((string) ($f->empresa?->nombre ?? '')))
            ->filter()
            ->unique()
            ->values();
    @endphp
    <div class="mb-1">
        <strong>{{ $cuenta->codigo ?? '—' }}</strong>
        — {{ $cuenta->nombre ?? '' }}
        @if ($empresas->isNotEmpty())
            <div class="text-muted" style="font-size:0.85em;line-height:1.25;">
                {{ $empresas->implode(', ') }}
            </div>
        @endif
    </div>
@empty
    <span class="text-warning">Sin cuentas</span>
@endforelse
