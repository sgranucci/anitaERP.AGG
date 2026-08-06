<?php

/**
 * Contenido del manual de usuario — Anita ERP / Módulo Compras.
 * Retorna metadatos y secciones para generación Word/PDF.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Módulo de Compras',
    'version' => '1.3',
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
            'titulo' => '11. Circuito documental integrado',
            'parrafos' => [
                'El flujo estándar de compras es: Tablas maestras → Proveedor → Requisición (PENDIENTE) → EN COMPRAS (carga presupuestos) → Árbol de aprobación → APROBADA → Orden(es) de compra → Aprobación OC → CUMPLIDA/CERRADA.',
                'Las listas de precio de proveedor alimentan precios de referencia en requisiciones y OC. Los presupuestos documentan la cotización seleccionada.',
            ],
        ],
        [
            'titulo' => '12. Integración con sistema Anita',
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
            'titulo' => '13. Resolución de problemas frecuentes',
            'items' => [
                'No veo el menú de Compras: verifique rol activo y asignación Menú-Rol con el administrador.',
                'No puedo editar una requisición: confirme estado (solo PENDIENTE o EN COMPRAS) y permiso/oficina de compra.',
                'No aparece requisición para generar OC: la requisición debe estar en estado APROBADA.',
                'El listado tarda mucho: puede estar ejecutándose sincronización inicial con Anita; espere y recargue.',
                'Error de CUIT duplicado en proveedor: revise tipo de servicio y reglas de unicidad configuradas.',
            ],
        ],
        [
            'titulo' => '14. Soporte',
            'parrafos' => [
                'Para incidencias técnicas, solicitud de permisos o capacitación adicional, contacte al administrador del sistema o al área de Sistemas de su organización.',
                'Conserve este manual en formato digital (Word/PDF) para consulta de usuarios finales y personal de Compras.',
            ],
        ],
    ],
];
