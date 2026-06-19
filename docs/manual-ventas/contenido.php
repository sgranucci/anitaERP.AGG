<?php

/**
 * Contenido del manual de usuario — Anita ERP / Pedidos y Facturación.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Pedidos y Facturación',
    'version' => '1.0',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Introducción y roles',
            'parrafos' => [
                'Este manual describe el proceso de pedidos de venta y su facturación en Anita ERP, adaptado a la operatoria de EL BIERZO, donde la mercadería se comercializa en cajas, piezas y kilos, y el importe facturado se basa en la pesada real registrada en planta.',
                'El circuito involucra dos perfiles principales:',
            ],
            'items' => [
                'Vendedor remoto (desde cualquier punto del país con acceso web): carga el pedido con cliente, reparto, artículos y cantidades pedidas. No pesa ni factura.',
                'Personal de planta / administración (en la empresa): ve el listado de pedidos, los imprime para preparación y despacho, registra la pesada escaneando códigos QR de las cajas, y emite remito y factura.',
            ],
            'nota' => 'Los pedidos cargados por vendedores remotos quedan registrados en el sistema y se listan físicamente en la empresa (impresión en papel o PDF) para que depósito y logística preparen la mercadería. La facturación se realiza en planta, una vez pesada la mercadería.',
            'tabla' => [
                'caption' => 'Rutas principales',
                'headers' => ['Pantalla', 'Ruta', 'Quién la usa'],
                'rows' => [
                    ['Listado de pedidos', 'ventas/pedido', 'Todos'],
                    ['Nuevo pedido', 'ventas/pedido/crear', 'Vendedor remoto'],
                    ['Editar pedido', 'ventas/pedido/{id}/editar', 'Planta / administración'],
                    ['Listado físico (impresora)', 'ventas/listarpedido/{id}', 'Planta'],
                    ['Listado PDF', 'ventas/listarpedidopdf/{id}', 'Planta / vendedor'],
                    ['Cierre masivo de pedidos', 'ventas/pedido/cerrar', 'Administración'],
                ],
            ],
        ],
        [
            'titulo' => '2. Conceptos: cajas, piezas, kilos y pesada',
            'parrafos' => [
                'En EL BIERZO cada renglón del pedido maneja cuatro cantidades relacionadas. Entender la diferencia es clave para vendedores y para quien pesa y factura.',
            ],
            'tabla' => [
                'caption' => 'Columnas de cantidad en el pedido',
                'headers' => ['Columna', 'Significado', 'Quién la completa', 'Uso en factura'],
                'rows' => [
                    ['Cajas', 'Cantidad de envases/cajas pedidos. Se calcula a partir de piezas y kilos según datos del artículo (unidades por envase y peso unitario).', 'Vendedor (pedido)', 'Informativo / bultos'],
                    ['Piezas', 'Unidades individuales dentro de las cajas (ej. unidades, piezas de producto).', 'Vendedor (pedido)', 'Informativo'],
                    ['Kilos', 'Peso teórico del pedido: piezas × peso del artículo. Es la cantidad pedida, no la pesada.', 'Vendedor (pedido); el sistema recalcula al cambiar cajas/piezas', 'Referencia; no es la base de facturación'],
                    ['Pesada', 'Peso real en kilos, sumado caja por caja al escanear QR en planta (o ingreso manual en casos excepcionales).', 'Planta (pesada)', 'Base de facturación — es lo que se factura'],
                ],
            ],
            'parrafos2' => [
                'Conversión automática (cajas ↔ piezas ↔ kilos): al ingresar o modificar Cajas, Piezas o Kilos, el sistema recalcula las otras columnas usando unidades por envase, peso del artículo y UMD del renglón.',
            ],
            'items' => [
                'Si ingresa cajas → calcula piezas = cajas × unidades por envase, y kilos = piezas × peso.',
                'Si ingresa piezas → calcula kilos = piezas × peso, y deriva cajas cuando corresponde.',
                'Si ingresa kilos → redondea hacia arriba las piezas y cajas necesarias (no se fraccionan cajas incompletas en unidades CAJ).',
                'Descuentos por cantidad: al seleccionar un descuento de tipo «por cantidad vendida», el sistema puede sumar piezas bonificadas y recalcular cajas/kilos.',
                'Totales del pie del pedido: Total cajas, Total piezas, Total kilos (pedido teórico) y Total pesados (suma de la columna Pesada).',
            ],
        ],
        [
            'titulo' => '3. Circuito completo del pedido',
            'captura_id' => 'flujo_pedidos',
            'parrafos' => [
                'El flujo operativo estándar en EL BIERZO es el siguiente:',
            ],
            'flujo' => "1. VENDEDOR REMOTO ──► Carga pedido (cliente, reparto, ítems, cajas/piezas/kilos)\n         │\n         ▼\n2. EMPRESA (listado) ──► Pedido visible en ventas/pedido → impresión física o PDF para depósito\n         │\n         ▼\n3. DEPÓSITO ──► Prepara mercadería según el listado impreso\n         │\n         ▼\n4. PLANTA (pesada) ──► Escaneo QR de cada caja → acumula kilos reales en columna Pesada\n         │\n         ▼\n5. ADMINISTRACIÓN ──► Botón Factura → remito + factura con kilos pesados\n         │\n         ▼\n6. ENTREGA ──► Remito de traslado + factura al cliente / transporte",
            'tabla' => [
                'caption' => 'Estados del pedido',
                'headers' => ['Estado', 'Descripción'],
                'rows' => [
                    ['Pendiente', 'Pedido cargado, aún no facturado. Es el estado normal tras guardar.'],
                    ['Suspendido', 'Pedido pausado (no se factura hasta reactivarlo).'],
                    ['Facturado', 'Se emitió factura; el pedido ya no se edita.'],
                    ['Anulado (por ítem)', 'Renglones marcados como anulados (fondo rojo en número de ítem).'],
                ],
            ],
        ],
        [
            'titulo' => '4. Listado de pedidos en la empresa',
            'captura_id' => 'pedido_listado',
            'herramientas_clave' => 'listado_pedidos',
            'herramientas_incluir_listado' => true,
            'parrafos' => [
                'Los pedidos cargados desde cualquier ubicación aparecen en el listado central (ventas/pedido). Desde allí el personal de la empresa consulta, filtra e imprime.',
                'Cada fila resume: ID, fechas, cliente, totales de Cajas, Piezas, Kilos, Pesada, reparto y estado.',
                'Impresión física del pedido (listado en planta): el icono impresora (Listar el pedido) genera un PDF y lo envía a la impresora configurada del usuario (menú Configura salida). El icono PDF rojo descarga o abre el PDF en pantalla.',
                'El documento impreso incluye: número de pedido, fechas, cliente, reparto, zona de venta, lugar de entrega, detalle por SKU con unidades, kilos, cajas, precio y pesada, totales y leyendas.',
            ],
        ],
        [
            'titulo' => '5. Carga de pedido (vendedores remotos)',
            'captura_id' => 'pedido_crear',
            'parrafos' => [
                'Esta sección está orientada a vendedores que cargan pedidos desde fuera de la planta. El objetivo es registrar con precisión qué pide el cliente y cuánto (en cajas/piezas/kilos teóricos), sin necesidad de pesar.',
                'Acceso: menú Ventas → Pedidos de clientes → Nuevo registro, o ruta ventas/pedido/crear.',
            ],
            'tabla' => [
                'caption' => 'Campos de cabecera',
                'headers' => ['Campo', 'Descripción', 'Consejo'],
                'rows' => [
                    ['Cliente', 'Código + nombre. Lupa para consultar clientes. Si no existe, puede usar Alta cliente provisorio.', 'Verifique suspensión (moroso, proforma, no facturar) antes de confirmar.'],
                    ['Vendedor', 'Vendedor asignado al pedido.', 'Seleccione su nombre en el desplegable.'],
                    ['Reparto', 'Transporte / línea de reparto (código + lupa).', 'Define logística y puede influir en facturación dividida.'],
                    ['Lugar de entrega', 'Dirección o referencia de entrega.', 'Sea específico: el listado impreso lo usa logística.'],
                    ['Fecha entrega', 'Fecha solicitada de entrega.', 'Campo requerido.'],
                    ['Zona de venta', 'Zona comercial del cliente.', 'Se puede completar por código o lupa.'],
                    ['Lote', 'Lote de stock (si aplica a la operatoria del día).', 'Consultar con planta si hay dudas.'],
                    ['Leyendas', 'Observaciones libres.', 'Instrucciones especiales visibles en el listado impreso.'],
                ],
            ],
            'items' => [
                'Agregar renglón con el botón + Agrega renglón (máximo 42 ítems por pedido).',
                'Artículo: ingrese el SKU o use la lupa de consulta. Al elegir, se cargan descripción, UMD y precio de lista.',
                'UMD (unidad de medida): CAJ, UN, KG, etc. Al cambiarla, se blanquean cantidades y el foco salta al campo adecuado.',
                'Ingrese cantidad en Cajas, Piezas o Kilos (uno basta; el sistema recalcula el resto al salir del campo).',
                'Descuento (opcional): seleccione de la lista si corresponde promoción.',
                'Pesada: déjela en cero o vacía — la completa planta al pesar.',
                'Revise los totales del pie y presione Guardar.',
            ],
            'nota' => 'Recordatorio para vendedores remotos: su pedido quedará en estado Pendiente y aparecerá en el listado de la empresa. Depósito lo imprimirá y preparará. Usted no debe completar la pesada ni facturar salvo que su rol lo autorice expresamente en planta.',
        ],
        [
            'titulo' => '6. Edición, guardado y estados',
            'captura_id' => 'pedido_editar',
            'herramientas_clave' => 'edicion_pedido',
            'parrafos' => [
                'Desde ventas/pedido/{id}/editar se puede modificar un pedido pendiente, registrar pesada y facturar.',
            ],
            'items' => [
                'Anular ítem (cruz roja): marca el renglón como anulado; requiere motivo según permisos.',
                'Eliminar línea (papelera): quita el renglón del pedido (permiso borrar-items-pedidos).',
                'Artículo sin cargo (regalo): precio cero en ítems bonificados (permiso especial).',
                'Historia de anulaciones (libro): muestra movimientos del ítem.',
            ],
        ],
        [
            'titulo' => '7. Pesada con lectura QR',
            'parrafos' => [
                'La pesada se realiza en planta, cuando la mercadería ya está preparada y etiquetada. Cada caja lleva un código QR con la información de peso real.',
                'En la edición del pedido → botón Pesada → se abre el modal Pesada del Pedido.',
                'Al escanear, el lector debe dejar el código en el campo Lectura QR. El contenido viene separado por punto y coma (;).',
            ],
            'tabla' => [
                'caption' => 'Campos del QR (orden)',
                'headers' => ['Posición', 'Dato'],
                'rows' => [
                    ['1', 'Id / número de caja'],
                    ['2', 'SKU del artículo'],
                    ['3', 'Piezas de la caja'],
                    ['4', 'Kilos (peso real de la caja)'],
                    ['5', 'Lote'],
                    ['6', 'Fecha de vencimiento'],
                ],
            ],
            'items' => [
                'Enfocar el campo Lectura QR (se activa automáticamente al abrir el modal).',
                'Escanear la etiqueta de cada caja.',
                'El sistema busca el renglón del pedido con el mismo SKU y donde los kilos pedidos superen lo ya pesado.',
                'Agrega una fila en la tabla de pesada y acumula los kilos del QR en la columna Pesada del renglón correspondiente.',
                'Repita hasta completar todas las cajas del pedido.',
                'Presione Acepta Pesadas y luego Guarde el pedido.',
                'No se puede escanear dos veces la misma caja ni superar el total de kilos pedidos del artículo.',
                'Para corregir una lectura errónea, elimine la fila en la tabla de pesada antes de guardar.',
            ],
        ],
        [
            'titulo' => '8. Facturación y remito',
            'parrafos' => [
                'La facturación se ejecuta desde la edición del pedido, una vez que la pesada refleja el peso real a entregar.',
                'Verifique que el pedido esté en estado Pendiente y con pesada cargada → presione Factura → se abre el modal Facturación de Pedido con los ítems pendientes.',
                'En EL BIERZO la cantidad que va a la factura es la pesada (kilos reales), no los kilos teóricos del pedido. Si hay descuento por línea, el sistema puede calcular bonificación sobre la pesada antes de facturar.',
                'Al confirmar, el sistema genera remito (tipo REM, letra R) y factura fiscal con emisión ARCA. En repartos especiales puede generar dos comprobantes (división entre unidades de negocio).',
                'Revise totales en el modal → Genera Factura. Si todo es correcto, verá el número de factura. El pedido pasa a estado Facturado y ya no admite edición.',
            ],
            'tabla' => [
                'caption' => 'Campos del modal de factura',
                'headers' => ['Campo', 'Descripción'],
                'rows' => [
                    ['Tipo de transacción', 'Factura A, B, etc. según condición IVA del cliente.'],
                    ['Punto de venta', 'PV fiscal para la factura (predeterminado EL BIERZO: PV 5).'],
                    ['Pto. venta del remito', 'PV para el remito de traslado (predeterminado: PV 1).'],
                    ['Actividad ARCA', 'Actividad fiscal; se completa desde el PV seleccionado.'],
                    ['Cantidad de bultos', 'Se precarga con total de cajas; obligatorio (entre 1 y 999999).'],
                    ['Leyendas', 'Texto adicional en la factura.'],
                ],
            ],
        ],
        [
            'titulo' => '9. Emisión fiscal (ARCA)',
            'parrafos' => [
                'La factura se emite contra ARCA (ex AFIP) mediante el webservice configurado en el punto de venta.',
            ],
            'items' => [
                'Punto de venta con certificado válido y numeración habilitada.',
                'Actividad ARCA asignada al PV.',
                'Cliente con CUIT/CUIL/DNI y condición IVA correctos.',
                'Para montos elevados, respetar límites de FCE según configuración (LIMITE_FCE en facturación EL BIERZO).',
                'Si la emisión falla, el sistema muestra el error y no deja el comprobante en estado definitivo. Corrija datos e intente nuevamente.',
            ],
        ],
        [
            'titulo' => '10. Herramientas del listado y reportes',
            'parrafos' => [
                'Además del listado operativo, existen reportes bajo Ventas (según permisos del menú):',
            ],
            'items' => [
                'Reporte de pedidos',
                'Kilos pedidos (totalizado por reparto o abierto por ítem)',
                'Kilos por categoría (totalizado por categoría y artículo)',
                'Reporte de kilos por pedido',
                'Reporte total de pedidos',
                'Reporte general de pedidos',
                'Reporte de consumo de material',
                'El listado principal también exporta a PDF, Excel y CSV con columnas de cajas, piezas, kilos, pesada, reparto y estado.',
            ],
            'parrafos2' => [
                'Varios reportes de Ventas permiten acotar los pedidos por reparto (transporte). La misma regla aplica en todos los que muestran los campos Desde y Hasta reparto:',
            ],
            'tabla' => [
                'caption' => 'Filtro de repartos en reportes (regla general)',
                'headers' => ['Modo', 'Cómo cargarlo', 'Ejemplo'],
                'rows' => [
                    ['Todos los repartos', 'Deje vacíos Desde y Hasta.', 'Sin valor en ninguno de los dos campos'],
                    ['Repartos puntuales', 'En Desde escriba los códigos separados por coma (sin usar Hasta).', '1,4,6 (incluye reparto 1, 4 y 6)'],
                    ['Rango', 'Complete Desde y Hasta, o escriba desde/hasta en Desde con barra (/).', 'Desde 1 · Hasta 10, o 1/10 en Desde'],
                    ['Un solo reparto', 'Solo en Desde, deje Hasta vacío.', '5 (solo reparto 5)'],
                ],
            ],
            'nota' => 'Al consultar, el encabezado del reporte y las exportaciones PDF/Excel muestran el criterio aplicado (por ejemplo «Todos», «Repartos 1, 4, 6» o «1 al 10»). Pulse Enter en el código para validar un reparto individual; si cargó una lista separada por comas, el sistema la interpreta como varios repartos y no busca un código único.',
        ],
        [
            'titulo' => '11. Cierre de pedidos y anulaciones',
            'captura_id' => 'pedido_cerrar',
            'parrafos' => [
                'Cierre masivo (ventas/pedido/cerrar): permite cerrar pedidos hasta una fecha con un motivo de cierre. Uso administrativo para pedidos vencidos o no concretados.',
                'Anulación de ítems: desde la edición, ícono de anulación por renglón. Registra historial con cliente y motivo.',
                'Eliminación de pedido completo: solo pedidos Pendientes, desde el listado (icono eliminar con confirmación).',
            ],
        ],
        [
            'titulo' => '12. Permisos principales',
            'tabla' => [
                'caption' => 'Permisos frecuentes',
                'headers' => ['Permiso', 'Uso'],
                'rows' => [
                    ['listar-pedidos', 'Ver listado e imprimir pedidos'],
                    ['crear-pedidos', 'Alta de pedidos (vendedores remotos)'],
                    ['editar-pedidos', 'Modificar, pesar, facturar'],
                    ['borrar-pedidos', 'Eliminar pedidos pendientes'],
                    ['borrar-items-pedidos', 'Quitar renglones'],
                    ['cierre-de-pedidos', 'Cierre masivo'],
                    ['listar-factura', 'Reimprimir factura emitida'],
                    ['entregar-articulo-sin-cargo-pedido-venta', 'Ítems bonificados sin cargo'],
                ],
            ],
        ],
        [
            'titulo' => '13. Errores frecuentes y buenas prácticas',
            'tabla' => [
                'caption' => 'Problema → solución',
                'headers' => ['Situación', 'Qué hacer'],
                'rows' => [
                    ['No puede generar pedidos con más de 42 ítems', 'Dividir en dos pedidos o consolidar líneas.'],
                    ['Cliente moroso / proforma / no facturar', 'Resolver con administración antes de facturar; el sistema alerta en pantalla.'],
                    ['Kilos pesados superan kilos pedidos', 'Revise si escaneó caja de más o corrija cantidades pedidas con planta.'],
                    ['Caja ya leída', 'QR duplicado; no escanee dos veces la misma caja.'],
                    ['No factura sin actividad ARCA', 'Complete actividad en el PV o seleccione otro PV fiscal válido.'],
                    ['No factura sin bultos', 'Indique cantidad de bultos ≥ 1 en el modal (normalmente = total cajas).'],
                    ['Pedido no pendiente', 'Solo se facturan pedidos Pendientes; reactive si estaba Suspendido.'],
                    ['Impresión física no sale', 'Configure salida en el listado (impresora/comando del usuario).'],
                ],
            ],
            'items' => [
                'Buenas prácticas — vendedor remoto: cargar reparto y lugar de entrega correctos; usar UMD coherente; dejar pesada en cero; usar leyendas para observaciones; confirmar que el pedido aparece Pendiente tras guardar.',
                'Buenas prácticas — planta: imprimir listado al recibir pedidos del día; pesar todas las cajas antes de facturar; verificar Total pesados; facturar el mismo día del despacho cuando sea posible.',
            ],
        ],
    ],
];
