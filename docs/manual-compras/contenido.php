<?php

/**
 * Contenido del manual de usuario — Anita ERP / Módulo Compras.
 * Retorna metadatos y secciones para generación Word/PDF.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Módulo de Compras',
    'version' => '1.6',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Introducción',
            'parrafos' => [
                'Anita ERP es el sistema integrado de gestión empresarial utilizado para administrar procesos comerciales, contables y operativos de la organización.',
                'El presente manual describe el acceso al sistema y el uso del módulo de Compras, que abarca el circuito documental desde la solicitud interna (requisición) hasta la emisión de la orden de compra al proveedor.',
                'El módulo se integra con el sistema legacy Anita para sincronización inicial de requisiciones, listas de precio de proveedor y órdenes de compra, según configuración del entorno.',
            ],
        ],
        [
            'titulo' => '2. Acceso al sistema',
            'herramientas_clave' => 'login',
            'parrafos' => [
                'Para ingresar al sistema, abra el navegador web (se recomienda Google Chrome o Microsoft Edge en versión actual) y acceda a la URL de la aplicación proporcionada por el administrador.',
                'Pantalla de inicio de sesión: ingrese su nombre de usuario (campo Usuario) y contraseña. Presione el botón Login para autenticarse.',
                'Requisitos: el usuario debe estar registrado en el sistema, tener al menos un rol activo asignado y contar con los permisos correspondientes al módulo que desea utilizar.',
            ],
            'items' => [
                'Si el usuario tiene varios roles, el sistema mostrará un cuadro de diálogo para seleccionar el rol con el que operará en la sesión.',
                'Si no posee rol activo, el sistema cerrará la sesión e informará: "Este usuario no tiene un rol activo".',
                'Tras el login exitoso, se carga el panel principal (página de inicio) con el menú lateral según el rol seleccionado.',
                'Para cerrar sesión, utilice la opción de cierre de sesión del menú de usuario (esquina superior derecha).',
                'Cambio de contraseña: disponible en Seguridad → Cambiar contraseña, según permisos del usuario.',
            ],
            'tabla' => [
                'caption' => 'Rutas de acceso y seguridad',
                'headers' => ['Función', 'Ruta relativa'],
                'rows' => [
                    ['Inicio de sesión', '/seguridad/login'],
                    ['Página principal', '/'],
                    ['Cerrar sesión', '/seguridad/logout'],
                    ['Cambiar contraseña', '/seguridad/cambia_password'],
                ],
            ],
        ],
        [
            'titulo' => '3. Navegación, menú y permisos',
            'parrafos' => [
                'El menú lateral se construye dinámicamente según el rol del usuario. Los ítems visibles y las acciones permitidas dependen de los permisos asignados al rol en Administración → Menú / Menú-Rol / Permiso-Rol.',
                'El rol administrador tiene acceso total a todas las funciones. Los demás roles operan según permisos por código (slug), por ejemplo: listar-proveedor, crear-requisicion, editar-ordencompra.',
                'En el módulo de Compras, el usuario puede tener contexto de sector de legajo de compra, oficina de compra y centro de costo, cargados en sesión al iniciar. Estos datos filtran listados y determinan qué documentos puede editar.',
            ],
            'tabla' => [
                'caption' => 'Permisos principales del módulo Compras',
                'headers' => ['Permiso (slug)', 'Descripción'],
                'rows' => [
                    ['listar-proveedor / crear-proveedor / editar-proveedor', 'Consulta y ABM de proveedores'],
                    ['listar-requisicion / crear-requisicion / editar-requisicion', 'Circuito de requisiciones'],
                    ['seguimiento-aprobacion-requisicion', 'Tablero de seguimiento del circuito de aprobación'],
                    ['listar-kpi-compras', 'Tablero KPIs de proceso y productividad (Enc-compras)'],
                    ['usuario-requisicion-compras', 'Usuario del área Compras (gestión central)'],
                    ['usuario-requisicion-resto', 'Usuario solicitante de otros sectores'],
                    ['listar-listaprecio-proveedor', 'Listas de precio de proveedor'],
                    ['listar-ordencompra / crear-ordencompra / editar-ordencompra', 'Órdenes de compra'],
                ],
            ],
        ],
        [
            'titulo' => '4. Tablas maestras de Compras',
            'herramientas_clave' => 'tablas_maestras',
            'parrafos' => [
                'Antes de operar con proveedores y documentos, el administrador debe mantener las tablas auxiliares del módulo. Todas siguen el mismo patrón: listado, botón Crear, formulario de alta/edición y eliminación lógica según corresponda.',
                'Acceso desde el menú Compras → Tablas (o submenús equivalentes según configuración en base de datos).',
            ],
            'tabla' => [
                'caption' => 'Tablas maestras disponibles',
                'headers' => ['Tabla', 'Ruta base', 'Uso'],
                'rows' => [
                    ['Condición de pago', 'compras/condicionpago', 'Plazos y formas de pago por defecto'],
                    ['Condición de compra', 'compras/condicioncompra', 'Términos comerciales de compra'],
                    ['Condición de entrega', 'compras/condicionentrega', 'Plazos y modalidad de entrega'],
                    ['Tipo de empresa', 'compras/tipoempresa', 'Clasificación del proveedor'],
                    ['Tipo servicio proveedor', 'compras/tiposervicio_proveedor', 'Rubro/servicio del proveedor'],
                    ['Retenciones (Gan., IVA, SUSS, IIBB)', 'compras/retencion*', 'Configuración impositiva'],
                    ['Tipo suspensión proveedor', 'compras/tiposuspensionproveedor', 'Motivos de suspensión'],
                    ['Sector legajo compra', 'compras/sector_legajocompra', 'Sectores para legajo de OC'],
                    ['Columna / Concepto IVA compra', 'compras/columna_ivacompra', 'Imputación IVA compras'],
                    ['Tipo transacción compra', 'compras/tipotransaccion_compra', 'Tipos de operación'],
                    ['Precarga comprobante proveedor', 'compras/precarga_comprobante_proveedor', 'Plantillas de carga'],
                ],
            ],
            'items' => [
                'En cada listado puede exportar o filtrar según las opciones de la grilla.',
                'Los campos obligatorios se marcan con asterisco en el formulario.',
            ],
        ],
        [
            'titulo' => '5. Proveedores',
            'herramientas_grupos' => [
                ['titulo' => 'Pantalla de listado', 'clave' => 'proveedor_listado', 'incluir_listado' => true],
                ['titulo' => 'Ficha de edición / alta', 'clave' => 'proveedor_edicion'],
            ],
            'parrafos' => [
                'La gestión de proveedores centraliza los datos comerciales, impositivos y contables de cada contraparte. Ruta principal: compras/proveedor.',
            ],
            'items' => [
                'Listar: consulte el listado de proveedores activos. Puede exportar y buscar por filtros de la grilla.',
                'Crear: Compras → Proveedores → Crear. Complete las solapas del formulario (datos generales, domicilio, impositivos, condiciones comerciales, formas de pago, cuentas contables, archivos).',
                'Consulta ARCA: desde el alta/edición puede consultar la constancia de inscripción AFIP ingresando el CUIT.',
                'Editar: modifique un proveedor existente. En la ficha de edición dispone de solapas adicionales: cuenta corriente, encuestas respondidas, requisiciones y órdenes de compra del proveedor (según permisos).',
                'Alta provisoria: permite registrar un proveedor temporal para operaciones puntuales.',
                'Eliminar: borrado lógico del proveedor (requiere permiso borrar-proveedor).',
            ],
            'tabla' => [
                'caption' => 'Campos principales del proveedor',
                'headers' => ['Campo', 'Descripción'],
                'rows' => [
                    ['Nombre / Fantasía', 'Razón social y nombre comercial'],
                    ['Código / CUIT (nroinscripcion)', 'Identificación interna e impositiva'],
                    ['Contacto, Teléfono, Email, Email OC', 'Datos de contacto'],
                    ['Domicilio, Localidad, Provincia, País', 'Ubicación fiscal'],
                    ['Condición IVA, Retenciones, IIBB', 'Datos impositivos'],
                    ['Condición pago / entrega / compra', 'Valores por defecto en documentos'],
                    ['Cuentas contables', 'Imputación contable'],
                    ['Estado / Tipo suspensión', 'Habilitación operativa'],
                ],
            ],
        ],
        [
            'titulo' => '6. Encuestas a proveedores',
            'parrafos' => [
                'Las encuestas son plantillas de evaluación que pueden asignarse a proveedores. Se administran en compras/encuesta (permisos listar-encuesta, crear-encuesta, etc.).',
                'Desde la ficha del proveedor se visualizan las encuestas respondidas. También existe un enlace externo para que el proveedor complete una encuesta sin acceso al ERP (ruta genera_proveedor_encuesta).',
            ],
        ],
        [
            'titulo' => '7. Listas de precio de proveedor',
            'herramientas_clave' => 'listaprecio_listado',
            'herramientas_incluir_listado' => true,
            'parrafos' => [
                'Las listas de precio de proveedor almacenan precios pactados por artículo con vigencia. No deben confundirse con las listas de precio de venta del módulo Stock (stock/listaprecio).',
                'Ruta: compras/listaprecio_proveedor. Al primer acceso con tabla vacía, el sistema puede sincronizar listas desde Anita automáticamente.',
            ],
            'items' => [
                'Crear lista: seleccione proveedor, fecha, nombre, moneda, condiciones de pago/entrega/compra y cargue líneas (artículo, precio, fecha de vigencia).',
                'Estados: ACTIVA / INACTIVA. Use Cambiar estado para habilitar o deshabilitar una lista.',
                'Importar Excel: desde la edición de una lista puede importar artículos y precios masivamente.',
                'Historia: consulte el historial de cambios de estado de la lista.',
                'Uso en requisiciones y OC: al cargar líneas, el sistema puede sugerir precio desde la lista vigente a la fecha indicada.',
            ],
        ],
        [
            'titulo' => '8. Requisiciones de compra',
            'herramientas_grupos' => [
                ['titulo' => 'Listado de requisiciones', 'clave' => 'requisicion_listado', 'incluir_listado' => true],
                ['titulo' => 'Formulario de edición', 'clave' => 'requisicion_edicion'],
            ],
            'parrafos' => [
                'La requisición es la solicitud interna de compra. Inicia el circuito documental. Ruta: compras/requisicion.',
                'Al abrir el listado sin datos locales, puede ejecutarse sincronización inicial desde Anita.',
            ],
            'tabla' => [
                'caption' => 'Estados de la requisición',
                'headers' => ['Estado', 'Significado'],
                'rows' => [
                    ['PENDIENTE', 'Recién creada por el solicitante'],
                    ['EN COMPRAS', 'En gestión del área de Compras'],
                    ['EN ARBOL APROBACION', 'En circuito de aprobación'],
                    ['APROBADA', 'Aprobada; habilita generación de OC'],
                    ['GENERO ORDEN COMPRA', 'Se generó al menos una orden de compra'],
                    ['CUMPLIDA', 'Proceso cerrado'],
                    ['SUSPENDIDA', 'Suspendida'],
                ],
            ],
            'items' => [
                'Crear: complete empresa, fechas, centro de costo, tratamiento (Normal/Urgente), comentario y líneas de artículos (cantidad, precio referencia, moneda, partida de gasto o CAPEX, fecha entrega por línea). Si el tratamiento es Urgente, indique el motivo.',
                'Editar: permitido en estados PENDIENTE o EN COMPRAS, según permisos y oficina de compra del usuario.',
                'Presupuestos: en la solapa Presupuestos cargue cotizaciones de uno o más proveedores (fecha, proveedor, condiciones, precios por línea, archivos adjuntos).',
                'Enviar al árbol de aprobación: desde EN COMPRAS, envíe la requisición al circuito configurado en Configuración → Árbol de aprobación.',
                'Aprobación externa: los aprobadores pueden confirmar vía enlace con hash recibido por correo.',
                'PDF: imprima la requisición desde el botón correspondiente en edición o listado.',
                'Wizard múltiples OC: desde una requisición APROBADA puede generar varias órdenes de compra en un asistente.',
                'Visualización pública: compras/requisicion/visualizar/{id}/{hash} para consulta sin login (con hash válido).',
            ],
        ],
        [
            'titulo' => '9. Presupuestos de requisición',
            'captura_id' => 'presupuesto',
            'parrafos' => [
                'Los presupuestos registran las cotizaciones de proveedores vinculadas a una requisición. Se gestionan en la solapa Presupuestos del formulario de requisición.',
                'Por cada presupuesto se indica proveedor, fecha, condiciones de entrega/compra/pago (texto), precio unitario por línea de requisición y archivos de respaldo.',
                'Puede generar PDF del presupuesto e imprimirlo. Los presupuestos sirven de referencia al confeccionar la orden de compra (origen de precio).',
            ],
        ],
        [
            'titulo' => '10. Órdenes de compra',
            'herramientas_grupos' => [
                ['titulo' => 'Listado de órdenes de compra', 'clave' => 'ordencompra_listado', 'incluir_listado' => true],
                ['titulo' => 'Formulario de orden de compra', 'clave' => 'ordencompra_edicion'],
            ],
            'parrafos' => [
                'La orden de compra (OC) es el documento formal enviado al proveedor. Ruta: compras/ordencompra. El listado puede filtrarse por sector de legajo del usuario.',
            ],
            'tabla' => [
                'caption' => 'Estados de la orden de compra',
                'headers' => ['Estado', 'Significado'],
                'rows' => [
                    ['PENDIENTE', 'Ingresada; en circuito de aprobación o edición'],
                    ['APROBADA', 'Aprobada en árbol de aprobación'],
                    ['CUMPLIDA', 'Recepción / proceso completado'],
                    ['SUSPENDIDA', 'Suspendida (puede reactivarse a PENDIENTE)'],
                    ['CERRADA', 'Cerrada administrativamente'],
                ],
            ],
            'items' => [
                'Crear manualmente: compras/ordencompra/crear. Busque requisiciones aprobadas o cargue datos de cabecera y líneas.',
                'Desde requisición: use plantilla desde requisición aprobada o el wizard de múltiples OC.',
                'Cabecera: proveedor, requisición origen, fechas, lugar de entrega, transporte, tratamiento (NO ANTICIPADA / ANTICIPADA), sector legajo, descuentos.',
                'Líneas: artículos con cantidad, precio (desde lista proveedor, presupuesto o manual), moneda, cotización, descuento, vínculo a línea de requisición.',
                'Comprobantes y cuotas: según condición de pago, el sistema sugiere cuotas; puede recalcular totales.',
                'Archivos adjuntos: notas, presupuestos escaneados u otros documentos en la solapa Archivos.',
                'Árbol de aprobación OC: envíe a aprobación; consulte movimientos e historial en edición.',
                'Cambiar estado / Reactivar / Cambiar sector: acciones disponibles según permiso actualizar-ordencompra.',
                'PDF: impresión vertical u horizontal (formato legal apaisado). Puede incluir resumen financiero y documentos adjuntos.',
                'Visualización externa: compras/ordencompra/visualizar/{id}/{hash}.',
            ],
        ],
        [
            'titulo' => '11. Contratos y OC abiertas',
            'herramientas_grupos' => [
                ['titulo' => 'Bloque Contrato / OC abierta en la orden de compra', 'clave' => 'contrato_bloque_oc'],
            ],
            'parrafos' => [
                'Una OC abierta es la que no se agota con una entrega: abonos, honorarios profesionales, servicios recurrentes, mantenimientos, alquileres, licencias. En lugar de crear un documento distinto, la propia orden de compra se marca como contrato y se le cargan la vigencia, el monto contratado, el responsable y la ruta con la que se van a cargar las facturas. El ERP vigila esos datos y avisa antes del vencimiento, con el mismo criterio que los acuerdos marco de SAP o los purchase agreements de Oracle.',
                'Se marca en el formulario de la orden de compra (compras/ordencompra/crear o editar) tildando la casilla "Contrato / OC abierta" en el bloque del mismo nombre. Recién al tildarla se despliegan los campos de vigencia, tope, avisos y ruta de facturación; una OC común no muestra nada de esto.',
                'Cargar la vigencia es lo mínimo indispensable: sin fecha de fin no hay aviso de vencimiento posible. El monto contratado es opcional, pero es lo que habilita el control de consumo. La recepción para facturar y la cuenta contable definen cómo se carga cada factura de ese contrato (capítulo 12).',
            ],
            'items' => [
                'Vigencia desde / hasta: período del contrato. La fecha "hasta" es la que dispara los avisos de vencimiento.',
                'Monto contratado (tope): importe máximo que puede consumir el contrato. Vacío significa sin tope, y en ese caso no hay avisos por consumo.',
                'Moneda del tope: si se elige una moneda, solo se computan las recepciones y facturas de esa misma moneda. Con "Moneda local" se computa el equivalente convertido por cotización.',
                'Se renueva automáticamente + Días de preaviso para no renovar: si el contrato se renueva solo, lo accionable no es el fin de vigencia sino la fecha límite para notificar que NO se renueva (fin de vigencia menos los días de preaviso). Pasada esa fecha el contrato se renueva por otro período.',
                'Días de aviso: umbrales propios de este contrato, separados por coma (por ejemplo 90,45). Vacío usa el default del sistema (60,30,15). Cada umbral avisa una sola vez.',
                'Responsable: usuario dueño del contrato. Recibe siempre los avisos de sus contratos, además de los destinatarios generales configurados.',
                'Recepción para facturar: Obligatoria (las facturas se cargan contra recepción COM) o No requiere recepción (se factura el contrato sin COM: abonos, honorarios, servicios). Este dato fija la ruta del formulario de factura mientras el contrato esté vigente.',
                'Cuenta contable de las facturas: solo aparece si la ruta es sin recepción. Puede tomarse de los artículos de la OC o de una cuenta indicada en el propio contrato. Si elige "Cuenta indicada en este contrato", aparece el campo Cuenta a imputar (código + Enter o lupa). El IVA y las percepciones siguen siempre el concepto de IVA compra; lo que cambia es la cuenta DEBE del neto.',
                'Estado actual: debajo del bloque, la OC muestra el consumo acumulado, el porcentaje del tope, el vencimiento y de dónde salió el consumo (recepción, factura o ambos).',
            ],
            'tabla' => [
                'caption' => 'Cómo se calcula el consumo del monto contratado',
                'headers' => ['Fuente', 'Qué computa', 'Cuándo manda'],
                'rows' => [
                    [
                        'Recepción de proveedor',
                        'Recepciones confirmadas de la OC, valorizadas por cantidad y precio de cada línea. Las devoluciones restan.',
                        'Fuente principal: marca el consumo apenas entra el bien o el servicio, sin esperar la factura.',
                    ],
                    [
                        'Factura de proveedor',
                        'Facturas de la OC que no tienen ninguna recepción vinculada.',
                        'Respaldo para lo que nunca pasa por recepción: abonos, honorarios, servicios.',
                    ],
                    [
                        'Factura (piso)',
                        'Total facturado de la OC, cuando resulta mayor que la suma anterior.',
                        'Red de seguridad si la recepción quedó sin precio o la factura terminó siendo mayor.',
                    ],
                ],
            ],
        ],
        [
            'titulo' => '12. Carga de facturas de un contrato',
            'herramientas_grupos' => [
                ['titulo' => 'Factura de proveedor vinculada a un contrato', 'clave' => 'contrato_factura'],
            ],
            'parrafos' => [
                'Cuando una orden de compra tiene un contrato vigente, el ERP no deja elegir libremente cómo cargar la factura: usa la ruta y la imputación definidas en el contrato. Eso evita facturar un abono como si fuera mercadería (exigiendo una COM que nunca va a existir) o, al revés, saltarse la recepción en un contrato que sí la necesita.',
                'El punto de entrada es Compras → Comprobantes de proveedor → Cargar factura, opción "Con OC". Se ingresa el número de orden (6 dígitos, como en Anita). Si esa OC es un contrato vigente a la fecha del comprobante, el formulario abre ya fijado: modo de carga, solapa de recepciones COM y origen de la cuenta del neto.',
                'Vigente significa que la fecha de la factura cae dentro de "Vigencia desde / hasta" (si alguna de las dos fechas está vacía, ese extremo no restringe). Si el contrato todavía no empezó o ya venció, la factura sigue el flujo estándar de la empresa y el formulario avisa que el contrato no está vigente.',
            ],
            'items' => [
                'Contrato con recepción obligatoria: el modo queda en "Factura contra recepción (COM)". Hay que confirmar una recepción con provisión antes de grabar la factura. Sin COM, el sistema bloquea la carga.',
                'Contrato sin recepción: el modo queda en "Gasto sin recepción". No se pide COM aunque la empresa tenga flujo estricto OC/COM/factura, y no se trata como factura anticipada.',
                'Imputación desde artículos de la OC: el neto de la factura se contabiliza en las cuentas de compras/gastos de los renglones de la orden, prorrateadas por el importe de cada ítem. La OC tiene que tener artículos con cuenta cargada.',
                'Imputación con cuenta del contrato: en el bloque Contrato hay que cargar la cuenta a imputar. Esa cuenta se usa como DEBE del neto en cada factura (queda precargada en la solapa de conceptos y se puede cambiar en el renglón si hace falta). IVA y percepciones siguen la cuenta del concepto de IVA compra.',
                'En la cabecera de la factura, junto al número de OC, se muestran badges: Contrato vigente, Recepción obligatoria o Sin recepción, y el origen de la cuenta (artículos OC o cuenta del contrato, con código y nombre).',
                'La misma regla aplica si la factura entra por precarga (agente, API o PDF con IA) y la OC resuelta es un contrato vigente: el modo y la imputación se toman del contrato, no de la COM que pudiera haber en el legajo.',
            ],
            'tabla' => [
                'caption' => 'Ruta de la factura según el contrato vigente',
                'headers' => ['Qué dice el contrato', 'Modo de la factura', 'De dónde sale la cuenta del neto'],
                'rows' => [
                    [
                        'Recepción obligatoria',
                        'Factura contra recepción (COM). Sin COM no se puede grabar.',
                        'De las recepciones COM (provisión y cuentas de los artículos recibidos).',
                    ],
                    [
                        'Sin recepción + cuenta de los artículos de la OC',
                        'Gasto sin recepción. No se pide COM.',
                        'Cuentas de compras/gastos de los artículos de la OC, prorrateadas por importe.',
                    ],
                    [
                        'Sin recepción + cuenta indicada en el contrato',
                        'Gasto sin recepción. No se pide COM.',
                        'La cuenta cargada en el contrato (Cuenta a imputar). Queda precargada en cada factura; se puede cambiar en el renglón de neto.',
                    ],
                    [
                        'Contrato fuera de vigencia, o OC que no es contrato',
                        'Flujo estándar de la empresa (COM si hay recepciones, factura anticipada si la OC es anticipada, o gasto sin recepción).',
                        'Según ese flujo: COM, anticipo o cuenta del concepto de IVA compra.',
                    ],
                ],
            ],
        ],
        [
            'titulo' => '13. Avisos y seguimiento de contratos',
            'captura_id' => 'contrato_reporte',
            'herramientas_grupos' => [
                ['titulo' => 'Reporte de contratos y OC abiertas por vencer', 'clave' => 'contrato_reporte'],
            ],
            'parrafos' => [
                'Un proceso automático diario revisa todos los contratos y envía un mail cuando alguno cruza un umbral. Solo se vigilan las OC marcadas como contrato que están en estado APROBADA o CUMPLIDA: las suspendidas y cerradas quedan afuera porque ya no admiten movimiento.',
                'Cada umbral avisa una sola vez. Si un contrato ya figuró en el aviso de 60 días, no vuelve a aparecer hasta el umbral de 30. Esto evita el mail diario repetido que termina en que nadie lo lee. La excepción es el escalamiento de contratos vencidos, que se reitera cada 7 días hasta que el contrato se resuelva.',
                'Los destinatarios y el texto de los mails se configuran en Configuración → Avisos por módulo, en los eventos "Contratos / OC abiertas por vencer" (preventivo) y "Contratos / OC abiertas vencidas (escalamiento)". Son dos eventos separados para que el escalamiento pueda ir a jefatura o gerencia sin sumar a esas personas al aviso preventivo de todos los días.',
                'Para trabajar la lista completa, sin depender del mail, está el reporte Compras → Reportes → Contratos y OC abiertas (compras/contrato-vencimiento-reporte). Permite filtrar por empresa, tipo de alerta, horizonte de días, proveedor y responsable, y exportar a PDF, Excel o CSV.',
            ],
            'items' => [
                'Filtro Tipo de alerta: Todos, Por vencer (dentro del horizonte), Con preaviso de no renovación pendiente, Consumo del tope en zona de alerta, Vencidos, o Sin fecha de vigencia cargada. Esta última opción sirve para detectar contratos mal cargados, que son los que nunca van a avisar.',
                'Días de horizonte: cuántos días hacia adelante mirar (90 por defecto).',
                'Solo sin responsable: contratos sin dueño asignado. Conviene revisarlo periódicamente porque son los que se pierden.',
                'En la grilla, las filas de contratos vencidos se marcan en rojo y las que vencen dentro de 30 días en amarillo. La columna Situación explica el motivo concreto de la alerta.',
                'Las columnas Recibido, Facturado y Consumido permiten auditar el consumo: si Recibido y Facturado difieren mucho, hay recepciones sin facturar o facturas sin recepción.',
                'El número de OC es un enlace: abre la orden de compra en una pestaña nueva para revisar o renovar el contrato.',
            ],
            'tabla' => [
                'caption' => 'Tipos de aviso',
                'headers' => ['Aviso', 'Cuándo se dispara', 'Qué hay que hacer'],
                'rows' => [
                    [
                        'Por vencer',
                        'Cuando faltan tantos días para el fin de vigencia como indica el umbral (60, 30 y 15 días por defecto).',
                        'Renovar, renegociar o dejar vencer con decisión tomada.',
                    ],
                    [
                        'Preaviso de no renovación',
                        'Contratos con renovación automática, cuando se acerca la fecha límite para notificar la no renovación.',
                        'Notificar al proveedor antes de esa fecha si no se quiere continuar. Pasada la fecha, el contrato se renueva solo.',
                    ],
                    [
                        'Consumo del tope',
                        'Cuando el consumo alcanza el porcentaje configurado del monto contratado (80% y 100% por defecto).',
                        'Ampliar el monto contratado o emitir una OC nueva antes de seguir consumiendo.',
                    ],
                    [
                        'Vencido (escalamiento)',
                        'Vigencia terminada y el contrato sigue abierto. Se reitera cada 7 días.',
                        'Renovar, cerrar la OC o darla de baja. Es el aviso que no debería existir.',
                    ],
                ],
            ],
        ],
        [
            'titulo' => '14. Circuito documental integrado',
            'parrafos' => [
                'El flujo estándar de compras es: Tablas maestras → Proveedor → Requisición (PENDIENTE) → EN COMPRAS (carga presupuestos) → Árbol de aprobación → APROBADA → Orden(es) de compra → Aprobación OC → CUMPLIDA/CERRADA.',
                'Las listas de precio de proveedor alimentan precios de referencia en requisiciones y OC. Los presupuestos documentan la cotización seleccionada.',
                'Las OC marcadas como contrato no siguen ese cierre: quedan vigentes durante todo el período contratado y se van consumiendo con recepciones y facturas sucesivas, hasta que se renuevan o se cierran (ver capítulos 11, 12 y 13). La ruta de cada factura (con o sin COM, y de dónde sale la cuenta del neto) la fija el contrato, no el flujo general de la empresa.',
            ],
        ],
        [
            'titulo' => '15. Integración con sistema Anita',
            'parrafos' => [
                'En entornos migrados, el ERP sincroniza datos históricos desde Anita (Informix) en el primer acceso a ciertos listados vacíos:',
            ],
            'items' => [
                'Requisiciones: sincronización al abrir listado sin registros locales.',
                'Listas de precio proveedor: sincronización al abrir listado vacío.',
                'Órdenes de compra: sincronización configurable (variable ORDENCOMPRA_SYNC_ANITA_INDEX en entorno).',
                'La sincronización no duplica registros existentes (control por número de documento).',
            ],
        ],
        [
            'titulo' => '16. Resolución de problemas frecuentes',
            'items' => [
                'No veo el menú de Compras: verifique rol activo y asignación Menú-Rol con el administrador.',
                'No puedo editar una requisición: confirme estado (solo PENDIENTE o EN COMPRAS) y permiso/oficina de compra.',
                'No aparece requisición para generar OC: la requisición debe estar en estado APROBADA.',
                'El listado tarda mucho: puede estar ejecutándose sincronización inicial con Anita; espere y recargue.',
                'Error de CUIT duplicado en proveedor: revise tipo de servicio y reglas de unicidad configuradas.',
                'Un contrato no avisa nunca: verifique que la OC tenga tildado "Contrato / OC abierta", que tenga fecha de vigencia hasta (o monto contratado) y que esté en estado APROBADA o CUMPLIDA. El filtro "Sin fecha de vigencia cargada" del reporte lista justamente estos casos.',
                'No recibí el mail del contrato: cada umbral avisa una sola vez, así que si el aviso de 60 días ya salió no se repite hasta los 30. Confirme además que su usuario esté como responsable del contrato o como destinatario del evento en Configuración → Avisos por módulo.',
                'El consumo del contrato no coincide con lo facturado: el consumo toma primero las recepciones confirmadas y suma las facturas sin recepción vinculada. Compare las columnas Recibido, Facturado y Consumido del reporte: la diferencia suele ser mercadería recibida y todavía no facturada.',
                'El consumo quedó en cero con recepciones cargadas: revise que las líneas de la recepción tengan precio. Si la OC no tenía precio, la recepción se valoriza en cero y el consumo recién aparece cuando llega la factura.',
                'No puedo cargar la factura de un abono / honorario: el contrato vigente probablemente tiene "Recepción para facturar" en Obligatoria. Edite la OC, márquela en "No requiere recepción" y elija de dónde sale la cuenta (artículos de la OC o cuenta del contrato).',
                'La factura pide COM aunque el contrato es un servicio: misma causa: recepción obligatoria en el contrato, o el contrato no está vigente a la fecha del comprobante (en ese caso rige el flujo de la empresa).',
                'Al grabar pide la cuenta del contrato: el contrato está en sin recepción con cuenta indicada en el contrato, pero esa cuenta no está cargada. Edite la OC, elija "Cuenta indicada en este contrato" y complete Cuenta a imputar.',
                'Al grabar pide la cuenta DEBE del neto: complete la columna Cuenta DEBE en los renglones de neto, o cargue la cuenta en el contrato para que se precargue sola.',
                'Al contabilizar falta la cuenta de un artículo de la OC: el contrato imputa el neto con las cuentas de los ítems. Abra el artículo y cargue la cuenta de compras o gastos de la empresa, o cambie la imputación del contrato a cuenta indicada en el contrato.',
            ],
        ],
        [
            'titulo' => '17. Soporte',
            'parrafos' => [
                'Para incidencias técnicas, solicitud de permisos o capacitación adicional, contacte al administrador del sistema o al área de Sistemas de su organización.',
                'Conserve este manual en formato digital (Word/PDF) para consulta de usuarios finales y personal de Compras.',
            ],
        ],
    ],
];
