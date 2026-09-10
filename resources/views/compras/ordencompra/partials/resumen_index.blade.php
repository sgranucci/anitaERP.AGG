@php
    $r = $resumen ?? [];
    $total = (int) ($r['total'] ?? 0);
    $pendiente = (int) ($r['pendiente'] ?? 0);
    $aprobada = (int) ($r['aprobada'] ?? 0);
    $cumplida = (int) ($r['cumplida'] ?? 0);
    $suspendida = (int) ($r['suspendida'] ?? 0);
    $cerrada = (int) ($r['cerrada'] ?? 0);
@endphp
<div class="oc-resumen">
    <div class="oc-kpi">
        <div class="oc-kpi-label">Órdenes</div>
        <div class="oc-kpi-valor">{{ number_format($total, 0, ',', '.') }}</div>
    </div>
    <div class="oc-kpi is-pendiente">
        <div class="oc-kpi-label">Pendientes</div>
        <div class="oc-kpi-valor">{{ number_format($pendiente, 0, ',', '.') }}</div>
    </div>
    <div class="oc-kpi is-pendiente">
        <div class="oc-kpi-label">Aprobadas</div>
        <div class="oc-kpi-valor">{{ number_format($aprobada, 0, ',', '.') }}</div>
    </div>
    <div class="oc-kpi is-ok">
        <div class="oc-kpi-label">Cumplidas</div>
        <div class="oc-kpi-valor">{{ number_format($cumplida, 0, ',', '.') }}</div>
    </div>
    <div class="oc-kpi is-warn">
        <div class="oc-kpi-label">Suspendidas</div>
        <div class="oc-kpi-valor">{{ number_format($suspendida, 0, ',', '.') }}</div>
    </div>
    <div class="oc-kpi is-mute">
        <div class="oc-kpi-label">Cerradas</div>
        <div class="oc-kpi-valor">{{ number_format($cerrada, 0, ',', '.') }}</div>
    </div>
</div>
