<template id="template-renglon-imputacion-perdida-empresa">
@include('caja.imputacion_perdida.partials.fila_empresa', [
    'linea' => null,
    'empresa_query' => $empresa_query,
    'puedeAbrirAbmCuenta' => $puedeAbrirAbmCuenta,
    'indice' => 0,
])
</template>
