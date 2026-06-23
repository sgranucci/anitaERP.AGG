<?php

/**
 * Manual de usuario — Recepción de proveedores + Movimientos de stock + Transferencias.
 * Audiencia: operador de depósito / compras sin experiencia técnica.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Recepción de proveedores y movimientos de stock',
    'version' => '1.0',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Introducción',
            'parrafos' => [
                'Este manual explica paso a paso cómo registrar mercadería que ingresa desde proveedores (Recepción de proveedores) y cómo mover stock dentro de la empresa (Movimientos de stock y Transferencias). Está pensado para personas que usan Anita ERP por primera vez: no hace falta saber programación ni contabilidad avanzada.',
                'Recepción de proveedores documenta lo que llegó físicamente contra una Orden de compra (OC). Al confirmar, el sistema suma stock en el depósito elegido y puede generar el asiento contable de provisión.',
                'Movimientos de stock registran entradas, salidas y transferencias manuales. Transferencia de mercadería es una pantalla optimizada para mover artículos entre depósitos o hacia/desde un bien de uso (equipo, PC, etc.), con o sin aprobación del receptor.',
                'Menú principal: Stock → Recepción proveedores; Stock → Movimientos de Stock; Stock → Transferencia. Las acciones visibles dependen de los permisos de su usuario.',
            ],
            'items' => [
                'Un borrador de recepción no modifica stock hasta que usted presiona Confirmar.',
                'Una transferencia siempre descuenta stock del origen; el ingreso en destino puede ser inmediato o quedar pendiente de aprobación.',
                'Desde el listado de Movimientos de Stock ve movimientos sueltos y transferencias en una sola grilla.',
            ],
        ],
        [
            'titulo' => '2. Conceptos básicos',
            'captura_id' => 'flujo_operativo',
            'parrafos' => [
                'Orden de compra (OC): pedido formal al proveedor. La recepción siempre parte de una OC pendiente de ingreso.',
                'Recepción COM: comprobante interno del ERP al confirmar la recepción. Puede imprimirlo en PDF.',
                'Depósito: lugar físico donde se guarda la mercadería (galpón, farmacia, bodega). Se elige con código + lupa, no con un desplegable simple.',
                'Tipo de transacción stock: clasifica cada movimiento como Entrada (E), Salida (S) o Transferencia (T).',
                'Transferencia: documento que mueve cantidades de un origen (depósito o bien de uso) a un destino. Genera movimiento de salida y, cuando corresponde, movimiento de entrada.',
            ],
            'tabla' => [
                'caption' => 'Glosario rápido',
                'headers' => ['Término', 'Significado para el operador'],
                'rows' => [
                    ['OC', 'Orden de compra en Compras; trae proveedor, ítems y precios acordados.'],
                    ['BORRADOR', 'Recepción guardada pero sin impacto en stock.'],
                    ['CONFIRMADA', 'Recepción que ya ingresó mercadería al depósito.'],
                    ['COM PDF', 'Impresión del comprobante de recepción.'],
                    ['NPU', 'Número de parte única para ítems serializados.'],
                    ['Pendiente de recepción', 'Transferencia enviada; falta que el destino apruebe.'],
                    ['Bien de uso', 'Equipo o activo con stock asignado (ej. notebook con insumos).'],
                ],
            ],
        ],
        [
            'titulo' => '3. Listado de recepciones',
            'herramientas_grupos' => [
                ['titulo' => 'Barra superior y filtros', 'clave' => 'recepcion_listado', 'incluir_listado' => true],
            ],
            'captura_id' => 'recepcion_listado',
            'parrafos' => [
                'Pantalla: Stock → Recepción proveedores. Muestra número de recepción, fecha, tipo, OC, proveedor, empresa, estado y badges de diferencias.',
                'Las filas con fondo amarillo indican diferencias respecto a la OC (precio, cantidad, artículo extra, faltante o laboratorio).',
                'Use la búsqueda rápida en la barra superior: tolera errores de tipeo si escribe al menos 6 caracteres. Para criterios precisos abra el panel Filtros y pulse Aplicar filtros.',
                'Exporte a PDF, Excel o CSV con los botones sobre la tabla; respeta los filtros activos, no solo la página visible.',
            ],
            'tabla' => [
                'caption' => 'Badges de diferencias en el listado',
                'headers' => ['Badge', 'Significado'],
                'rows' => [
                    ['P', 'Precio de recepción distinto al de la OC.'],
                    ['C', 'Cantidad recibida distinta a la pedida.'],
                    ['A', 'Artículo extra o sustituto no estaba en la OC.'],
                    ['F', 'Faltante: ítem de OC no recibido.'],
                    ['LAB', 'Artículo de laboratorio (prefijo configurado).'],
                ],
            ],
            'items' => [
                'PDF rojo: imprime comprobante COM de la recepción.',
                'Lápiz: editar borrador o consultar recepción confirmada/anulada.',
                'Flechas amarillas: cambiar OC (solo borrador).',
                'Check verde: confirmar recepción (solo borrador).',
                'Papelera roja: eliminar borrador (acción irreversible).',
            ],
        ],
        [
            'titulo' => '4. Permisos — recepción de proveedores',
            'parrafos' => [
                'Si no ve un botón descrito en este manual, su rol probablemente no tiene el permiso. Solicítelo al administrador de usuarios.',
                'Sin el permiso listar-todas-recepciones-proveedor solo verá recepciones de su centro de costo y empresas asignadas.',
            ],
            'tabla' => [
                'caption' => 'Permisos principales',
                'headers' => ['Permiso', 'Para qué sirve'],
                'rows' => [
                    ['listar-recepcion-proveedor', 'Ver listado, exportar e imprimir COM PDF.'],
                    ['crear-recepcion-proveedor', 'Botón Nuevo registro y pantalla de alta.'],
                    ['actualizar-recepcion-proveedor', 'Guardar borrador, eliminar borrador, cambiar OC.'],
                    ['confirmar-recepcion-proveedor', 'Confirmar recepción (impacta stock y contabilidad).'],
                    ['devolver-recepcion-proveedor', 'Registrar devolución a proveedor.'],
                    ['anular-recepcion-proveedor', 'Anular recepción confirmada (revierte stock).'],
                    ['ocr-recepcion-proveedor', 'Cargar datos desde foto o PDF del remito (si está habilitado).'],
                ],
            ],
        ],
        [
            'titulo' => '5. Alta y edición de recepción',
            'herramientas_grupos' => [
                ['titulo' => 'Cabecera y solapas', 'clave' => 'recepcion_form_cabecera'],
                ['titulo' => 'Grilla de ítems', 'clave' => 'recepcion_form_items'],
            ],
            'captura_id' => 'recepcion_form',
            'parrafos' => [
                'Flujo recomendado: Nuevo registro → indicar Nº OC (Enter, Tab o lupa) → completar fecha, número de factura/remito, depósito de entrada → cargar cantidades recibidas y rechazadas → Guardar → revisar solapa Asiento contable → Confirmar recepción.',
                'Nº OC: escriba el número y presione Enter, use la lupa para buscar OC pendientes o el enlace Ver OC para abrir la orden en otra pestaña.',
                'Proveedor y empresa vienen de la OC (solo lectura). Fecha no puede ser posterior a hoy.',
                'Nº factura remito: acepta formatos como 265, 1-265, FAC 1-265, REM 999, ND, NC.',
                'Depósito general entrada: opcional en cabecera; puede definir depósito distinto por línea. Use código + lupa (F1) para elegir depósito autorizado.',
                'Solapas: Recepción (formulario), Historia estados, Asiento contable (vista previa o grabado), Archivos asociados (adjuntos y OCR).',
            ],
            'items' => [
                'Agregar artículo extra: para mercadería no pedida en la OC (línea tipo EXTRA).',
                'Cant. recibida / Rechaz.: al menos una debe ser mayor a cero por línea.',
                'Motivo rechazo: obligatorio si cantidad rechazada > 0.',
                'Motivo diferencia de precio: obligatorio si el precio recibido ≠ precio OC.',
                'Ver detalle de precios: modal con precio OC, cantidad, precio recepción y total.',
                'Badge NPU: ítem con número de parte única; badge Fórmula: depósito de fórmulas.',
                'OCR: suba PDF o imagen del remito para precargar cantidades (requiere permiso y configuración del servidor).',
            ],
        ],
        [
            'titulo' => '6. Confirmación y estados',
            'captura_id' => 'circuito_recepcion',
            'parrafos' => [
                'Al confirmar, el sistema pregunta: «¿Confirmar recepción? Generará movimiento de stock y asiento contable.» Acepte solo si revisó cantidades, depósitos y diferencias.',
                'Estados de cabecera: BORRADOR (editable), CONFIRMADA (stock ingresado), ANULADA (revertida). Tipos: RECEPCION (ingreso normal) y DEVOLUCION (salida contra recepción previa).',
            ],
            'tabla' => [
                'caption' => 'Estados y acciones',
                'headers' => ['Estado', 'Qué puede hacer'],
                'rows' => [
                    ['BORRADOR', 'Guardar, Confirmar, Eliminar borrador, Cambiar OC, Emitir PDF borrador.'],
                    ['CONFIRMADA', 'Consultar, PDF COM, Devolución a proveedor, Anular recepción.'],
                    ['ANULADA', 'Solo consulta e impresión histórica.'],
                ],
            ],
            'items' => [
                'Confirmar dispara: movimiento de stock (entrada), asiento contable tipo COM (si contabilidad activa), sincronización legacy Anita y registro de NPU si aplica.',
                'El sistema puede enviar avisos por email ante diferencias de precio, cantidad, artículos extra, faltantes, laboratorio, rechazos o encuesta de proveedor.',
                'Anular recepción revierte stock, asiento y registros Anita; no es posible si hay factura de proveedor vinculada o devoluciones confirmadas.',
            ],
        ],
        [
            'titulo' => '7. Devolución y anulación',
            'herramientas_grupos' => [
                ['titulo' => 'Pantalla devolución', 'clave' => 'recepcion_devolucion'],
            ],
            'captura_id' => 'recepcion_devolucion',
            'parrafos' => [
                'Devolución a proveedor: disponible en recepciones CONFIRMADAS de tipo RECEPCION. Indique cantidades a devolver; no pueden superar lo recepcionado.',
                'Al registrar devolución el sistema la confirma automáticamente: genera salida de stock equivalente.',
                'Anular recepción: revierte stock, asiento contable y registros Anita. Confirme el mensaje de advertencia antes de continuar.',
            ],
        ],
        [
            'titulo' => '8. Configuración contable (administradores)',
            'parrafos' => [
                'Menú Configuración → Recepción proveedores. Solo usuarios con permiso editar/actualizar configuración.',
                'Por empresa: checkbox «Generar asiento contable al confirmar recepción» y cuentas de provisión, factura anticipada, anticipo bienes de uso y proveedores intangible.',
                'Tolerancias por centro de costo: porcentaje de cantidad, porcentaje de precio y tolerancia absoluta de precio. Definen cuándo una diferencia exige tratamiento especial.',
            ],
        ],
        [
            'titulo' => '9. Listado de movimientos de stock',
            'herramientas_grupos' => [
                ['titulo' => 'Barra superior y acciones', 'clave' => 'movimientos_listado', 'incluir_listado' => true],
            ],
            'captura_id' => 'movimientos_listado',
            'parrafos' => [
                'Menú: Stock → Movimientos de Stock. Lista movimientos sueltos y transferencias en una sola tabla.',
                'Columnas: ID, Fecha, Tipo de transacción, Número, Lote, Origen, Destino, Empresa, Cantidad, Ítems, Estado.',
                'Si ve el aviso «Mostrando movimientos de su sector», su usuario está limitado al centro de costo asignado. Con permiso listar-todos los movimientos de stock ve todo el sector/empresa.',
                'Búsqueda rápida busca movimientos y transferencias a la vez. Export PDF/Excel/CSV respeta filtros activos.',
            ],
            'items' => [
                'Movimiento suelto: lápiz editar, ojo consultar, PDF verde comprobante, cruz roja eliminar.',
                'Transferencia: PDF verde comprobante transferencia, ojo consultar, flecha amarilla editar egreso, flecha verde editar ingreso (si ya existe).',
            ],
        ],
        [
            'titulo' => '10. Entradas, salidas y transferencias manuales',
            'herramientas_grupos' => [
                ['titulo' => 'Formulario movimiento', 'clave' => 'movimientos_form'],
            ],
            'captura_id' => 'movimientos_form',
            'parrafos' => [
                'Nuevo movimiento: Stock → Movimientos de Stock → Nuevo. Elija tipo Entrada o Salida (no Transferencia si prefiere la pantalla ágil de transferencias).',
                'Campos obligatorios: tipo de transacción, fecha, lote de stock (por defecto «LOTE DE ALTA»), empresa, depósito.',
                'Depósito: escriba código y Enter, o lupa (F1) para modal Consulta depósitos → Elegir o Consultar ficha en otra pestaña.',
                'Grilla de ítems: Agregar artículo (modal artículo), cantidad, saldo origen en transferencias, icono kardex para historial del artículo en el depósito.',
                'Si el tipo lleva contabilidad verá la solapa Asiento contable con vista previa antes de guardar.',
                'Comprobante PDF: disponible tras guardar, desde edición o listado (icono PDF verde).',
            ],
            'items' => [
                'Entrada: suma stock en el depósito indicado.',
                'Salida: resta stock; el sistema valida saldo disponible.',
                'Transferencia desde esta pantalla: al elegir tipo Transferencia aparecen Depósito origen y Depósito destino (o bienes de uso según tipo). Al guardar crea el documento transferencia.',
            ],
        ],
        [
            'titulo' => '11. Tipos y variantes de transferencia',
            'captura_id' => 'circuito_transferencia',
            'parrafos' => [
                'Las transferencias mueven mercadería entre depósitos o hacia/desde un bien de uso. Siempre generan salida en origen; la entrada en destino puede ser inmediata o pendiente de aprobación.',
                'Modo de aprobación (configuración del servidor): Inmediata (sin bandeja), Por tipo de transacción (solo tipos marcados requieren aprobación) o Siempre (toda transferencia espera al receptor).',
            ],
            'tabla' => [
                'caption' => 'Variantes habituales',
                'headers' => ['Variante', 'Origen', 'Destino', 'Aprobación típica'],
                'rows' => [
                    ['Entre depósitos', 'Depósito A', 'Depósito B', 'Según configuración del tipo.'],
                    ['Entre depósitos (con aprobación)', 'Depósito A', 'Depósito B', 'Siempre pendiente hasta Aprobar.'],
                    ['A bien de uso', 'Depósito', 'Equipo / bien de uso', 'Puede requerir aprobación del responsable.'],
                    ['Desde bien de uso', 'Equipo / bien de uso', 'Depósito', 'Suele requerir aprobación del depósito destino.'],
                ],
            ],
            'items' => [
                'Rechazar transferencia: revierte stock en origen; puede indicar motivo.',
                'Aprobar transferencia: crea ingreso en destino; estado pasa a Confirmada.',
                'Anulada: transferencia cancelada sin efecto operativo.',
            ],
        ],
        [
            'titulo' => '12. Transferencia de mercadería (pantalla ágil)',
            'herramientas_grupos' => [
                ['titulo' => 'Cabecera y acciones', 'clave' => 'transferencia_pantalla'],
            ],
            'captura_id' => 'transferencia_pantalla',
            'parrafos' => [
                'Menú: Stock → Transferencia. Diseño pensado para depósito o tablet: cabecera fija, lista de artículos, barra inferior Transferir.',
                'Complete empresa, depósito salida, depósito entrada (o bienes de uso según tipo), tipo de transacción transferencia y centro de costo destino si el tipo lleva contabilidad.',
                'Cargar stock: lista todos los artículos con saldo en el depósito de salida. Agregar artículo (modal): suma ítems puntuales sin cargar todo el inventario.',
                'Por cada fila: SKU, descripción, saldo disponible, campo cantidad. Artículos sin equivalencia ERP se marcan en rojo y no se pueden transferir.',
                'Transferir (N): envía la transferencia; N indica cuántos ítems tienen cantidad > 0.',
                'Pendientes: botón con contador de transferencias que esperan su aprobación (permiso listar transferencias pendientes).',
            ],
        ],
        [
            'titulo' => '13. Aprobación y rechazo de transferencias',
            'herramientas_grupos' => [
                ['titulo' => 'Bandeja pendientes', 'clave' => 'transferencia_pendientes'],
            ],
            'captura_id' => 'transferencia_pendientes',
            'parrafos' => [
                'Menú: Stock → Transferencia → Pendientes, o enlace desde el botón Pendientes de la pantalla de transferencia.',
                'Columnas: código, fecha, origen, destino, ítems, remitente, destinatario.',
                'Aprobar: confirma recepción en destino y completa la transferencia.',
                'Rechazar: revierte el stock en origen; puede registrar motivo.',
                'Aprobación por correo: si el tipo requiere aprobación, el destinatario recibe email con enlaces Ver, Aprobar y Rechazar (válidos varios días, sin necesidad de estar logueado).',
            ],
        ],
        [
            'titulo' => '14. Reporte transferencias pendientes',
            'parrafos' => [
                'Menú: Stock → Reportes → Transferencias pendientes.',
                'Complete filtros (empresa, fechas, bien destino, solo con aprobación). Pulse Consultar.',
                'Muestra totales de transferencias e ítems. Exporte PDF, Excel o CSV. Enlace a pantalla de aprobación para quien tenga permiso.',
            ],
        ],
        [
            'titulo' => '15. Comprobantes PDF y exportaciones',
            'herramientas_grupos' => [
                ['titulo' => 'Impresión y export', 'clave' => 'exportaciones'],
            ],
            'parrafos' => [
                'COM recepción proveedor: icono PDF rojo en listado o botón Emitir recepción (PDF) en edición. Documento con logos, cabecera e ítems recibidos.',
                'Comprobante movimiento stock: icono PDF verde en listado o edición de movimiento suelto.',
                'Comprobante transferencia: icono PDF verde en fila de transferencia del listado de movimientos o desde consulta de transferencia.',
                'Export listado recepciones: PDF legal apaisado, Excel o CSV desde index recepción.',
                'Export listado movimientos: PDF, Excel o CSV desde index movimientos (incluye transferencias unificadas).',
            ],
        ],
        [
            'titulo' => '16. Procesos relacionados (mismo stock)',
            'parrafos' => [
                'Recuento de inventario: ajustes por conteo físico; genera movimientos RCAJP/RCAJN visibles en kardex.',
                'Préstamo entre depósitos: préstamo con aprobación; aparece en historial de movimientos al confirmarse.',
                'Recepción proveedor: ingreso desde compras; al confirmar aparece como movimiento de entrada en el depósito.',
                'Comprobante proveedor (Compras): puede vincular recepciones COM confirmadas de la misma OC para facturar contra provisión.',
            ],
            'items' => [
                'Use el icono kardex en líneas de movimiento para ver entradas/salidas del artículo en un depósito.',
                'Enlaces azules en reportes abren el ABM en nueva pestaña si tiene permiso (origen=modal_consulta).',
            ],
        ],
        [
            'titulo' => '17. Errores frecuentes y soluciones',
            'tabla' => [
                'caption' => 'Mensajes habituales',
                'headers' => ['Situación', 'Qué hacer'],
                'rows' => [
                    ['No veo el depósito en el modal', 'Verifique que esté autorizado en Admin → Usuario → Depósitos y que la empresa del formulario coincida.'],
                    ['No puedo confirmar recepción', 'Revise motivos de rechazo, diferencias de precio sin comentario, fecha futura o período contable cerrado.'],
                    ['Saldo insuficiente al transferir', 'Reduzca cantidad o verifique movimientos previos en kardex.'],
                    ['Transferencia queda pendiente', 'Es normal si el tipo requiere aprobación; el receptor debe entrar a Pendientes o usar el enlace del correo.'],
                    ['Artículo en rojo en transferencia', 'Sin equivalencia ERP; regularice el artículo en Stock → Artículos.'],
                    ['No aparece botón Nuevo', 'Falta permiso crear-recepcion-proveedor o crear-movimientos-de-stock.'],
                ],
            ],
        ],
        [
            'titulo' => '18. Flujos paso a paso (resumen)',
            'items' => [
                'Recepción normal: Nuevo → OC → cantidades → Guardar → Confirmar → PDF COM.',
                'Devolución: Abrir recepción CONFIRMADA → Devolución a proveedor → cantidades → Registrar devolución.',
                'Entrada manual: Movimientos → Nuevo → Entrada → depósito + ítems → Guardar → PDF.',
                'Salida manual: Movimientos → Nuevo → Salida → depósito + ítems → Guardar.',
                'Transferencia inmediata: Transferencia → depósitos → Cargar stock → cantidades → Transferir.',
                'Transferencia con aprobación: Igual anterior → receptor Pendientes → Aprobar o Rechazar.',
                'Transferencia a equipo: Tipo «a bien de uso» → depósito salida + bien destino → Transferir.',
            ],
        ],
    ],
];
