@php
    $ocJsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE;
    $ocMonedasJson = json_encode(
        $moneda_query->map(fn ($m) => ['id' => (int) $m->id, 'abrev' => (string) ($m->abreviatura ?? '')])->values()->all(),
        $ocJsonFlags
    );
    $ocFormapagosJson = json_encode(
        $formapago_query->map(fn ($f) => ['id' => (int) $f->id, 'nombre' => (string) ($f->nombre ?? '')])->values()->all(),
        $ocJsonFlags
    );
    $ocTotRef = $oc_totales_resumen ?? \App\Support\Compras\OrdencompraTotalesResumen::vacioParaVista();
    $ocTotalesReferenciaJson = json_encode([
        'total' => (float) ($ocTotRef['total'] ?? 0),
        'moneda_id' => (int) ($ocTotRef['moneda_id'] ?? 1),
    ], $ocJsonFlags);
@endphp
<script type="application/json" id="oc-json-monedas">{!! $ocMonedasJson !!}</script>
<script type="application/json" id="oc-json-formapagos">{!! $ocFormapagosJson !!}</script>
<script type="application/json" id="oc-json-totales-referencia">{!! $ocTotalesReferenciaJson !!}</script>

@include('includes.stock.modalconsultaarticulo')
@include('includes.presupuesto.modalconsultapartidagasto', ['centrocosto_query' => $centrocosto_query ?? null])
@include('includes.presupuesto.modalconsultacapex', ['centrocosto_query' => $centrocosto_query ?? null])
@include('includes.compras.modalconsultaproveedor')
@include('includes.compras.modalconsultarequisicion')
