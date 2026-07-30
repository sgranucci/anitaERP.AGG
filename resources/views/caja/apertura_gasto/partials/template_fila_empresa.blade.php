<template id="template-renglon-apertura-gasto-empresa">
@include('caja.apertura_gasto.partials.fila_empresa', [
    'linea' => null,
    'empresa_query' => $empresa_query,
    'puedeAbrirAbmCuenta' => $puedeAbrirAbmCuenta,
    'puedeAbrirAbmCc' => $puedeAbrirAbmCc,
    'indice' => 0,
])
</template>
