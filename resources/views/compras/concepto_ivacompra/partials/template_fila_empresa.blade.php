<template id="template-renglon-concepto-ivacompra-empresa">
@include('compras.concepto_ivacompra.partials.fila_empresa', [
    'linea' => null,
    'empresa_query' => $empresa_query,
    'puedeAbrirAbmCuenta' => $puedeAbrirAbmCuenta,
    'indice' => 0,
])
</template>
