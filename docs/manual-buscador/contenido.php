<?php

/**
 * Manual de usuario — Buscador del encabezado.
 * Audiencia: quien usa el ERP a diario.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Buscador del encabezado',
    'version' => '1.0',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Para qué sirve',
            'parrafos' => [
                'El campo Buscar del encabezado, al lado del menú, encuentra pantallas del rol, manuales, clientes, artículos, proveedores y comprobantes. También permite crear un alta y, si la frase no coincide con nada de eso, abrir la consulta operativa.',
                'Cada persona ve solo lo que su rol ya puede abrir. Un invitado no tiene el campo. En el celular el campo está en el mismo encabezado y el resultado se abre debajo.',
            ],
        ],
        [
            'titulo' => '2. Cómo se usa',
            'parrafos' => [
                'Escriba al menos dos letras. La lista se arma sola. Las flechas mueven el renglón, Enter lo abre y Esc cierra la lista. El tramo que coincide queda marcado en amarillo.',
                'La lista se parte en grupos: Sugerencias, Acciones, Pantallas, Ayuda, Comprobantes y Registros. Una pantalla anclada en la barra de abajo, si coincide con lo escrito, sube y muestra un pin.',
            ],
            'items' => [
                'Una sugerencia (Completar búsqueda o Quisiste decir) reemplaza lo escrito y vuelve a buscar. No abre una pantalla.',
                'Una pantalla se abre en la misma ventana. Un manual, un cliente, un artículo, un proveedor o un comprobante se abre en otra pestaña, en consulta.',
                'Enter sobre la fila marcada hace lo mismo que un clic.',
            ],
        ],
        [
            'titulo' => '3. Pantallas, ayuda y palabras parecidas',
            'parrafos' => [
                'Las pantallas son las hojas del menú de este rol. La ayuda son los manuales del Centro de ayuda que aplican a esta instalación.',
                'Si la palabra está a medias, la barra propone completarla. «cli» propone «clientes». Si está mal escrita, propone la corrección. «clinete» dice «Quisiste decir clientes» y, al elegirla, trae las pantallas de clientes.',
                'Una letra cambiada en una palabra del mismo largo no se toma como parecida. «precios» no trae «premios».',
            ],
        ],
        [
            'titulo' => '4. Maestros y órdenes de compra',
            'parrafos' => [
                'El grupo Registros trae como máximo cuatro de cada tipo: clientes, artículos, proveedores, usuarios, empleados, depósitos, centros de costo, cuentas contables y cuentas de caja. Primero el código exacto, después el que empieza igual y después el nombre.',
                'No entran clientes ni proveedores suspendidos, ni artículos inactivos, ni usuarios suspendidos, ni empleados de baja. El usuario solo aparece para administrador y encargado de sistemas, que son quienes abren ese maestro.',
                'El cliente se busca en todo el padrón. Artículo, proveedor, empleado, depósito, cuenta contable y cuenta de caja respetan la empresa asignada. El depósito, además, solo los que el usuario tiene autorizados.',
                'Si lo escrito no es un número, la orden de compra se busca por el nombre o el código del proveedor y sale en Comprobantes. Con un número sigue valiendo la búsqueda del apartado siguiente.',
                'Arriba, si lo escrito es el comienzo de un nombre o de un código, salen hasta unas pocas sugerencias. No recorre el padrón entero.',
            ],
        ],
        [
            'titulo' => '5. Comprobantes por número',
            'parrafos' => [
                'Un número de al menos tres dígitos busca orden de compra, pedido y factura. Cada fila dice el tipo. Si el mismo número es un cliente y una orden, salen las dos.',
                'La orden de compra y la factura quedan en las empresas asignadas al usuario. Quien no tiene empresas asignadas ve todas. El pedido no tiene empresa: usa el mismo filtro de vendedor que el listado de pedidos.',
                'En el pedido se busca el número final del código (el de «PED-…-00513365» se encuentra con 513365) y también el identificador interno si coincide exacto.',
            ],
            'tabla' => [
                'caption' => 'Qué abre cada número',
                'headers' => ['Escribe', 'Trae'],
                'rows' => [
                    ['Número de orden de compra', 'La orden, con el proveedor, si el rol puede ver órdenes de compra.'],
                    ['Número de factura', 'El comprobante de venta, con punto de venta y cliente, si el rol puede ver facturas.'],
                    ['Número de pedido', 'El pedido y el cliente, si el rol puede ver pedidos.'],
                    ['Código de cliente', 'La ficha del cliente. Si además hay un comprobante con ese número, sale en Comprobantes.'],
                ],
            ],
        ],
        [
            'titulo' => '6. Lo que este rol abre seguido',
            'parrafos' => [
                'Cuando se elige una fila, el rol recuerda ese texto y esa fila. A la tercera vez que la misma palabra abre la misma pantalla, esa pantalla queda primera, aunque el nombre no sea idéntico a lo escrito.',
                'El recuerdo es del rol, no de una sola persona. Al empezar no hay nada guardado: se arma con el uso.',
            ],
        ],
        [
            'titulo' => '7. Crear desde la barra',
            'parrafos' => [
                'Si lo escrito pide un alta, aparece el grupo Acciones. La fila entra a la pantalla de crear, con el mismo permiso que esa pantalla. Si el rol no puede crear, la fila no sale.',
                '«factura» sola no crea: abre la pantalla de facturación. Hace falta un verbo: nueva, nuevo, crear o alta.',
            ],
            'tabla' => [
                'caption' => 'Altas',
                'headers' => ['Escribe', 'Abre'],
                'rows' => [
                    ['nueva factura', 'Alta de factura. Facturación sigue en Pantallas.'],
                    ['crear cliente', 'Alta de cliente.'],
                    ['alta de articulo', 'Alta de artículo. También vale producto.'],
                    ['crear proveedor', 'Alta de proveedor.'],
                    ['crear oc', 'Alta de orden de compra.'],
                    ['crear pedido', 'Alta de pedido.'],
                    ['crear remito', 'Alta de remito, si el circuito existe en esta instalación.'],
                    ['crear cheque', 'Alta de cheque.'],
                    ['nueva', 'La lista de altas que este rol puede hacer, hasta seis. El resto sale al nombrarlo.'],
                ],
            ],
        ],
        [
            'titulo' => '8. Una frase',
            'parrafos' => [
                'Si la frase es una pregunta que la consulta operativa ya sabe responder, aparece «Preguntar: …» aunque también haya pantallas o maestros. Al elegirla, el panel responde. La barra no llama al modelo en cada tecla y no confirma ninguna carga.',
                'Ahí entran, entre otras, el saldo de un cliente, las OC pendientes de firma, las fórmulas de stock («artículos de compra del sku 111», «en qué fórmulas aparece el insumo 111»), los movimientos sin centro de costo y la conciliación ya corrida: «conciliación bancaria cuenta de caja 300 mes pasado» y «diferencias del turno de gastronomía». Esas dos últimas leen y explican; no concilian, no cobran y no cierran.',
                'Si la frase no coincide con ninguna pregunta conocida, y tampoco trajo pantalla, comprobante, maestro ni alta, quien tiene la consulta ve «Consultar: …». Esa fila abre el panel con el texto escrito y se envía recién al confirmar adentro. Ejemplo: «quiero cobrar».',
            ],
        ],
    ],
];
