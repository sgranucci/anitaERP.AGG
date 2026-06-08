<template id="template-renglon-tolerancia-recepcion">
    @include('configuracion.recepcion_proveedor.partials.fila_tolerancia', [
        'tolerancia' => (object) [
            'centrocosto_id' => 0,
            'tolerancia_cantidad_pct' => 0,
            'tolerancia_precio_pct' => 0,
            'tolerancia_precio_absoluto' => 0,
        ],
        'indice' => '__INDEX__',
        'centrocosto_query' => $centrocosto_query,
        'es_nueva' => true,
    ])
</template>
