<?php

/**
 * Manual de usuario — Facturación, remitos, COT y certificados sanitarios.
 * Solo El Bierzo. Operatoria de planta y administración.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Facturación, remitos, COT y certificados sanitarios',
    'version' => '1.0',
    'fecha' => null,
    'empresa' => 'EL BIERZO',
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Para qué es este manual',
            'parrafos' => [
                'Este manual es la operatoria de facturación de El Bierzo en Anita ERP: factura a mano, factura desde un pedido, factura desde un remito, remito (incluido el remito Z), código de operación de traslado (COT) ante ARBA y certificado sanitario SENASA.',
                'Está en el Centro de ayuda, junto al manual de pedidos. El de pedidos explica la carga, la pesada y la política comercial del cliente. Este empieza cuando ya hay mercadería para documentar, trasladar o facturar.',
                'La letra del comprobante fiscal no se elige a mano. Sale de la condición de IVA del cliente: A, B u otra. El código visible queda así: FAC A-00010-00001234 (tipo, letra, punto de venta de cinco dígitos y número de ocho).',
            ],
            'tabla' => [
                'caption' => 'Pantallas de este manual',
                'headers' => ['Pantalla', 'Menú', 'Ruta'],
                'rows' => [
                    ['Facturas', 'Ventas → Facturas', 'ventas/factura'],
                    ['Pedidos', 'Ventas → Pedidos', 'ventas/pedido'],
                    ['Remitos', 'Ventas → Remitos', 'ventas/remito'],
                    ['Asignar remitos a facturas', 'Ventas, al lado de Remitos', 'ventas/asignacion-remito-factura'],
                    ['COT electrónico ARBA', 'Ventas → COT electrónico ARBA', 'ventas/cot-electronico'],
                    ['Certificados sanitarios', 'Ventas → Certificados sanitarios', 'ventas/certificado-sanitario'],
                    ['Configuración COT', 'Configuración → Configuración por módulo → Ventas', 'ventas/cot-configuracion'],
                ],
            ],
        ],
        [
            'titulo' => '2. Antes de facturar: el cliente',
            'parrafos' => [
                'La política comercial del cliente define hasta dónde llega el circuito. El nombre del tipo de suspensión es el motivo (deuda, proforma, etc.). La cobranza sigue disponible en todos los casos. La nota de crédito sí se puede emitir aunque el cliente sea moroso, proforma o suspendido.',
            ],
            'tabla' => [
                'caption' => 'Qué se puede hacer',
                'headers' => ['Política', 'Pedido', 'Remito', 'Factura'],
                'rows' => [
                    ['Normal', 'Sí', 'Sí', 'Sí'],
                    ['Moroso', 'Sí', 'No', 'No'],
                    ['Proforma', 'Sí', 'Sí', 'No, hasta el cobro'],
                    ['Suspendido', 'No aparece para un alta nueva', 'No', 'No'],
                ],
            ],
            'parrafos2' => [
                'Mensajes habituales: «El cliente está suspendido: no se puede facturar.», «es moroso: no se puede facturar.», «es proforma: se puede pedir y boletar, no se puede facturar hasta el cobro.». El cliente DESPACHO no se factura ni se remite: el pedido se cumple con Transferir al despacho (capítulo del stock del depósito). Si ARCA marca problemas de padrón, el sistema corta con «Problemas en ARCA: no puede operar con este cliente.».',
                'Un pedido o un remito ya grabado se puede abrir aunque el cliente haya pasado a suspendido después. Facturar ese documento sigue bloqueado.',
            ],
        ],
        [
            'titulo' => '3. Factura a mano',
            'parrafos' => [
                'Ventas → Facturas → Nuevo. Sirve para un comprobante que no nace de un pedido ni de un remito ya cargado. En El Bierzo, guardar esta factura no crea un remito. El remito nace al facturar un pedido o al facturar un remito que ya existe.',
                'El alta usa la grilla de cajas, piezas y kilos. La cantidad que se factura es el kilo del renglón. La pesada de planta no interviene en esta pantalla: eso es del pedido.',
            ],
            'items' => [
                'Elegí el tipo de transacción (código, Enter, F1 o lupa). Por defecto queda el tipo de factura del usuario. Si el total supera el tope de factura de crédito electrónica ($ 5.549.862), el sistema puede sugerir FCE.',
                'Elegí el cliente. En esta pantalla solo aparecen clientes a los que se puede facturar.',
                'Completá vendedor, lugar de entrega, fecha, moneda, reparto, punto de venta, actividad ARCA, depósito y punto de venta del remito. El punto de venta del remito es obligatorio en pantalla aunque esta factura a mano no emita remito.',
                'Agregá renglones. Artículo facturable con F1 o lupa, o un concepto sin artículo. Cargá cajas, piezas y kilos. El precio de mercadería viene de la lista y no se tipea.',
                'Revisá el pie: total de cajas, unidades, kilos y la tabla Concepto / Tasa / Importe.',
                'Guardar. Si ARCA responde, el mensaje es «Comprobante {código} generado con éxito» y vuelve al listado.',
            ],
            'tabla' => [
                'caption' => 'Campos de cabecera',
                'headers' => ['Campo', 'Qué cargar'],
                'rows' => [
                    ['Tipo de transacción', 'FAC, FCE, NC, ND u otro. La letra la pone el IVA del cliente.'],
                    ['Comprobante ref.', 'Solo en nota de crédito o débito electrónica. Ejemplo: FCE A-00008-00001234.'],
                    ['Punto de venta', 'Punto fiscal. El sistema lo recuerda por usuario.'],
                    ['Vendedor', 'Obligatorio. Código y lupa.'],
                    ['Lugar de entrega', 'Si el cliente tiene lugares cargados, hay que elegir uno.'],
                    ['Dto. línea / Dto. pie', 'Porcentajes. En El Bierzo el precio de lista queda en el renglón y el descuento va al pie.'],
                    ['Reparto', 'Código de transporte. Vacío no corta la factura a mano.'],
                    ['Depósito', 'Código de depósito, con consulta. No es un desplegable de tabla.'],
                    ['Leyendas', 'Texto libre del comprobante.'],
                ],
            ],
            'parrafos2' => [
                'La primera vez el sistema propone puntos de venta y después recuerda lo que el usuario eligió. Usuarios de producción clarisad y cdacurso: factura 00010 y remito 00001. El resto: factura 00008 y remito 00099.',
                'Sin artículo hay que indicar alícuota (Exento, 10,5 % o 21 %) y un concepto: en El Bierzo el concepto es obligatorio en ese renglón. El ícono de regalo deja el ítem sin cargo si el usuario tiene el permiso. Tope de la grilla: la misma lógica de cantidades del pedido, con dos decimales en caja, pieza y kilo.',
                'Abrir un comprobante ya emitido sirve para verlo, reimprimirlo o generar una nota de crédito. El botón Actualizar de esa pantalla no reescribe la factura fiscal.',
            ],
        ],
        [
            'titulo' => '4. Qué pasa al guardar la factura',
            'parrafos' => [
                'Al guardar, el sistema vuelve a calcular impuestos, pide número y graba la venta, los ítems, el asiento y la cuenta corriente. ARCA responde antes de volver al listado. La copia a Anita (comprobante y cuenta corriente) sale después, en segundo plano, para no hacer esperar al operador. Si Anita falla, la factura ya está numerada en ARCA y el sistema reintenta.',
            ],
            'tabla' => [
                'caption' => 'De dónde sale el número',
                'headers' => ['Modo del punto de venta', 'Quién numera'],
                'rows' => [
                    ['Electrónico (CAE)', 'ARCA entrega el número y el CAE.'],
                    ['CAEA o manual', 'El ERP numera. En CAEA, Anita también avanza su numerador al cerrar.'],
                ],
            ],
            'items' => [
                'Si ARCA no responde por un corte, el punto 00010 y el punto 00009 reintentan solos en el punto CAEA 00005. El aviso dice «Comprobante emitido con CAEA por contingencia ARCA.». La espera de ARCA en este circuito es de 18 segundos.',
                'El punto 00008 es de prueba: no tiene numerador en Anita. El último número CAEA forzado es 43; el próximo sale 44.',
                'La impresión no es automática por el solo hecho de guardar. Si hay un programa de impresión marcado para dispararse al facturar, se abre la sesión (PDF y/o impresora, según ese programa).',
            ],
            'parrafos2' => [
                'Toda factura, nota de débito o nota de crédito letra B calcula percepción de ingresos brutos de CABA, aunque la letra B apague el resto de las percepciones de otras provincias. La alícuota sale del padrón AGIP si está cargado; si no, de la tasa de la provincia. Si el importe no llega al mínimo de percepción, no se percibe. Siguen vigentes el certificado de no retención y las exclusiones de la ficha del cliente. La percepción de IVA del 3 % en letra B no se cobra. La de no categorizado sí, cuando corresponde.',
            ],
        ],
        [
            'titulo' => '5. Facturar un pedido',
            'parrafos' => [
                'El botón Factura está en el pedido (ventas/pedido/{id}/editar) si el pedido no está Facturado ni Suspendido y no fue transferido al despacho. También está en el listado, en la fila, cuando el pedido está Pendiente, no fue al despacho y hay al menos un ítem pendiente con pesada. Si no cumple, el mensaje es «El pedido no está pesado o no se puede facturar.».',
                'Se factura la pesada, no los kilos teóricos del pedido. Entran solo ítems pendientes con pesada mayor a cero. Cajas y piezas van las del pedido. El precio es el del pedido; si el renglón tiene descuento, la factura toma el precio de lista menos ese porcentaje. En El Bierzo los descuentos de pie del modal están ocultos: vale el descuento del pedido. Si hay descuento de línea, los kilos bonificados se redondean a un decimal.',
            ],
            'items' => [
                'Con la pesada cargada, pulsá Factura. Se abre Facturación de Pedido.',
                'La fecha es la del día y es solo lectura. Revisá tipo de transacción, punto de venta, cliente, lugar de entrega, punto de venta del remito y actividad ARCA.',
                'La grilla muestra artículo, descripción, unidad, cajas, piezas, Pesada, bonificación y precio. Podés cargar bultos y leyenda.',
                'El sistema calcula un preview. Si hay error, lo muestra en el aviso del cuadro.',
                'Genera Factura.',
            ],
            'tabla' => [
                'caption' => 'Qué queda grabado',
                'headers' => ['Documento', 'Resultado'],
                'rows' => [
                    ['Factura', 'Número y CAE o CAEA. Impuestos iguales a la factura a mano, incluida la percepción de CABA en letra B.'],
                    ['Remito', 'REM letra R, con el número que Anita da para ese punto de venta de remito. Queda Facturado y vinculado a la factura.'],
                    ['Pedido', 'Pasa a Facturado. Los ítems sin pesada se cierran como falta de stock.'],
                    ['Remito que ya existía', 'Si el pedido ya tenía un remito pendiente sin factura, no se crea otro: se marca facturado y se engancha a la factura.'],
                ],
            ],
            'parrafos2' => [
                'No se emite una nota de crédito desde este botón: «No se puede generar una nota de crédito desde un pedido. Use un tipo de factura.». Si el total da cero, no emite. Un doble clic queda trabado 180 segundos: «Ya hay una facturación en curso de este pedido. Espere a que termine.».',
                'Después de facturar, si el programa de impresión está en automático, entra a la sesión con factura y remito. Desde el pedido también se reimprime cada comprobante por su código, y el pedido con Listar Pedido o Mi impresora.',
            ],
        ],
        [
            'titulo' => '6. Facturar el reparto desde el listado',
            'parrafos' => [
                'En el subtotal del reparto del listado de pedidos hay un ícono de factura. Factura, uno por uno, los pedidos pesados de ese reparto que entran en los filtros del listado. Hace falta el permiso facturar-reparto-pedidos.',
            ],
            'items' => [
                'Se abre Facturar reparto, con pedido, cliente, cajas, unidades, kilos y kilos pesados.',
                'Elegí tipo de transacción, punto de venta de la factura y el punto de venta del remito (en pantalla figura como punto de venta del pedido). La actividad ARCA se completa sola.',
                'Genera facturas. Cada pedido sigue el mismo circuito del capítulo anterior. Si uno falla, los demás pueden salir igual.',
                'Al terminar, Facturas emitidas permite imprimir las copias del programa de impresión.',
            ],
            'parrafos2' => [
                'Si no hay nada pesado: «No hay pedidos pesados para facturar en este reparto.». Si falta un dato de cabecera: «Debe indicar tipo de transacción, punto de venta de factura y punto de venta del remito.» o «Debe asignar actividad ARCA.». Si ninguno salió: «No se pudo facturar ningún pedido del reparto.».',
            ],
        ],
        [
            'titulo' => '7. Pedido del cliente DESPACHO y stock del depósito',
            'parrafos' => [
                'El cliente DESPACHO es interno. El pedido sirve para reponer el depósito del reparto. No se factura, no se le hace remito y no entra en Facturar reparto. El cumplimiento no es otra pantalla: es el botón Transferir al despacho.',
                'La venta de un cliente normal descuenta el stock del depósito asignado al reparto. Si ese reparto no tiene depósito, descuenta el depósito de ventas. El pedido DESPACHO hace el camino inverso de la reposición: saca la mercadería del depósito de ventas y la entra en el depósito del reparto, para que después las ventas de ese reparto tengan saldo.',
            ],
            'items' => [
                'Cargá el pedido con el cliente DESPACHO, el reparto que se repone y los artículos. Guardá.',
                'Pesá y guardá la pesada. La transferencia usa la pesada de cada ítem. Si un ítem no tiene pesada, usa los kilos del pedido. Un ítem en cero no viaja.',
                'Transferir al despacho. La confirmación dice: «¿Transferir la pesada guardada al depósito del despacho? No se factura este pedido. Guarde la pesada antes si acaba de pesarla.»',
                'Se genera una transferencia de mercadería (tipo TRA) desde el depósito de ventas hacia el depósito del reparto. El pedido queda Transferido. Los ítems que viajaron quedan Entregados.',
                'Si el tipo de transferencia pide aprobación, la TM queda pendiente de aprobación. El pedido igual queda Transferido.',
                'En el pedido aparece el enlace a esa transferencia. Si el pedido existía en Anita, se cierra ahí como entregado: a partir de ese momento el circuito vive solo en el ERP.',
            ],
            'tabla' => [
                'caption' => 'Depósito y cortes',
                'headers' => ['Situación', 'Qué pasa'],
                'rows' => [
                    ['Repartos 1 y 90', 'Tienen asignado el depósito código 2 (DESPACHO).'],
                    ['Otro reparto', 'Se repone igual si en el transporte tiene un depósito asignado.'],
                    ['Depósito de Surmar', 'Si el depósito del reparto es de la otra empresa, se usa el del mismo código en la empresa del pedido.'],
                    ['Reparto sin depósito', '«El reparto no tiene depósito de despacho asignado.»'],
                    ['Sin pesada ni kilos', '«No hay ítems con pesada o kilos para transferir. Guarde la pesada antes de transferir.»'],
                    ['Ya transferido', '«El pedido ya fue transferido al despacho.» No se vuelve a mover el stock.'],
                    ['Cliente que no es DESPACHO', '«Solo el pedido del cliente DESPACHO se transfiere. El resto se factura.»'],
                    ['Importar remito Anita', 'El cliente DESPACHO no se importa. Ese circuito es solo del ERP.'],
                ],
            ],
            'parrafos2' => [
                'El botón está en la edición del pedido, con el permiso transferir-pedido-despacho (administrador, encargado de administración, despacho y facturación). Un segundo clic mientras corre dice «Ya hay una transferencia en curso de este pedido. Espere a que termine.». El mensaje de éxito nombra el código de la transferencia y avisa si Anita se cerró.',
            ],
        ],
        [
            'titulo' => '8. Remito',
            'parrafos' => [
                'Ventas → Remitos. El alta siempre graba tipo REM, letra R. El código queda REM R {punto de venta}-{número}. El número lo pide Anita. El estado inicial es Pendiente. También se puede generar un remito desde el pedido, con el botón Remito, sin facturar: copia los ítems y queda Pendiente.',
            ],
            'items' => [
                'Crear remito. Cliente (código, Enter o F1). No lista morosos ni suspendidos.',
                'Vendedor y reparto, los dos obligatorios, por código y lupa.',
                'Lugar de entrega. Si el cliente tiene lugares, hay que elegir uno. Fecha del comprobante: hoy, solo lectura. Fecha de entrega: editable y obligatoria.',
                'Zona de venta y, si hace falta, lote de stock.',
                'Renglones: artículo facturable, unidad, cajas, piezas, kilos, descuento y precio. Máximo 42 ítems.',
                'Leyenda. El pie muestra totales y el valor asegurado.',
                'Guardar. El mensaje es «Remito {id} {código} creado con exito» y vuelve al listado.',
            ],
            'tabla' => [
                'caption' => 'Datos que conviene revisar',
                'headers' => ['Dato', 'Regla'],
                'rows' => [
                    ['Valor asegurado', 'Neto de los renglones no anulados menos 15 %. Es solo lectura. Anita lo usa como seguro.'],
                    ['Precio', 'Solo lectura, salvo el caso del capítulo 9.'],
                    ['Listado', 'Código, fechas, cliente, cajas, piezas, kilos, reparto y estado. Si no tiene factura, badge Sin factura.'],
                    ['Borrar', 'Solo si está Pendiente. Pregunta «Desea eliminar el remito?».'],
                    ['Traer remito', 'Busca por número, sin el resto de filtros. Si no está: «No se encontró ningún remito con ese número.».'],
                ],
            ],
        ],
        [
            'titulo' => '9. Remito Z (asignar kilos con F5)',
            'parrafos' => [
                'En planta, el remito Z es el alta de remito que se arma con Asignar kilos (F5). No es otro tipo de comprobante: lo que se guarda sigue siendo un REM letra R. F5 está en el alta del remito. También se abre con la tecla F5 (Ctrl+F5 sigue siendo recargar el navegador). Hace falta el permiso de crear remitos.',
                'El cuadro toma los kilos facturados hoy de ese reparto. Suman facturas y notas de débito. Restan las notas de crédito. Remitos y cobranzas de ese origen no entran. El porcentaje, de 0 a 100, es la parte que se deja afuera: 0 deja el total de esos kilos y 100 no deja kilos.',
            ],
            'items' => [
                'En el alta, pulsá Asignar kilos (F5).',
                'Confirmá el reparto (código, Enter o F1). Si el formulario ya tiene reparto, lo usa.',
                'Cargá el porcentaje y Acepta.',
                'La grilla se reemplaza con los renglones (kilos, piezas y precio). La fecha del remito pasa a ser hoy.',
                'Revisá y Guardar. F5 no graba solo.',
            ],
            'parrafos2' => [
                'El aviso de éxito nombra la cantidad de ítems y de comprobantes leídos, y pide revisar antes de guardar. Si un código no existe en el ERP, lo lista como «Sin artículo ERP». Errores habituales: «Reparto inválido», «Porcentaje inválido», «Reparto sin código Anita», «No hay comprobantes del día para ese reparto», «Ningún SKU existe en el ERP» y «Sin kilos resultantes tras aplicar el porcentaje».',
            ],
        ],
        [
            'titulo' => '10. Precio del remito e importar desde Anita',
            'parrafos' => [
                'El precio del renglón es solo lectura. Se puede tipear solo si se cumplen las tres condiciones: el usuario tiene el permiso modificar-precio-remito, el punto de venta del remito es el 00006 y ese punto es de Surmar. Si no, al guardar se conserva el precio de la lista o el que vino importado.',
                'En el listado, Importar Anita trae remitos que ya están en Anita y todavía no están en el ERP, o actualiza los que no están facturados. Hace falta el permiso ejecutar-importar-remito-anita.',
            ],
            'tabla' => [
                'caption' => 'Importar Anita',
                'headers' => ['Dato', 'Cómo se carga'],
                'rows' => [
                    ['Origen', 'Bierzo (REM R 1) o Surmar (REM R 6). En Surmar el cliente se busca por CUIT.'],
                    ['Fecha', 'Obligatoria. No trae remitos anteriores a 2023.'],
                    ['Repartos', 'Lista (por ejemplo 95,12), rango (10/20) o vacío para todos.'],
                    ['Ya facturado en el ERP', 'No se pisa. El mensaje lo dice.'],
                    ['Cliente DESPACHO', 'No se importa. Ese circuito es solo del ERP.'],
                ],
            ],
            'parrafos2' => [
                'El PDF del remito sale por la sesión de impresión, formulario REMITO, desde el listado o con Listar Remito en la edición. El número es el último REM R de Anita para ese punto de venta, más uno. Si Anita no responde: «No se pudo obtener numeración de remito desde Anita.».',
            ],
        ],
        [
            'titulo' => '11. Facturar un remito ya emitido',
            'parrafos' => [
                'Esto es distinto de asignar un remito a una factura que ya existe (capítulo 11). Acá se emite la factura fiscal del remito que todavía no tiene factura.',
                'Abrí el remito. El botón Factura aparece si no tiene factura asociada y el estado no es Facturado, Suspendido ni Anulado. Pendiente y Entregado sí se pueden facturar. La fuente de verdad es si tiene factura vinculada. El botón no sale si el cliente es el de despacho interno.',
            ],
            'items' => [
                'Pulsá Factura. Se abre Facturación de Remito, con la misma cabecera que el pedido: fecha del día solo lectura, tipo, punto de venta, cliente, punto de venta del remito y actividad. Los descuentos de pie están ocultos.',
                'La columna se llama Pesada, pero el número que se factura es el kilo del remito.',
                'Genera Factura.',
            ],
            'tabla' => [
                'caption' => 'Qué queda vinculado',
                'headers' => ['Dato', 'Resultado'],
                'rows' => [
                    ['Número de remito', 'Se conserva. No pide otro número.'],
                    ['Estado del remito', 'Facturado, y queda ligado a la factura en los dos sentidos.'],
                    ['Renglones', 'Cajas y piezas se copian. Precio y descuento, los del remito. Kilos en cero: «Artículo {sku} sin kilos».'],
                    ['Pedido de origen', 'Si el remito nació de un pedido, ese pedido también queda Facturado.'],
                    ['ARCA y Anita', 'CAE o CAEA antes de volver. Anita, después. Impresión por la sesión, igual que desde el pedido.'],
                ],
            ],
            'parrafos2' => [
                'Otros cortes: «Remito ya tiene factura asociada», «Remito ya facturado», «Remito suspendido: no se puede facturar», «Remito anulado: no se puede facturar», «No hay ítems pendientes para facturar del remito.», «El total del comprobante es 0. Revise precios del remito.» y «No se puede generar una nota de crédito desde un remito. Use un tipo de factura.». La política del cliente (moroso, proforma, suspendido) se aplica igual que en la factura a mano.',
            ],
        ],
        [
            'titulo' => '12. Asignar un remito a una factura ya emitida',
            'parrafos' => [
                'Asignar remitos a facturas no emite una factura nueva. Une un remito que quedó sin factura con una factura que quedó sin remito. El remito conserva su número y pasa a ser el remito de esa factura: toma cliente, fecha y artículos de la factura. También se entra desde el botón Asignar a facturas del listado de remitos.',
            ],
            'items' => [
                'Elegí la empresa. Es obligatoria. Cada consulta es de una sola empresa, para no mezclar comprobantes de empresas distintas.',
                'Desde es obligatorio (por defecto, hoy). Hasta es opcional. El reparto se puede filtrar por código.',
                'Vista Huérfanos (remitos sin factura y facturas sin remito) o Todos. Consultar.',
                'Marcá un par. La vista previa dice el nivel de coincidencia.',
                'Confirmá.',
            ],
            'tabla' => [
                'caption' => 'Cómo sugiere el par',
                'headers' => ['Nivel', 'Criterio'],
                'rows' => [
                    ['Excelente', 'Mismo cliente y fecha, o mismo cliente y kilos parecidos (diferencia hasta 15 %).'],
                    ['Bueno', 'Mismo cliente.'],
                    ['Regular', 'Misma fecha y distinto cliente. El remito toma los datos de la factura.'],
                    ['Distinto', 'Sin coincidencia de cliente ni de fecha. Igual se puede confirmar: el remito toma los datos de la factura.'],
                ],
            ],
            'parrafos2' => [
                'La sugerencia automática solo arma pares del mismo cliente. No se asignan remitos a facturas de gastronomía ni de estacionamiento, ni a comprobantes que no sean FAC o FCE. Si la factura ya tiene remito, o el remito ya tiene factura, el par se rechaza. Mensaje de éxito: «Se asignó 1 remito a la factura» o «Se asignaron N remitos a facturas».',
            ],
        ],
        [
            'titulo' => '13. COT electrónico (ARBA)',
            'parrafos' => [
                'El COT es el código de ARBA que acompaña el traslado. La pantalla Generación COT electrónico presenta los remitos del día y obtiene un COT por remito. El archivo que viaja se llama TB_{CUIT}_{planta}{puerta}_{fecha}_{secuencia}.txt. ARBA devuelve número de comprobante, número único y COT. El ERP guarda la sesión y permite imprimir la constancia.',
                'En El Bierzo el modo es por reparto: una fecha y uno o más repartos. El modo por guía (grilla de facturas y Excel de otro expreso) es de otra instalación. Si en configuración alguien lo cambia, esta pantalla deja de ser la de reparto. La configuración está en Configuración → Configuración por módulo → Ventas → Configuración COT ARBA. Ahí solo se elige el modo. Usuario, clave y domicilio de origen están en el entorno del servidor (ARBA_COT_*). El aviso de la pantalla lo dice, y también cómo probar la conexión.',
            ],
            'items' => [
                'Entrá a COT electrónico ARBA. Mirá el badge de ambiente (TEST o PROD) y que diga Modo reparto.',
                'Fecha facturas, obligatoria. Es la fecha de los remitos y facturas.',
                'En Repartos incluidos cargá código (lupa), dominio del camión y CUIT del chofer. El titular del CUIT se completa contra el padrón. Agregar reparto las veces que haga falta.',
                'Consultar remitos. Los pendientes listos salen tildados. Los ya emitidos y los sin importe no se pueden enviar.',
                'Revisá Control COT a enviar y el parcial de cada reparto: cantidad, kilos e importe sin IVA.',
                'Opcional: Imprimir COT al procesar, con la impresora de Mi impresora para el formulario COT.',
                'Procesar envío ARBA y confirmá. La confirmación muestra remitos, kilos e importe sin IVA.',
            ],
            'tabla' => [
                'caption' => 'De dónde salen los remitos',
                'headers' => ['Orden', 'Origen'],
                'rows' => [
                    ['1', 'Remito del día en Anita, del código de reparto elegido.'],
                    ['2', 'Remito del ERP del mismo día y transporte, si todavía no está en Anita.'],
                    ['3', 'Factura de Anita que tiene número de remito, solo si no hay remito físico con ese número.'],
                ],
            ],
            'parrafos2' => [
                'El COT declara el valor de la mercadería sin IVA. No se presenta un peso de relleno: si la factura o el remito no tienen un importe real, el renglón queda «Sin importe» y no viaja. El aviso lo dice: «No se presenta el COT con $1.». Un remito ya enviado aparece en gris, con badge Ya emitido, el COT y la sesión anterior.',
                'La identidad fiscal que se declara es la de la factura (tipo, letra, punto de venta y número), no la del remito. El CUIT del chofer, si se carga, tiene que cerrar el dígito verificador: «CUIT chofer inválida en reparto {código}». Vacío se acepta. Provincia Buenos Aires se manda como B y CABA como C.',
                'Si el artículo no tiene nomenclador, el archivo usa 160100 (fiambres) y unidad 3. El maestro de transporte conviene tener CUIT del chofer y patente; en la pantalla se pueden corregir para ese envío.',
                'Éxito: «Envío procesado. Comprobante ARBA {número}» y el detalle de la sesión. La constancia es una hoja por remito con COT y tiene que acompañar el traslado. Si la impresión falla después de un envío correcto, el COT ya está: «El envío se procesó, pero no se pudo imprimir el COT».',
                'Abajo, Sesiones de envío COT guarda los últimos 30 días, con filtros de fechas, ambiente y estado (OK o con errores). Una sesión con error también se guarda, para ver qué remito falló. Desde ahí se reimprime, se baja el PDF y se exporta el histórico.',
                'Probar conexión no envía remitos. Textos útiles: «Conexión OK», «Usuario o clave inválidos», «Usuario no habilitado», «Usuario bloqueado» y «No se pudo conectar con ARBA».',
            ],
        ],
        [
            'titulo' => '14. Certificado sanitario SENASA',
            'parrafos' => [
                'Ventas → Certificados sanitarios → Certificado sanitario SENASA. Arma la solicitud WEB de SENASA a partir de los pedidos cuya fecha de entrega es la del filtro, y solo con artículos que tienen código SENASA. Genera el XML (con frío y sin frío) y el PDF para presentar. El ERP no carga el trámite en el sitio de SENASA: se descarga el ZIP y se presenta aparte.',
                'El establecimiento de fábrica es 1154. En el PDF la razón social es Frig. El Bierzo S.A. El maestro Destinos SENASA (Ventas → Certificados sanitarios → Destinos SENASA) es la localidad y la provincia de cada zona de venta. El lugar del XML sale de ese maestro, no del domicilio libre tipeado en el cliente.',
            ],
            'items' => [
                'Generar certificado WEB.',
                'Fecha de entrega, obligatoria. Es la fecha de entrega del pedido, no la fecha de carga.',
                'Reparto, zona y cliente son opcionales. Vacío significa todos. El reparto se carga por código, Enter o F1.',
                'Dejá tildado «Si el pedido aún no está en el ERP, leerlo de Anita» si hace falta. Ese fallback no cambia el reparto de un pedido que ya está en el ERP: el reparto se cambia en Ventas → Pedido.',
                'Consultar pedidos.',
            ],
            'tabla' => [
                'caption' => 'Avisos antes de generar',
                'headers' => ['Aviso', 'Qué hacer'],
                'rows' => [
                    ['Artículos sin código SENASA (card roja)', 'Bloquea el botón. Abrí el ABM del artículo, cargá el SENASA y volvé a consultar. Si el artículo no está en el ERP, primero dalo de alta.'],
                    ['Desfasaje de reparto (card amarilla)', 'No bloquea. El pedido ya está en el ERP y Anita tiene otro expreso. Cambiá el transporte en el pedido del ERP y volvé a consultar.'],
                    ['Sin líneas SENASA', '«No hay pedidos con artículos SENASA para la fecha/filtros indicados.»'],
                    ['Producto de otro establecimiento sin amparo', 'Hay que cargar el certificado de origen. El jamón crudo de prefijo 9066 no exige ese amparo.'],
                ],
            ],
            'parrafos2' => [
                'El preview lista origen (ERP o Anita), pedido, cliente, transporte, SKU, kilos, cajas, piezas, frío y registro SENASA, con subtotal por pedido. Esas cantidades son las que van al certificado: revisalas antes de generar.',
                'Un cliente marcado para no emitir certificado no entra. Los pedidos suspendidos o anulados tampoco. Los ítems anulados y los SKU que empiezan con «texto» se saltean.',
            ],
        ],
        [
            'titulo' => '15. Datos del certificado, PDF y borrado',
            'parrafos' => [
                'Los datos del certificado aparecen cuando hay líneas y no quedan artículos sin SENASA.',
            ],
            'tabla' => [
                'caption' => 'Cabecera al generar',
                'headers' => ['Campo', 'Regla'],
                'rows' => [
                    ['Camión', 'Obligatorio. Código y lupa. Dominio y habilitación van al XML.'],
                    ['Temperatura', 'Por defecto 7.'],
                    ['Nro. remito', 'Opcional. Si es mayor a cero, entra al XML.'],
                    ['Precintos', 'Cantidad de 0 a 99, tomada del camión, y texto de hasta 15 caracteres.'],
                    ['Establ. destino', 'De 0 a 9999. En cero, el XML lleva las localidades SENASA. Si es mayor a cero, lleva el establecimiento destino y no las localidades.'],
                    ['Abrir por localidad', 'Un certificado por reparto y zona. Sin tilde: un certificado por reparto, aunque mezcle zonas.'],
                    ['Generar archivo WEB', 'Tildado, graba el XML. Destildado, graba el certificado sin archivo.'],
                ],
            ],
            'items' => [
                'Confirmá «¿Generar certificado(s) WEB?».',
                'Al volver al listado: «Certificado A-000123 generado.» o «N certificados sanitarios generados.».',
                'El listado muestra número, serie, fecha, camión, reparto, precinto, establecimiento destino, kilos y cajas. El pie suma el filtro completo, no solo la página.',
                'ZIP frío y ZIP sin frío son dos archivos del mismo certificado. SENASA no acepta el XML suelto: la descarga es el ZIP. El PDF es la solicitud para emitir y abre en otra pestaña.',
                'El ojo abre la ficha. Si faltaba el XML, el amparo o el destino, al abrir la ficha el sistema regenera los archivos.',
            ],
            'parrafos2' => [
                'La numeración la reserva Anita: serie A, B, C y siguientes, número de solicitud, y número interno o patagónico. Patagonia sale del destino o de las provincias del sur. En el PDF, tránsito o tránsito restringido depende de esa marca. Borrar un certificado no hace retroceder el numerador: el próximo sigue.',
                'No hay anulación ni edición. Se borra el registro, las líneas y los XML. El ícono rojo borra uno. Borrar historial pide un rango de fechas inclusive, muestra un preview y pide confirmación. «Se borraron N certificados sanitarios.» La acción queda en auditoría y no se deshace.',
                'Peso neto del XML = kilos. La cantidad del XML = piezas. Peso bruto = kilos más cajas (si las cajas redondean a cero, usa 1). Elaborado = fecha del certificado menos dos días. Vencimiento = elaborado más los días del artículo, o 45 si el artículo no los tiene.',
            ],
        ],
        [
            'titulo' => '16. Errores frecuentes de facturación',
            'tabla' => [
                'caption' => 'Mensajes y qué revisar',
                'headers' => ['Mensaje', 'Qué revisar'],
                'rows' => [
                    ['Cliente inexistente / No tiene Documento / No tiene CUIT', 'Código de cliente y documento en la ficha.'],
                    ['No se puede facturar: el pedido no tiene ítems pesados', 'Cargá la pesada de al menos un ítem.'],
                    ['El total del comprobante es 0 / Factura en 0', 'Precio de lista del artículo o precio del remito.'],
                    ['El artículo no tiene lista de precios asignada', 'Lista del cliente o del artículo, renglón indicado.'],
                    ['Debe elegir un punto de venta válido', 'Punto de venta de la factura y, si corresponde, el del remito.'],
                    ['No se pudo numerar el comprobante / Error numerador', 'Numerador del punto de venta. Reintentá. Si se repite, avisá a sistemas.'],
                    ['Ya existe un comprobante con ese punto de venta, tipo y número', 'El sistema reintenta. Si vuelve, no fuerces otro alta: avisá a sistemas.'],
                    ['La NCE/NDE requiere un comprobante FCE asociado', 'Referencia con formato FCE A-00008-00001234 y anulación S/N si la pide.'],
                    ['Debe seleccionar un lugar de entrega del cliente', 'El cliente tiene lugares y falta elegir uno.'],
                    ['No puede facturar cliente STOCK', 'Ese cliente no se factura por este circuito.'],
                    ['La factura quedó grabada, pero no hay un programa marcado como plan con envíos', 'La factura está bien. Falta el programa de impresión con el comprobante Envío.'],
                    ['No se encontraron remitos para la fecha y repartos indicados', 'Fecha, código de reparto y que el remito exista ese día.'],
                    ['Se agotaron las series de certificado SENASA (A-Z)', 'Avisá a sistemas: el numerador de series de Anita llegó al final.'],
                ],
            ],
        ],
        [
            'titulo' => '17. Permisos',
            'tabla' => [
                'caption' => 'Permiso y pantalla',
                'headers' => ['Permiso', 'Para qué'],
                'rows' => [
                    ['listar-factura / crear-factura / editar-factura', 'Listado e impresión, alta, y abrir un comprobante emitido.'],
                    ['generar-nota-de-credito', 'Nota de crédito o de débito de reversión.'],
                    ['crear-factura o editar-pedidos', 'Botón Factura del pedido y facturar desde el listado.'],
                    ['facturar-reparto-pedidos', 'Facturar todos los pedidos pesados de un reparto. El pedido DESPACHO no entra.'],
                    ['transferir-pedido-despacho', 'Transferir al despacho: mueve el stock del pedido DESPACHO al depósito del reparto.'],
                    ['listar-remitos / crear-remitos / editar-remitos / actualizar-remitos / borrar-remitos', 'Listado, alta, abrir, guardar y borrar un remito pendiente. Crear remitos también habilita F5.'],
                    ['modificar-precio-remito', 'Precio editable, y solo en el punto de venta 00006 de Surmar.'],
                    ['ejecutar-importar-remito-anita', 'Importar Anita en el listado de remitos.'],
                    ['listar-asignacion-remito-factura / ejecutar-asignacion-remito-factura', 'Consultar la asignación y confirmar el vínculo.'],
                    ['procesar-cot-electronico', 'Pantalla COT, envío, constancia y export del histórico.'],
                    ['editar-cot-configuracion / actualizar-cot-configuracion', 'Ver y guardar el modo del COT.'],
                    ['listar-certificado-sanitario / crear-certificado-sanitario / borrar-certificado-sanitario', 'Listado y PDF, generar, y borrar uno o el historial.'],
                ],
            ],
            'parrafos' => [
                'El COT operativo está para administrador, encargado de contaduría y encargado de impuestos. La configuración del modo, para administrador, encargado de sistemas, encargado de contaduría y encargado de administración. El certificado está para administrador, encargado de administración, despacho, facturación y encargado de contaduría. Si un botón no aparece, el permiso de la fila es el que falta.',
            ],
        ],
    ],
];
