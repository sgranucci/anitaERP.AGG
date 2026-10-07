<?php

/**
 * Manual de usuario — Módulo de logística.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Módulo de logística',
    'version' => '1.0',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Para qué sirve',
            'parrafos' => [
                'Logística es el lugar donde se pide un insumo del catálogo o un trabajo de traslado, y donde después se sigue hasta que alguien lo recibe. El artículo sigue siendo el maestro de stock: logística no crea un segundo código. Publica artículos que ya existen y arma un portal para pedirlos.',
                'Hay un solo documento, la solicitud. Tiene dos naturalezas. Insumos: se arma un pedido con artículos del catálogo. Trabajos: se completa un formulario según el tipo (traslado de slots, retiro de materiales, traslado de elementos o recambio de butacas).',
                'El módulo registra el pedido, el plazo, la aprobación cuando hace falta, y el cumplimiento. El movimiento de stock, la transferencia y la requisición se cargan en sus propias pantallas. La solicitud guarda el número de ese comprobante y lo muestra. No descuenta stock sola.',
            ],
        ],
        [
            'titulo' => '2. Dónde está',
            'parrafos' => [
                'El menú operativo es Logística. Adentro está Solicitudes. La parametrización está en Configuración, Configuración por módulo, Logística, Catálogo de logística. Hay un atajo con la misma pantalla bajo el menú Logística para quien tenga el rol de encargado.',
                'La solapa Catálogo logística del artículo (Stock, Artículos) es la que publica cada SKU en el portal. Sin esa solapa el artículo no aparece para pedirlo, aunque exista en el maestro.',
            ],
            'tabla' => [
                'caption' => 'Pantallas',
                'headers' => ['Pantalla', 'Menú', 'Para qué'],
                'rows' => [
                    ['Solicitudes', 'Logística → Solicitudes', 'Listado, filtros, Excel, PDF y CSV. Desde acá se abre cada solicitud y se crea una nueva.'],
                    ['Nueva solicitud', 'Botón Nueva solicitud', 'Pantalla partida: a la izquierda el tipo y la categoría o el trabajo; a la derecha el catálogo o el formulario.'],
                    ['Ficha de la solicitud', 'Número en el listado', 'Datos del pedido, ítems, adjuntos, plazo, vínculo y las acciones de cumplimiento.'],
                    ['Catálogo de logística', 'Configuración → Configuración por módulo → Logística', 'Categorías, tipos, trabajos, ubicaciones, quién ve qué, plazos, tope y monto de aprobación.'],
                    ['Artículo', 'Stock → Artículos, solapa Catálogo logística', 'Publicar el SKU, marcarlo favorito, limitar centros de costo y habilitarlo a un rol o a una persona.'],
                ],
            ],
        ],
        [
            'titulo' => '3. Numeración',
            'parrafos' => [
                'Hay una sola secuencia por año calendario. El prefijo cambia según el tipo. El número se ve como prefijo, año y cuatro dígitos: SOL-2026-0001, TRB-2026-0002, RET-2026-0003. Insumos usan SOL. Los trabajos usan TRB, salvo el retiro de materiales, que usa RET.',
            ],
        ],
        [
            'titulo' => '4. Quién ve el catálogo',
            'parrafos' => [
                'La visibilidad es por defecto cerrada. Si no hay una fila que habilite, la persona no ve ese tipo, esa categoría ni ese ítem. Hay cuatro capas y las cuatro tienen que dejar pasar para que un artículo se pueda pedir.',
                'Una fila puesta a un usuario pisa la del rol, tanto para habilitar como para negar. Si el rol ve EPP y a esa persona se le carga el ítem en No, esa persona no lo ve.',
                'El centro de costo es la cuarta capa y vive en el artículo. Si no se carga ningún centro, cualquier centro puede pedirlo. Si se cargan centros, solo esos pueden.',
            ],
            'tabla' => [
                'caption' => 'Capas de visibilidad',
                'headers' => ['Capa', 'Dónde se carga', 'Qué controla'],
                'rows' => [
                    ['Tipo de solicitud', 'Catálogo de logística, Habilitación', 'Si la persona ve Insumos, Trabajos, o los dos. Códigos: insumos y trabajos.'],
                    ['Categoría de catálogo', 'Catálogo de logística, Habilitación', 'Librería, limpieza, EPP, mantenimiento, repuestos u otro. Códigos: LIB, LIM, EPP, MANT, REP, OTR. No es la categoría de stock de Anita.'],
                    ['Ítem', 'Solapa Catálogo logística del artículo', 'Ese SKU, para un rol o para un usuario.'],
                    ['Centro de costo', 'Misma solapa del artículo', 'Vacío = todos los centros. Con filas = solo esos centros pueden pedirlo.'],
                ],
            ],
            'parrafos2' => [
                'En Catálogo de logística, el campo Ver catálogo como sirve para mirar el portal con los ojos de otra persona, sin cambiar sus permisos. Se carga el código de usuario, Enter o F1, y Ver. El aviso dice cuántos tipos, categorías e ítems ve. Si da cero, falta publicar artículos o falta una habilitación de tipo, categoría o ítem.',
            ],
        ],
        [
            'titulo' => '5. Listado de solicitudes',
            'parrafos' => [
                'Logística, Solicitudes abre el listado. Arriba están los chips, que se combinan entre sí. Debajo está la consulta avanzada (QBE), el diseñador de vista y la exportación.',
            ],
            'tabla' => [
                'caption' => 'Chips del listado',
                'headers' => ['Chip', 'Qué hace'],
                'rows' => [
                    ['Las mías / Todas', 'Las mías muestra lo que pidió quien está logueado. Todas aparece si tiene permiso de listar o de gestionar, y muestra el resto.'],
                    ['Estado', 'Todas, Enviada, Pendiente de aprobación, Aprobada, En preparación, Entregada, Cerrada, Rechazada.'],
                    ['Plazo', 'Todos o Vencidas. Vencidas son las que tienen compromiso anterior a ahora y todavía no están entregadas, cerradas ni rechazadas.'],
                ],
            ],
            'items' => [
                'Columnas de inicio: número (abre la ficha), compromiso, fecha, solicitante, tipo, centro de costo, prioridad, estado y total estimado. Ítems y fecha de alta se muestran desde Diseñar vista.',
                'Si no se eligió otro orden, las vencidas salen primero. El compromiso vencido se marca en la grilla.',
                'Diseñar vista cambia columnas, orden y agrupación, y puede guardar la vista. QBE arma criterios por campo. La caja Texto o número busca en el listado.',
                'Pdf, Excel y Csv salen con los filtros y el orden que están activos, no solo con la página visible. Cortes resume el total estimado del filtro.',
                'Nueva solicitud abre la pantalla de carga.',
            ],
        ],
        [
            'titulo' => '6. Pantalla de carga',
            'parrafos' => [
                'Nueva solicitud está partida en dos. A la izquierda se elige la naturaleza y, si es insumos, la categoría, o si es trabajos, el tipo de trabajo. A la derecha cambia el contenido: grilla de artículos o el formulario de ese trabajo.',
                'Solo aparecen los tipos, categorías y trabajos que la persona tiene habilitados. Los favoritos del catálogo salen primero.',
            ],
        ],
        [
            'titulo' => '7. Circuito de insumos',
            'parrafos' => [
                'El pedido de insumos va del catálogo a la entrega. Logística no reserva mercadería ni graba el movimiento: deja asentado cómo se va a cumplir y, cuando el comprobante ya existe, lo vincula.',
            ],
            'tabla' => [
                'caption' => 'Pasos del pedido de insumos',
                'headers' => ['Paso', 'Quién', 'Pantalla', 'Qué pasa'],
                'rows' => [
                    ['1. Elegir categoría', 'Solicitante', 'Nueva solicitud, panel izquierdo', 'Se elige Insumos y después la categoría (librería, limpieza, EPP, mantenimiento, repuestos u otro).'],
                    ['2. Armar el pedido', 'Solicitante', 'Grilla de la derecha', 'Cada tarjeta muestra SKU, descripción, unidad, precio estimado y disponible. Se carga la cantidad y Agregar. El pedido queda abajo.'],
                    ['3. Centro y prioridad', 'Solicitante', 'Pie del formulario', 'Centro de costo por código, Enter o F1. Prioridad Normal o Urgente. Enviar solicitud.'],
                    ['4. Alta', 'Sistema', 'Ficha', 'Nace en Enviada, o en Pendiente de aprobación si supera el tope del centro o el monto global. Se calcula el compromiso y se avisa por mail al solicitante.'],
                    ['5. Aprobar o rechazar', 'Quien gestiona', 'Ficha, si está pendiente', 'Aprobar la deja Aprobada. Rechazar la deja Rechazada y no se puede seguir.'],
                    ['6. Preparar', 'Quien gestiona', 'Ficha, Enviada o Aprobada', 'Elige salida de depósito, transferencia o compra, y los depósitos si corresponden. Pasar a preparación deja los ítems preparados por la cantidad pedida y el estado En preparación.'],
                    ['7. Comprobante', 'Depósito, transferencias o compras', 'Su pantalla, y después la ficha', 'Se carga el movimiento, la transferencia o la requisición donde corresponde. En la ficha, Vincular con el código o el número.'],
                    ['8. Entregar', 'Quien gestiona', 'Ficha, En preparación', 'Cantidad de esta entrega, nombre de quien recibió y, si hay, foto o PDF. Si queda pendiente, sigue En preparación. Cuando no queda nada, pasa a Entregada.'],
                ],
            ],
        ],
        [
            'titulo' => '8. Disponible y precio',
            'parrafos' => [
                'Disponible es la suma del saldo de los depósitos que el usuario puede ver. No reserva. Si la cantidad pedida es mayor que ese saldo, la tarjeta marca Va a compra. El pedido se puede enviar igual: el aviso es para que logística sepa que no alcanza con el depósito.',
                'El precio estimado no se tipea. Sale del último precio de orden de compra de ese artículo. Si esa línea está en moneda extranjera y tiene cotización, se pasa a pesos. Si no hay orden, o el precio da cero, se usa el precio promedio ponderado del artículo. El total de la solicitud es cantidad por ese precio. Cascos o filtros sin compra reciente pueden estimar cero.',
            ],
        ],
        [
            'titulo' => '9. Tope y aprobación',
            'parrafos' => [
                'La aprobación de un insumo la hace quien tiene el permiso de gestionar logística, con los botones Aprobar y Rechazar de la ficha. No entra al árbol de aprobaciones de compras.',
                'Hay dos umbrales, y cualquiera de los dos deja la solicitud en Pendiente de aprobación. Los trabajos no pasan por este control: nacen Enviadas y el total estimado queda en cero.',
            ],
            'tabla' => [
                'caption' => 'Cuándo un insumo pide aprobación',
                'headers' => ['Control', 'Dónde', 'Regla'],
                'rows' => [
                    ['Tope mensual del centro', 'Catálogo de logística', 'Suma los insumos de ese centro en el mes, sin las rechazadas, y le agrega este pedido. Si pasa el tope, queda pendiente. El aviso dice Supera el tope mensual del centro de costo. Un centro sin tope no usa este control. El presupuesto de partidas no interviene: no es un sobre único del centro.'],
                    ['Monto que pide aprobación', 'Catálogo de logística', 'Si el valor es mayor que cero y el total estimado de esta solicitud lo supera, queda pendiente. El aviso dice Supera el monto que pide aprobación. En cero, este control no corre.'],
                ],
            ],
            'parrafos2' => [
                'Rechazar se puede mientras esté Enviada, Pendiente de aprobación o Aprobada. Una rechazada no suma al tope del mes. Desde En preparación ya no se rechaza: hay que terminar la entrega o dejarla en curso.',
            ],
        ],
        [
            'titulo' => '10. Circuito de trabajos',
            'parrafos' => [
                'En Nueva solicitud se elige Trabajos. A la izquierda aparecen los tipos habilitados. A la derecha se abre el formulario de ese tipo, con el responsable que figura en la configuración. El centro de costo se elige igual que en insumos. La prioridad no se puede bajar del piso del tipo; sí se puede subir.',
                'Al enviar, la solicitud queda Enviada. El mail va al solicitante y, si el tipo de trabajo tiene email, también a ese correo. El nombre del responsable queda guardado en la solicitud aunque después se cambie en la configuración.',
                'El cumplimiento de un trabajo no elige depósito ni vincula comprobante. Quien gestiona pasa a preparación y, cuando el trabajo está hecho, cierra con el nombre de quien recibió y, si quiere, una constancia. El estado final es Cerrada.',
            ],
            'tabla' => [
                'caption' => 'Piso de prioridad',
                'headers' => ['Trabajo', 'Piso', 'Se puede pedir'],
                'rows' => [
                    ['Traslado de slots', 'Alta', 'Alta o Urgente'],
                    ['Retiro de materiales', 'Media', 'Media, Alta o Urgente'],
                    ['Traslado de elementos', 'Media', 'Media, Alta o Urgente'],
                    ['Recambio de butacas', 'Baja', 'Baja, Media, Alta o Urgente'],
                ],
            ],
        ],
        [
            'titulo' => '10.1 Traslado de slots',
            'parrafos' => [
                'Origen y destino son botones de las ubicaciones cargadas en el catálogo, y tienen que ser distintos. Cantidad de slots, fecha tentativa y una descripción. Si se marca accesorios en Sí, hay que escribir cuáles. La foto es opcional (imagen o PDF, hasta 5 MB).',
                'La fecha tentativa es el compromiso: el plazo vence al final de ese día, aunque la prioridad tenga otras horas.',
            ],
        ],
        [
            'titulo' => '10.2 Retiro de materiales',
            'parrafos' => [
                'Se elige la empresa y el número de una orden de compra real de esa empresa. Al salir del número, el sistema completa la dirección con el domicilio del proveedor. Si la orden no tiene domicilio, hay que escribir la dirección. La foto de la orden es obligatoria, imagen o PDF, hasta 5 MB. El prefijo del número es RET.',
            ],
        ],
        [
            'titulo' => '10.3 Traslado de elementos',
            'parrafos' => [
                'El motivo es Resguardo o Destrucción. Se elige la ubicación de origen y se describe qué se traslada. Sin descripción no se envía.',
            ],
        ],
        [
            'titulo' => '10.4 Recambio de butacas',
            'parrafos' => [
                'El tipo es VIP, Especial o Comunes. La cantidad va de 1 a 10. El UID es un texto opcional, por ejemplo BTC-00214: no es un padrón de bienes. Las notas quedan en la solicitud. Si se cargó UID, la ficha muestra el historial de ese código: fecha, solicitud, destino y usuario. El destino del historial sale de las notas cuando el formulario no tiene una ubicación de destino.',
            ],
        ],
        [
            'titulo' => '11. Estados',
            'parrafos' => [
                'El estado se ve en el listado y en la ficha. Cada cambio, salvo vincular el comprobante, avisa por mail al solicitante.',
            ],
            'tabla' => [
                'caption' => 'Estados de la solicitud',
                'headers' => ['Estado', 'Insumos', 'Trabajos'],
                'rows' => [
                    ['Enviada', 'Salió del portal y no superó tope ni monto.', 'Estado inicial de todo trabajo.'],
                    ['Pendiente de aprobación', 'Superó el tope del centro o el monto global.', 'No se usa.'],
                    ['Aprobada', 'Quien gestiona la aprobó. Falta pasar a preparación.', 'No se usa.'],
                    ['En preparación', 'Ya se eligió depósito, transferencia o compra. Puede haber entregas parciales.', 'El trabajo está en curso.'],
                    ['Entregada', 'Todas las líneas quedaron entregadas.', 'No se usa.'],
                    ['Cerrada', 'No se usa.', 'Se registró quién recibió.'],
                    ['Rechazada', 'Se rechazó antes de preparar. No suma al tope.', 'Se rechazó antes de preparar.'],
                ],
            ],
        ],
        [
            'titulo' => '12. Cómo se cumple un insumo',
            'parrafos' => [
                'En la ficha, con la solicitud Enviada o Aprobada, el bloque Cómo se cumple pide el modo. Pasar a preparación guarda ese modo y marca cada ítem como preparado por la cantidad pedida. El comprobante se hace en el módulo que corresponde y después se vincula.',
            ],
            'tabla' => [
                'caption' => 'Modo de cumplimiento',
                'headers' => ['Modo', 'Qué se elige', 'Qué número se vincula después'],
                'rows' => [
                    ['Salida de depósito', 'Un depósito de salida autorizado para quien gestiona.', 'Código del movimiento de stock, o su id si se carga solo el número.'],
                    ['Transferencia', 'Depósito de salida y depósito de destino, distintos y autorizados.', 'Código de la transferencia de mercadería, o su id.'],
                    ['Compra', 'No pide depósito.', 'Número de requisición, solo dígitos.'],
                ],
            ],
            'items' => [
                'Vincular está habilitado en En preparación. Si el número no existe, la ficha avisa y no guarda nada.',
                'Cuando hay vínculo, la ficha muestra el comprobante y abre su pantalla. La transferencia abre el listado de transferencias.',
                'Si todavía no hay vínculo, quedan los accesos para cargar el movimiento, la transferencia o la requisición.',
                'Vincular no cambia el estado y no manda mail.',
            ],
        ],
        [
            'titulo' => '13. Entrega y constancia',
            'parrafos' => [
                'La entrega se registra en En preparación. Hay que escribir quién recibió. La constancia es opcional: imagen o PDF, hasta 5 MB. Queda para descargar junto con los otros adjuntos.',
                'En insumos, cada línea muestra lo pendiente y un campo Entregar ahora, que arranca con todo lo pendiente. Se puede bajar una línea para entregar una parte. No se puede entregar más de lo pendiente, y al menos una línea tiene que ser mayor que cero. Si después de esta entrega todavía falta cantidad, el estado sigue En preparación y se puede volver a entregar. Cuando todas las líneas cierran, el estado pasa a Entregada y se guarda la fecha de entrega.',
                'En trabajos no hay cantidades. El botón Cerrar pide el mismo nombre de quien recibió y deja la solicitud Cerrada.',
            ],
            'tabla' => [
                'caption' => 'Cantidades del ítem',
                'headers' => ['Columna', 'Cuándo se llena'],
                'rows' => [
                    ['Pedida', 'Al enviar la solicitud.'],
                    ['Preparada', 'Al pasar a preparación, por el total pedido.'],
                    ['Entregada', 'Cada vez que se registra una entrega, se suma.'],
                ],
            ],
        ],
        [
            'titulo' => '14. Plazos',
            'parrafos' => [
                'Al enviar, la solicitud guarda un compromiso. Si el trabajo tiene fecha tentativa, el compromiso es el final de ese día. Si no, son las horas de entrega de la prioridad, contadas desde el envío. Esas horas se editan en Catálogo de logística. La hora de preparación queda documentada para el equipo; el listado marca vencida según la hora de entrega.',
                'Una solicitud entregada, cerrada o rechazada no figura como vencida aunque la fecha ya pasó.',
            ],
            'tabla' => [
                'caption' => 'Plazos de instalación',
                'headers' => ['Prioridad', 'Horas para preparar', 'Horas para entregar'],
                'rows' => [
                    ['Urgente', '4', '8'],
                    ['Alta', '8', '24'],
                    ['Media', '24', '48'],
                    ['Normal', '24', '72'],
                    ['Baja', '72', '120'],
                ],
            ],
            'parrafos2' => [
                'Normal es la prioridad de un insumo que no se marcó urgente. En trabajos el piso más bajo que ofrece la pantalla es Baja, no Normal. Las horas de entrega no pueden quedar por debajo de las de preparación.',
            ],
        ],
        [
            'titulo' => '15. Avisos por mail',
            'parrafos' => [
                'El mail sale al correo del solicitante cuando se crea la solicitud y en cada cambio de estado: aprobar, rechazar, pasar a preparación, registrar una entrega parcial o cerrar. El texto dice el número visible y el estado nuevo, y trae el enlace a la ficha.',
                'Si es un trabajo y el tipo tiene email cargado, ese correo también recibe el alta. Vincular el comprobante no avisa. Un correo vacío, inválido o que solo pertenece a un usuario suspendido no se envía. Si el correo falla, la solicitud igual queda grabada.',
            ],
        ],
        [
            'titulo' => '16. Catálogo de logística',
            'parrafos' => [
                'Configuración, Configuración por módulo, Logística, Catálogo de logística. Todo se guarda con Actualizar. Quitar una fila de la tabla y actualizar la borra.',
            ],
            'items' => [
                'Ver catálogo como: código de usuario, Enter o F1, y Ver. Muestra cuántos tipos, categorías e ítems ve esa persona.',
                'Monto que pide aprobación: 0 no usa este umbral.',
                'Plazo: horas de preparación y de entrega por prioridad.',
                'Tope mensual por centro de costo: se busca el centro por código o F1, se carga el monto y se actualiza. Monto 0 no agrega. Quitar la fila saca el tope.',
                'Categorías del catálogo: código, nombre, icono, orden y activa. Se puede agregar una categoría.',
                'Tipos de solicitud: insumos y trabajos. Se puede cambiar nombre, icono, orden y si está activo. El código no se reescribe.',
                'Tipos de trabajo: código, nombre, icono, responsable, email, piso de prioridad, orden y activo. El email es el que recibe el aviso de alta. El piso es Baja, Media o Alta.',
                'Ubicaciones de traslado: los botones de origen y destino de slots y de elementos. No reemplazan a los depósitos de stock.',
                'Habilitación: nivel Tipo o Categoría, código, alcance Rol o Usuario, y Sí o No. El ítem no se habilita acá: se habilita en la solapa del artículo.',
            ],
        ],
        [
            'titulo' => '17. Solapa del artículo',
            'parrafos' => [
                'En el artículo, solapa Catálogo logística. Publicar lo muestra en solicitudes de insumos, siempre que además estén habilitados el tipo, la categoría y el ítem. Hay que elegir la categoría de catálogo para poder publicar. Favorito lo sube arriba de la grilla.',
                'Centros de costo: si no hay ninguno, lo puede pedir cualquier centro. Si hay filas, solo esos. La visibilidad de ítem se carga en la misma solapa, por rol o por usuario, en Sí o en No. Una fila de usuario pisa la del rol.',
                'Guardar el artículo graba la solapa junto con el resto de la ficha.',
            ],
        ],
        [
            'titulo' => '18. Permisos',
            'parrafos' => [
                'El encargado de logística entra a solicitudes, puede crear, ver todas y gestionar el cumplimiento, y entra al catálogo para parametrizar. Un perfil que solo crea ve Las mías y la pantalla de carga, y no ve los botones de aprobar, preparar o entregar.',
            ],
            'tabla' => [
                'caption' => 'Qué habilita cada permiso',
                'headers' => ['Permiso', 'Qué permite'],
                'rows' => [
                    ['Listar solicitudes', 'Ver el listado de todas, no solo las propias, y exportar.'],
                    ['Crear solicitud', 'Nueva solicitud y el catálogo que le corresponde.'],
                    ['Gestionar solicitud', 'Aprobar, rechazar, preparar, vincular y registrar la entrega o el cierre. También ve todas.'],
                    ['Listar, editar y actualizar la configuración', 'Abrir y guardar Catálogo de logística, incluidos plazos y topes.'],
                ],
            ],
        ],
        [
            'titulo' => '19. Qué no hace este módulo',
            'parrafos' => [
                'Logística pide y sigue el pedido. Estas tareas siguen en sus módulos, o quedan para más adelante.',
            ],
            'items' => [
                'No graba ni reserva stock. El disponible es una lectura. El movimiento se carga en Movimientos de stock.',
                'No arma la transferencia ni la requisición. Solo guarda el id cuando ya existen.',
                'No usa el árbol de compras ni el presupuesto de partidas como tope.',
                'No lleva mínimo ni máximo por depósito, ni posiciones, olas ni códigos de ubicación.',
                'No tiene un padrón de bienes. El UID de la butaca es texto y un historial de solicitudes.',
                'Las ubicaciones de traslado no son salas ni depósitos: son los nombres que se eligen en el formulario del trabajo.',
            ],
        ],
    ],
];
