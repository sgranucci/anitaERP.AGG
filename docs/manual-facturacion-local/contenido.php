<?php

/**
 * Manual de usuario — cambios y devoluciones de e-commerce (Facturación Local Ferli).
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Cambios y devoluciones de e-commerce',
    'version' => '1.0',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Para qué sirve el legajo',
            'parrafos' => [
                'El legajo de cambios y devoluciones registra un cambio de un pedido de tienda (Tienda Nube, Mercado Libre o carga manual). El cliente devuelve un par y se lleva otro. El circuito emite dos comprobantes distintos y deja la diferencia anotada para compensarla a mano.',
                'La factura nueva cubre solo el par que se lleva. La nota de crédito cubre solo lo marcado como «A devolver», al precio de esa línea en la grilla. El resto de la factura (otro par, flete, cupón de otro artículo) no se vuelve a facturar ni se acredita.',
            ],
        ],
        [
            'titulo' => '2. El circuito',
            'parrafos' => [
                'Hay que tener un turno abierto en el local para emitir la factura de reemplazo y la nota de crédito. Ninguno de los dos comprobantes cobra ni devuelve plata en el momento: los dos se emiten contra la cuenta puente NCD (código 1131009, Aplicación de crédito local). La diferencia se compensa después, fuera de estos comprobantes.',
            ],
            'tabla' => [
                'caption' => 'Pasos del legajo',
                'headers' => ['Paso', 'Estado', 'Qué hace'],
                'rows' => [
                    ['1. Armar el legajo', 'Borrador', 'Elegir la factura original. Quedan las líneas del pedido. Dejar en «A devolver» solo el par que vuelve. Cargar el reemplazo (SKU, combinación y talle). El precio del reemplazo sale de la lista del local.'],
                    ['2. Confirmar', 'Abierto', 'No emite comprobante. Exige factura original, un motivo y al menos una línea de reemplazo. Las líneas se pueden seguir corrigiendo.'],
                    ['3. Factura de reemplazo', 'Aguardando recepción', 'Botón «Emitir FAC reemplazo (NCD)». Factura solo las líneas Reemplazo, al precio de la grilla, a nombre del mismo cliente. El par que se lleva sale de stock. El PDF queda en la solapa Comprobantes.'],
                    ['4. Recepción', 'Recibido', 'Cuando llega el par, se elige la disposición (reventa, deterioro o no recibido) y una observación. Todavía no mueve stock.'],
                    ['5. Nota de crédito', 'NC emitida', 'Botón «Emitir NC de lo devuelto (NCD)». Solo las líneas «A devolver», al precio cargado en la grilla. Si no se tocó, es el de la factura. Si se igualó al importe del producto (descuento por transferencia), la nota sale por ese importe. El motivo define si ese par vuelve al stock vendible.'],
                    ['6. Diferencia', 'Cerrado o Pendiente compensación', 'Compara el total de la factura de reemplazo con el total de la nota de crédito. Si son iguales, el legajo se cierra. Si no, queda pendiente y se cierra con un texto de cómo se compensó (efectivo, transferencia, etc.). Ese texto no genera otro comprobante.'],
                ],
            ],
        ],
        [
            'titulo' => '3. Precios: lista, factura y cupón',
            'parrafos' => [
                'La línea «A devolver» muestra el precio con el que se facturó ese par, ya con el cupón repartido. La línea «Reemplazo» muestra el precio vigente de la lista del local (la misma que usa el punto de venta y Tienda Nube antes del cupón). No es un precio por talle de las listas 16/26 o 27/33: en la web el artículo tiene un precio único.',
                'Si el pedido tenía un cupón, ese importe se reparte entre los artículos para que la factura cierre con AFIP. El par devuelto queda en la factura por menos que la lista. Si no se corrige el precio de «A devolver», la nota de crédito toma ese importe ya descontado y la factura de reemplazo toma la lista. La diferencia es la parte del cupón de ese par.',
                'Descuento por transferencia: en la grilla se pone el importe del producto en «A devolver» y en «Reemplazo». La factura y la nota de crédito salen por ese importe. La diferencia queda en cero.',
            ],
        ],
        [
            'titulo' => '4. Ejemplo: se devuelve un par de dos',
            'parrafos' => [
                'Pedido de Tienda Nube facturado en FAC B-00023-00045345 del 29/09/2026, total $ 94.022. El cliente cambia solo SINTONIA 03 (talle 26, TORNASOL) por el mismo artículo en talle 27, BLANCO-MULTI. Easy Kids y el flete se quedan en la factura original: no van en «A devolver».',
            ],
            'tabla' => [
                'caption' => 'Cómo estaba el pedido',
                'headers' => ['Concepto', 'Precio de lista / pedido', 'En la factura (con cupón)'],
                'rows' => [
                    ['Easy Kids 01, talle 26', '$ 46.800', '$ 28.817,73'],
                    ['SINTONIA 03, talle 26 TORNASOL', '$ 75.000', '$ 46.182,27'],
                    ['Flete', '$ 19.022', '$ 19.022'],
                    ['Cupón', '$ 46.800', 'Repartido entre los dos pares'],
                    ['Total factura', '', '$ 94.022'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Qué emite el legajo',
                'headers' => ['Comprobante', 'Qué incluye', 'Importe'],
                'rows' => [
                    ['Factura de reemplazo', 'SINTONIA 03 talle 27 BLANCO-MULTI, precio de lista OFERTA WEB', '$ 75.000'],
                    ['Nota de crédito', 'Solo SINTONIA 03 talle 26, al precio de la factura', '$ 46.182,27'],
                    ['No entra', 'Easy Kids y el flete siguen facturados', '—'],
                    ['Diferencia', 'Cliente debe (lista menos lo ya pagado por ese par)', '$ 28.817,73'],
                ],
            ],
            'items' => [
                'El cupón de $ 46.800 se repartió así: $ 17.982,27 a Easy Kids y $ 28.817,73 a SINTONIA. Por eso 75.000 − 28.817,73 = 46.182,27.',
                'Los dos talles de SINTONIA 03 están a $ 75.000 en la lista web. La diferencia no es porque el talle 27 sea más caro.',
                'Si Easy Kids o el flete quedan marcados como «A devolver», también entran en la nota de crédito. Hay que quitarlos de la grilla antes de confirmar.',
            ],
        ],
        [
            'titulo' => '5. Stock y motivo',
            'parrafos' => [
                'El par que se lleva sale de stock al emitir la factura de reemplazo, como una venta del local.',
                'El par que vuelve entra al stock recién al emitir la nota de crédito, y solo si el motivo del legajo dice «vuelve al stock». Si el motivo dice «no entra al stock», la nota de crédito no mueve depósito. La disposición de la recepción (reventa, deterioro, no recibido) queda en el historial y no decide el stock.',
            ],
        ],
        [
            'titulo' => '6. Qué no hace este circuito',
            'items' => [
                'No anula la factura original entera cuando el pedido tenía más de un par.',
                'No vuelve a aplicar el cupón: el descuento ya está en el precio de la línea facturada.',
                'No genera el cobro ni la devolución de la diferencia. Eso se anota al cerrar la compensación.',
                'Una factura puede tener más de una nota de crédito. La suma de esas notas no puede superar el total de la factura. El segundo cambio se acredita sobre la factura que todavía tiene saldo (la del reemplazo, o la original si el primer crédito fue parcial).',
                'Anular el legajo no anula comprobantes fiscales ya emitidos. Con nota de crédito emitida, el legajo no se puede anular desde esta pantalla.',
            ],
        ],
    ],
];
