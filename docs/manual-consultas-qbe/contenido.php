<?php

/**
 * Manual de usuario — Consultas de la grilla de pagos a proveedores.
 * Audiencia: pagos, tesorería y quien arma listados. Piloto sobre pago a proveedores.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Consultas de la grilla de pagos a proveedores',
    'version' => '1.6',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Para qué sirve',
            'parrafos' => [
                'Esta pantalla lista las órdenes de pago del circuito de compras y las órdenes de pago cargadas por ingresos y egresos. Encima de la grilla hay una consulta que se arma por criterios, se guarda como vista y puede mostrar hasta tres gráficos, pintar filas y agregar columnas calculadas.',
                'Menú: Compras → Pago a proveedores. Hace falta el permiso de listar pagos a proveedores. Lo que cada uno ve de empresas depende de las empresas asignadas a su usuario.',
                'El gráfico, el color de fila, el clic sobre una barra y la vista por rol están también en comprobantes de proveedor, artículos e ingresos y egresos. Ingresos y egresos tiene el mismo lienzo de hasta tres gráficos (cantidad, ingresos y egresos, ya en pesos), las columnas calculadas y el envío del Excel ahora o programado. El filtro por factura aplicada y el mail al proveedor, en ingresos y egresos, salen solo en las OPP y OPA que son una orden de pago. Administración de tickets tiene el mismo lienzo, las columnas calculadas y el Excel programado, encima del estado, las fechas y el alcance por rol y usuario. Ahí no hay factura ni mail al proveedor.',
            ],
        ],
        [
            'titulo' => '2. La barra de la pantalla',
            'parrafos' => [
                'Arriba está el selector de vista, Diseñar vista, QBE, Visual y Defaults instalación. A la derecha, la caja Texto o número, Limpiar y, si hay un orden memorizado, Quitar orden.',
                'QBE abre la consulta avanzada. Visual abre el gráfico, el color de fila y las columnas calculadas. Los dos paneles arrancan cerrados para dejar la grilla a la vista. Si el botón QBE está relleno, hay una consulta aplicada aunque el panel esté cerrado.',
                'Nueva OP abre una orden de pago del circuito. Pago vía IE abre ingresos y egresos con el tipo orden de pago, si el usuario tiene permiso.',
            ],
            'items' => [
                'Texto o número busca en todos los campos de texto y número. Enter o la lupa lanzan esa búsqueda y dejan de lado los criterios de la consulta avanzada.',
                'Limpiar saca el texto, la consulta avanzada, el gráfico, el color y las columnas calculadas. No saca la empresa, el filtro de mail ni el orden de la grilla.',
                'Quitar orden vuelve al orden de instalación (fecha descendente) y lo borra de la vista del usuario.',
            ],
        ],
        [
            'titulo' => '3. Empresa y mail',
            'parrafos' => [
                'Los botones de empresa y de mail están por encima de la consulta avanzada y se combinan con ella. Una vista guardada no los pisa.',
                'Mail tiene Todos, Enviado y Sin enviar. Mira si la orden de pago del circuito tiene un envío al proveedor en su historia. Las órdenes cargadas por ingresos y egresos no llevan ese ícono.',
            ],
        ],
        [
            'titulo' => '4. Consulta avanzada',
            'parrafos' => [
                'Se abre con QBE. Cada renglón es un criterio: campo, operador y valor. Los criterios de un grupo se unen con Todos (Y) o Alguno (O). El grupo puede negar el resultado con NOT. Entre grupos se elige Y u O.',
                'Hay hasta 8 grupos y 12 criterios por grupo. Buscar aplica la consulta y la deja en la dirección de la pantalla, así la paginación y la exportación usan el mismo filtro.',
                'Cuentas de caja y el ícono de mail se ven en la grilla y no se filtran acá: se arman después de paginar. El mail se filtra con los botones de arriba.',
            ],
            'tabla' => [
                'caption' => 'Campos de la consulta',
                'headers' => ['Campo', 'Qué compara'],
                'rows' => [
                    ['Fecha', 'Fecha del pago.'],
                    ['OP', 'Número de la orden.'],
                    ['Empresa', 'Nombre de la empresa.'],
                    ['Proveedor', 'Nombre del proveedor.'],
                    ['Descripción', 'Texto del detalle.'],
                    ['Monto', 'Importe del pago, en la moneda de cada fila.'],
                    ['Estado', 'Por ejemplo CONFIRMADA o REVERTIDA.'],
                    ['ID', 'Identificador interno.'],
                    ['Origen', 'Escriba orden de pago o ingresos y egresos.'],
                    ['Tipo', 'Abreviatura del comprobante.'],
                    ['Moneda', 'Abreviatura de la moneda.'],
                    ['Nro. factura aplicada', 'Número del comprobante, o sucursal y número como 1891-564.'],
                    ['Fecha factura aplicada', 'Fecha de esa factura.'],
                    ['Total factura aplicada', 'Total de esa factura.'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Operadores según el tipo de campo',
                'headers' => ['Tipo', 'Operadores'],
                'rows' => [
                    ['Texto', 'Contiene, no contiene, empieza, termina, igual, distinto, mayor y menor alfabético, entre, está vacío.'],
                    ['Número entero', 'Igual, mayor, mayor o igual, menor, menor o igual, entre, vacío.'],
                    ['Importe', 'Desde, hasta, mayor, menor, igual, entre, vacío.'],
                    ['Fecha', 'Desde, hasta, posterior a, anterior a, es el día, entre, hoy, ayer, esta semana, este mes, mes anterior, este año, sin fecha. Al elegir una fecha el operador inicial es Entre.'],
                ],
            ],
        ],
        [
            'titulo' => '5. Filtro por la factura aplicada',
            'parrafos' => [
                'Nro. factura aplicada, Fecha factura aplicada y Total factura aplicada buscan la factura que la orden de pago canceló. El camino es la aplicación del pago, la deuda de cuenta corriente y el comprobante. Esa relación existe solo en esta grilla.',
                'El número puede ser el del comprobante (564) o sucursal y número juntos (1891-564). Ejemplo: 1891-564 trae la orden de pago 28239 de la factura Cetrogar A 1891-564.',
                'Si ese criterio está activo, las órdenes cargadas por ingresos y egresos no entran en el resultado: no tienen un vínculo confiable con la factura.',
            ],
        ],
        [
            'titulo' => '6. Fórmulas',
            'parrafos' => [
                'En un criterio se puede elegir Fórmula en lugar de un campo. También se usan en las columnas calculadas del panel Visual. El lenguaje es corto, a propósito.',
            ],
            'items' => [
                'LENGTH({proveedor}) — cantidad de caracteres.',
                'UPPER({estado}) y LOWER({estado}) — mayúsculas o minúsculas.',
                'TRIM({detalle}) — saca espacios de los extremos.',
                'CONCAT({proveedor}, \' \', {estado}) — une textos. El nombre entre llaves es el campo de la consulta.',
                'No se puede usar la factura aplicada dentro de una fórmula: esa factura no está en la fila del listado, solo en el filtro.',
            ],
        ],
        [
            'titulo' => '7. Vistas',
            'parrafos' => [
                'Diseñar vista define columnas, etiquetas, anchos, alineación y orden. El orden del diseñador es el orden de la grilla. Guardar ofrece Actualizar esta vista o Guardar como nueva.',
                'Usar como vista por defecto al abrir aplica solo a quien la guarda. Compartir con otros usuarios la deja visible para el resto. El atajo de menú, si se marca, agrega un acceso junto a Pago a proveedores.',
                'Al guardar, la consulta avanzada, el orden, los gráficos, el color de fila y las columnas calculadas que están en pantalla viajan con la vista. Hay que tener el panel armado y después guardar: si se cambian los gráficos y no se guarda la vista, quedan en esa consulta, no para la próxima entrada.',
                'Vista estándar ignora la vista por defecto y muestra la grilla de instalación.',
            ],
        ],
        [
            'titulo' => '8. Vista de instalación por rol',
            'parrafos' => [
                'En Diseñar vista, el administrador puede marcar la vista como vista de instalación de un rol. Quien entra con ese rol la recibe si no tiene una vista propia marcada como defecto.',
                'La vista personal gana. Si el usuario ya eligió la suya, la del rol no la reemplaza.',
                'El selector de rol lo ve el administrador. El resto de los usuarios no asigna vistas a un rol; solo las recibe.',
            ],
        ],
        [
            'titulo' => '9. Orden de la grilla',
            'parrafos' => [
                'El encabezado de cada columna ordenable alterna ascendente y descendente y deja una marca. Se pueden acumular hasta cinco criterios. Quitar orden los borra y vuelve a fecha descendente, número descendente.',
                'Cuentas de caja y el ícono de mail no se ordenan: no están en la consulta SQL de la grilla.',
                'El orden se memoriza en la vista del usuario. Limpiar no lo borra. Quitar orden sí.',
            ],
        ],
        [
            'titulo' => '10. Gráfico, color y columnas calculadas',
            'parrafos' => [
                'Se abre con Visual y se aplica con Aplicar visual. Hay tres filas de gráfico. Cada una elige tipo, eje y valor. Una fila en Sin gráfico no se dibuja. Los gráficos que quedan usan el mismo filtro de la consulta.',
                'El valor se pide por nombre: Cantidad, o Suma de monto. Cantidad cuenta pagos. Suma de monto suma el importe y lo parte por moneda. No es un total si hay pesos y dólares juntos.',
            ],
            'items' => [
                'Ejemplo de dos gráficos. Fila 1: Barras, Estado, Cantidad. Fila 2: Barras, Proveedor, Suma de monto. Fila 3: Sin gráfico. Aplicar visual. Quedan dos dibujos.',
                'Un clic en la barra CONFIRMADA del gráfico de estados deja la grilla y el gráfico de proveedores solo con pagos confirmados. El gráfico de estados sigue mostrando el resto de los estados, para elegir otro. Quitar este filtro saca solo ese clic.',
                'En una torta de suma de monto, cada porción dice el valor y la moneda (CONFIRMADA · ARS). El clic usa la parte anterior al punto medio.',
                'Quitar gráficos, el botón del primer dibujo, apaga los tres. Si la vista es de quien está mirando, también se los saca a la vista guardada. Elegir Sin gráfico en las tres filas y aplicar hace lo mismo.',
                'Eje: un campo de la grilla, salvo el monto. El monto es el valor, no el eje. Se muestran los 12 valores más altos.',
                'Pintar fila si: hasta cuatro reglas. Un campo es o contiene un texto, y la fila se pinta de ámbar, rojo o verde. Vale la primera que coincide. El texto se compara con lo que se ve en la celda, por ejemplo CONFIRMADA o REVERTIDA.',
                'Columna calculada: hasta dos. Nombre y fórmula, con el mismo lenguaje del capítulo 6 (LENGTH, UPPER, LOWER, TRIM, CONCAT). Salen al final de la grilla, del Excel, del PDF y del mail. No entran al diseñador de columnas. No son la suma del gráfico.',
            ],
        ],
        [
            'titulo' => '11. Cortes',
            'parrafos' => [
                'Si en Diseñar vista se agrupa por un campo, arriba de la grilla aparecen los cortes de ese agrupamiento: cuántas filas hay y la suma de monto. Esa suma del corte sigue juntando monedas. El gráfico, cuando el valor es suma de monto, las separa.',
                'La grilla sigue paginada. El corte sale del filtro completo, no de la página visible.',
            ],
        ],
        [
            'titulo' => '12. Exportar y mandar por mail',
            'parrafos' => [
                'Pdf, Excel y Csv de la barra de exportación usan el filtro, la vista y el orden que están en pantalla. Exportan el universo filtrado, no solo la página.',
                'Mandar Excel ahora envía ese listado al correo que se escribe. El campo arranca con el correo del usuario. En el mismo formulario se puede dejar programado todos los días o cada lunes: se manda a las 07:05 con el filtro que había en pantalla al programarlo. Dar de baja corta el envío.',
                'Si el filtro tiene más de 2.000 filas, el archivo trae las primeras 2.000 y el aviso lo dice. El Excel del mail es el del listado, con las columnas calculadas si hay. No incluye el gráfico.',
            ],
        ],
        [
            'titulo' => '13. Ejemplos',
            'parrafos' => [
                'Pagos confirmados de un proveedor: QBE, campo Proveedor contiene el nombre, y Estado es igual a CONFIRMADA. Buscar.',
                'Factura A 1891-564: QBE, Nro. factura aplicada es igual a 1891-564. Trae la orden de pago 28239. También se puede buscar solo 564. El resultado son órdenes de pago del circuito. Las de ingresos y egresos no aparecen.',
                'Dos gráficos: Visual, fila 1 Barras / Estado / Cantidad, fila 2 Barras / Proveedor / Suma de monto, Aplicar visual. Clic en CONFIRMADA: la grilla y el gráfico de proveedores quedan en confirmadas; el de estados sigue completo. Guardar la vista si tiene que quedar para la próxima vez.',
                'Filas revertidas en rojo: Visual, pintar fila si Estado es REVERTIDA, tono Rojo, Aplicar visual.',
                'Columna en mayúsculas: Visual, etiqueta Mayúsculas, fórmula UPPER({estado}), Aplicar visual.',
            ],
        ],
        [
            'titulo' => '14. Límites de esta versión',
            'parrafos' => [
                'En pagos a proveedores hay medidas con nombre (Cantidad y Suma de monto), una relación con la factura aplicada y un lienzo de hasta tres gráficos que comparten el filtro. No es un tablero que mezcle comprobantes, cheques y artículos, ni un lenguaje de fórmulas de agregado.',
            ],
            'items' => [
                'Cuentas de caja y el ícono de mail no entran a la consulta avanzada ni al orden.',
                'El corte de la grilla, si suma monto, sigue juntando monedas. El gráfico no.',
                'La relación con la factura y el mail al proveedor están en pagos y, en ingresos y egresos, solo en OPP y OPA ligadas a una orden de pago. El lienzo de tres gráficos, las columnas calculadas y el envío programado están en pagos, en ingresos y egresos y en administración de tickets. En tickets se suman al estado, a las fechas y al alcance por rol; no los reemplazan. Comprobantes y artículos siguen con un gráfico, color de fila, clic y vista por rol.',
                'Las fórmulas de una columna siguen siendo LENGTH, UPPER, LOWER, TRIM y CONCAT. No calculan una suma del gráfico.',
            ],
        ],
    ],
];
