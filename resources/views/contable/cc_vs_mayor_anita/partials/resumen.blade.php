@php
    $r = $resumen ?? [];
    $fmt = static fn ($n) => number_format((float) $n, 2, ',', '.');
@endphp
<div class="row mb-3">
    <div class="col-md-3">
        <div class="border rounded p-2 bg-light">
            <div class="text-muted small">Neto CC (climov D−H)</div>
            <strong>{{ $fmt($r['cc_neto'] ?? 0) }}</strong>
            <div class="small">D {{ $fmt($r['cc_debe'] ?? 0) }} · H {{ $fmt($r['cc_haber'] ?? 0) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="border rounded p-2 bg-light">
            <div class="text-muted small">Neto mayor (subdiario D−H)</div>
            <strong>{{ $fmt($r['mayor_neto'] ?? 0) }}</strong>
            <div class="small">D {{ $fmt($r['mayor_debe'] ?? 0) }} · H {{ $fmt($r['mayor_haber'] ?? 0) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="border rounded p-2 {{ abs((float)($r['diff_neto'] ?? 0)) > 0.05 ? 'bg-warning' : 'bg-light' }}">
            <div class="text-muted small">Diff neto CC − mayor</div>
            <strong>{{ $fmt($r['diff_neto'] ?? 0) }}</strong>
            <div class="small">Problemas: {{ (int) ($r['filas_problema'] ?? 0) }} · Match flex: {{ (int) ($r['filas_match_flex'] ?? 0) }}</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="border rounded p-2 bg-light">
            <div class="text-muted small">Filas bridge</div>
            <div class="small">climov {{ (int) ($r['climov_filas'] ?? 0) }} · aplmov {{ (int) ($r['aplmov_filas'] ?? 0) }} · subdiario {{ (int) ($r['subdiario_filas'] ?? 0) }}</div>
            <div class="small">aplmov suma {{ $fmt($r['aplmov_suma'] ?? 0) }}</div>
        </div>
    </div>
</div>
