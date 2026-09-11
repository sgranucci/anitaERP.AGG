@php
    $r = $resumen ?? [];
@endphp
<div class="oc-resumen">
    <div class="oc-kpi">
        <div class="oc-kpi-label">Requisiciones</div>
        <div class="oc-kpi-valor">{{ number_format((int) ($r['total'] ?? 0), 0, ',', '.') }}</div>
    </div>
    <div class="oc-kpi is-pendiente">
        <div class="oc-kpi-label">Pendientes</div>
        <div class="oc-kpi-valor">{{ number_format((int) ($r['pendiente'] ?? 0), 0, ',', '.') }}</div>
    </div>
    <div class="oc-kpi is-pendiente">
        <div class="oc-kpi-label">En compras</div>
        <div class="oc-kpi-valor">{{ number_format((int) ($r['en_compras'] ?? 0), 0, ',', '.') }}</div>
    </div>
    <div class="oc-kpi is-warn">
        <div class="oc-kpi-label">En árbol</div>
        <div class="oc-kpi-valor">{{ number_format((int) ($r['en_arbol'] ?? 0), 0, ',', '.') }}</div>
    </div>
    <div class="oc-kpi is-pendiente">
        <div class="oc-kpi-label">Aprobadas</div>
        <div class="oc-kpi-valor">{{ number_format((int) ($r['aprobada'] ?? 0), 0, ',', '.') }}</div>
    </div>
    <div class="oc-kpi is-ok">
        <div class="oc-kpi-label">Con OC</div>
        <div class="oc-kpi-valor">{{ number_format((int) ($r['genero_oc'] ?? 0), 0, ',', '.') }}</div>
    </div>
    <div class="oc-kpi is-ok">
        <div class="oc-kpi-label">Cumplidas</div>
        <div class="oc-kpi-valor">{{ number_format((int) ($r['cumplida'] ?? 0), 0, ',', '.') }}</div>
    </div>
    <div class="oc-kpi is-mute">
        <div class="oc-kpi-label">Provisorios</div>
        <div class="oc-kpi-valor">{{ number_format((int) ($r['provisorio'] ?? 0), 0, ',', '.') }}</div>
    </div>
</div>
