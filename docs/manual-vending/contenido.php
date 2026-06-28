<?php

/**
 * Contenido del manual de usuario — Anita ERP / Módulo Vending.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Vending (Gastronomía y Caja)',
    'version' => '1.0',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Introducción',
            'parrafos' => [
                'El módulo de Vending de Anita ERP administra máquinas expendedoras y el ciclo de rendiciones: registro operativo en Ventas (Gastronomía), replicación a Anita y presentación en tesorería (Caja).',
                'Este manual describe el flujo completo, las pantallas de cada área, los permisos necesarios y las restricciones que impiden modificar una rendición ya presentada en caja.',
                'El circuito es análogo al de estacionamiento: la rendición nace en Ventas con estado Anita pendiente de contabilidad (rendg_estado = espacio); Caja completa la presentación actualizando el total Z sin cerrar contabilidad desde Ventas.',
            ],
            'items' => [
                'Menú Ventas → Gastronomía → Máquinas vending: ABM de máquinas y rulos.',
                'Menú Ventas → Gastronomía → Rendiciones vending: altas de cierre (informe X) por máquina.',
                'Menú Caja → Rendiciones vending: presentación en tesorería de rendiciones pendientes.',
            ],
        ],
        [
            'titulo' => '2. Conceptos clave',
            'captura_id' => 'flujo',
            'parrafos' => [
                'Antes de operar conviene distinguir los roles de cada sistema y los totales que se graban en Anita:',
            ],
            'tabla' => [
                'caption' => 'Conceptos del módulo',
                'headers' => ['Concepto', 'Dónde', 'Descripción'],
                'rows' => [
                    ['Máquina vending', 'Ventas', 'Equipo con PV, depósito, ubicación y grilla de rulos (artículo + precio por rulo).'],
                    ['Rendición Ventas', 'Ventas', 'Cierre operativo (X): cantidades por rulo, total ventas y medios de cobro. Nº cierre correlativo por empresa.'],
                    ['Presentación caja', 'Caja', 'Registro tesorería que vincula la rendición Ventas con caja, fecha y movimientos por medio.'],
                    ['total X (Anita)', 'rendgastro', 'Total ventas informado en alta/edición Ventas (rendg_total_x).'],
                    ['total Z (Anita)', 'rendgastro', 'Total cobrado en presentación caja (rendg_total_z). Cero hasta presentar; reset al anular caja.'],
                    ['Estado Anita', 'rendgastro', 'Vending usa espacio (pendiente contabilidad). Solo gastronomía salón usa F.'],
                ],
            ],
        ],
        [
            'titulo' => '3. Flujo operativo completo',
            'captura_id' => 'flujo',
            'parrafos' => [
                'El circuito estándar para un cierre de máquina vending sigue este orden. Omitir la configuración de máquinas o presentar dos veces la misma rendición en caja generará error explícito.',
            ],
            'items' => [
                'Configurar máquinas (una vez o al incorporar equipos): ABM en Ventas o sincronizar desde Anita si está habilitado.',
                'Registrar rendición en Ventas: indicar empresa, máquina, jornada, cantidades por rulo y medios de cobro. El sistema asigna Nº cierre y replica a Anita con Z = 0.',
                'Imprimir comprobante Ventas (opcional pero recomendado): PDF con detalle de rulos y totales.',
                'Presentar en Caja: elegir rendición pendiente desde modal, verificar cajero, confirmar medios y guardar. Anita recibe total Z.',
                'Correcciones: si la rendición ya se presentó en caja, anule o elimine primero en Caja → Rendiciones vending; luego podrá editar o eliminar en Ventas.',
            ],
            'tabla' => [
                'caption' => 'Rutas del flujo',
                'headers' => ['Paso', 'Ruta', 'Permiso principal'],
                'rows' => [
                    ['Máquinas', 'ventas/gastronomia/maquinas-vending', 'listar-maquinavending-gastronomia'],
                    ['Rendiciones Ventas', 'ventas/gastronomia/maquinas-vending/rendiciones', 'listar-maquinavending-rendicion-gastronomia'],
                    ['Presentación caja', 'caja/rendicionmaquinavending', 'listar-rendicion-maquinavending-caja'],
                ],
            ],
        ],
        [
            'titulo' => '4. Máquinas vending — listado',
            'captura_id' => 'maquinas_listado',
            'herramientas_clave' => 'maquinas_listado',
            'herramientas_incluir_listado' => false,
            'parrafos' => [
                'La pantalla Máquinas vending centraliza el padrón de equipos por empresa. Cada registro vincula punto de venta fiscal, depósito de stock, ubicación física y la cantidad de rulos configurados.',
                'Si no hay registros y la sincronización Anita está activa (config app.anita_sync_maquinavending_gastronomia_index), el listado muestra instrucción para importar desde Anita o usar el botón Sincronizar desde Anita en cabecera.',
                'La columna Rulos indica cuántos artículos tiene asociados el equipo; debe haber al menos uno para poder cargar rendiciones.',
            ],
        ],
        [
            'titulo' => '5. Máquinas vending — alta y edición',
            'captura_id' => 'maquinas_form',
            'herramientas_clave' => 'maquinas_form',
            'parrafos' => [
                'El formulario de alta/edición organiza la información en tarjetas: datos generales (empresa, nombre, códigos Anita/ARCA, PV, ubicación, depósito) y grilla de rulos.',
                'Al cambiar la empresa se recargan selects de PV, ubicación y depósito vía API. El depósito se elige con modal de consulta (mismo patrón que recuento y movimientos de stock).',
                'Cada rulo asocia un número de selección en la máquina con un artículo de stock y precio de lista. Use el modal de consulta de artículo para evitar errores de código.',
                'Campos codigo_anita y codigo_arca permiten trazabilidad con sistemas externos; la sincronización Anita los completa al importar.',
            ],
            'items' => [
                'Validaciones: empresa, nombre, PV y depósito obligatorios; al menos un rulo con artículo.',
                'Eliminar máquina: solo si no tiene rendiciones registradas.',
            ],
        ],
        [
            'titulo' => '6. Sincronización Anita — máquinas',
            'parrafos' => [
                'Cuando está habilitada la integración, el botón Sincronizar desde Anita importa máquinas de las marcas configuradas (Biyemas, Kandiko, Rebisco) junto con sus rulos y precios.',
                'La sincronización no crea rendiciones ni modifica presentaciones en caja; solo actualiza el maestro maquinavending y maquinavending_articulo.',
                'Comando alternativo en servidor: php artisan maquinavending:sincronizar-anita (útil en despliegue inicial o cron).',
            ],
            'tabla' => [
                'caption' => 'Permisos sincronización',
                'headers' => ['Acción', 'Permiso'],
                'rows' => [
                    ['Botón en pantalla', 'sincronizar-maquinavending-gastronomia-anita'],
                    ['Comando artisan', 'Ejecución en servidor (operador)'],
                ],
            ],
        ],
        [
            'titulo' => '7. Rendiciones Ventas — listado',
            'captura_id' => 'rendicion_ventas_listado',
            'herramientas_clave' => 'rendicion_ventas_listado',
            'herramientas_incluir_listado' => true,
            'parrafos' => [
                'El listado de rendiciones muestra cada cierre con Nº correlativo por empresa, fecha, jornada, máquina, totales de ventas y cobrado, estado de presentación en caja y sync Anita.',
                'El aviso bajo la cabecera recuerda: tras registrar en Ventas se replica a Anita; la presentación en caja es un paso aparte. Mientras Caja = Pendiente puede editar o eliminar.',
                'Los iconos Editar/Eliminar aparecen en gris (solo tooltip) si la rendición ya fue presentada en caja — debe anular la presentación en Caja primero.',
            ],
            'tabla' => [
                'caption' => 'Columnas relevantes',
                'headers' => ['Columna', 'Significado'],
                'rows' => [
                    ['Nº cierre', 'Correlativo por empresa; visible también en Caja como Nº cierre Ventas.'],
                    ['Total ventas / Total cobrado', 'Importes del cierre X; medios deben cuadrar con cobrado.'],
                    ['Caja', 'Pendiente = sin presentación; Presentada = bloqueo edición Ventas.'],
                    ['Anita', 'OK con fecha = replicado a rendgastro/rendvalor.'],
                ],
            ],
        ],
        [
            'titulo' => '8. Rendiciones Ventas — alta y edición',
            'captura_id' => 'rendicion_ventas_form',
            'herramientas_clave' => 'rendicion_ventas_form',
            'parrafos' => [
                'En alta seleccione empresa y máquina; el sistema propone PV, depósito y carga automática de artículos por rulo desde la configuración de la máquina.',
                'Indique fecha de rendición, fecha de jornada (día de turno) y cantidades vendidas por rulo. Los importes se calculan con el precio de lista vigente en la máquina.',
                'En medios de pago distribuya el total cobrado entre cuentas de caja (efectivo, QR, tarjetas). La suma debe igualar el total cobrado.',
                'Al guardar: asigna Nº cierre, persiste líneas de artículos y medios, sincroniza Anita (total X, Z=0, estado espacio) y puede abrir el comprobante PDF.',
                'Edición: permitida solo si no hay presentación en caja. Actualizar vuelve a sincronizar Anita con los nuevos totales.',
            ],
        ],
        [
            'titulo' => '9. Comprobante PDF — rendición Ventas',
            'captura_id' => 'rendicion_ventas_comprobante',
            'parrafos' => [
                'El comprobante PDF de rendición Ventas incluye logo de empresa, cabecera con Nº cierre, máquina, PV, fechas, tabla de rulos (cantidad, precio, importe), resumen de medios y totales.',
                'Acceso: icono imprimir en listado, botón tras guardar, o ruta directa maquinavending_rendicion_comprobante con parámetro inline=1 para visualizar en navegador.',
                'Desde Caja, el icono PDF rojo en el listado de presentaciones reimprime este mismo comprobante Ventas (requiere permiso ver-comprobante-maquinavending-rendicion-gastronomia).',
            ],
        ],
        [
            'titulo' => '10. Presentación Caja — listado',
            'captura_id' => 'caja_listado',
            'herramientas_clave' => 'caja_listado',
            'herramientas_incluir_listado' => true,
            'parrafos' => [
                'Caja → Rendiciones vending lista las presentaciones registradas en tesorería: código interno, fecha caja, empresa, caja física, Nº cierre Ventas vinculado, máquina e importe cobrado.',
                'Export PDF disponible según filtros activos. Paginación server-side conserva criterios de búsqueda.',
                'Editar y eliminar respetan restricciones de fecha (solo día actual salvo permiso encargado) y período contable de caja abierto.',
            ],
        ],
        [
            'titulo' => '11. Presentación Caja — alta',
            'captura_id' => 'caja_form',
            'herramientas_clave' => 'caja_form',
            'parrafos' => [
                'Nuevo registro abre formulario alineado con rendiciones gastronomía y estacionamiento: tarjetas de cabecera, consulta de rendición Ventas pendiente, verificación de cajero y detalle de medios.',
                'Use Consultar rendición para abrir modal con rendiciones Ventas aún no presentadas. Al elegir una, precarga empresa, máquina, Nº cierre y totales de referencia.',
                'Verificación cajero: usuario y contraseña del responsable de caja que recibe el arqueo (misma validación que otros módulos de caja).',
                'Al guardar: crea rendicion_maquinavending_caja, movimientos por medio, marca la rendición Ventas como presentada y actualiza Anita (total Z). Abre comprobante PDF caja.',
            ],
            'items' => [
                'No puede presentar la misma rendición Ventas dos veces.',
                'Fecha de presentación: hoy por defecto; encargado puede fechas anteriores con permiso actualizar-rendicion-maquinavending-caja-dia / -encargado.',
                'Período contable de alcance CAJA debe estar abierto para la fecha de la operación.',
            ],
        ],
        [
            'titulo' => '12. Presentación Caja — edición y anulación',
            'captura_id' => 'caja_editar',
            'herramientas_clave' => 'caja_form',
            'parrafos' => [
                'Editar permite ajustar medios de pago y observaciones de una presentación ya registrada, si la fecha lo permite y el período contable sigue abierto.',
                'Actualizar vuelve a sincronizar Anita con el nuevo total Z (mantiene rendg_estado = espacio).',
                'Eliminar presentación: confirma con diálogo; borra registro caja, movimientos asociados, resetea total Z en Anita y deja la rendición Ventas en estado Pendiente para nueva presentación o edición en Ventas.',
            ],
        ],
        [
            'titulo' => '13. Comprobante PDF — presentación Caja',
            'captura_id' => 'caja_comprobante',
            'parrafos' => [
                'El comprobante de presentación caja incluye logo de empresa, título, código de presentación, fecha, caja, referencia a Nº cierre Ventas y máquina, líneas de medios con importes y total cobrado.',
                'Acceso: icono imprimir en listado o botón Imprimir tras guardar/actualizar. Ruta imprimir_rendicion_maquinavending con inline=1.',
                'Este PDF documenta la recepción en tesorería; el comprobante operativo de Ventas (capítulo 9) conserva el detalle por rulo.',
            ],
        ],
        [
            'titulo' => '14. Permisos principales',
            'parrafos' => [
                'Asigne permisos por rol según la función del usuario. Operadores de máquinas suelen tener Ventas; tesorería requiere permisos Caja.',
            ],
            'tabla' => [
                'caption' => 'Permisos Ventas — máquinas',
                'headers' => ['Permiso', 'Función'],
                'rows' => [
                    ['listar-maquinavending-gastronomia', 'Ver listado máquinas'],
                    ['crear-maquinavending-gastronomia', 'Alta máquina'],
                    ['editar-maquinavending-gastronomia / actualizar-maquinavending-gastronomia', 'Modificar máquina'],
                    ['borrar-maquinavending-gastronomia', 'Eliminar máquina'],
                    ['sincronizar-maquinavending-gastronomia-anita', 'Importar desde Anita'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Permisos Ventas — rendiciones y Caja',
                'headers' => ['Permiso', 'Función'],
                'rows' => [
                    ['listar-maquinavending-rendicion-gastronomia', 'Ver rendiciones Ventas'],
                    ['crear-maquinavending-rendicion-gastronomia', 'Alta rendición'],
                    ['editar-maquinavending-rendicion-gastronomia / actualizar-maquinavending-rendicion-gastronomia', 'Modificar rendición (solo pendiente caja)'],
                    ['borrar-maquinavending-rendicion-gastronomia', 'Eliminar rendición (solo pendiente caja)'],
                    ['ver-comprobante-maquinavending-rendicion-gastronomia', 'PDF rendición Ventas'],
                    ['listar-rendicion-maquinavending-caja', 'Ver presentaciones caja'],
                    ['crear-rendicion-maquinavending-caja', 'Presentar en caja'],
                    ['editar-rendicion-maquinavending-caja / actualizar-rendicion-maquinavending-caja', 'Modificar presentación'],
                    ['borrar-rendicion-maquinavending-caja', 'Anular presentación'],
                ],
            ],
        ],
        [
            'titulo' => '15. Resolución de problemas',
            'parrafos' => [
                'Consultas frecuentes y acciones recomendadas:',
            ],
            'items' => [
                'No veo Editar/Eliminar en rendición Ventas: verifique columna Caja. Si dice Presentada, anule en Caja → Rendiciones vending.',
                'Error al guardar en caja — período contable: abra el período de alcance CAJA para la fecha o use fecha permitida.',
                'Anita no muestra total Z: confirme que la presentación caja se guardó sin error; anular y volver a presentar resetea y regraba Z.',
                'No hay máquinas en listado: ejecute sincronización Anita o cree manualmente con permiso crear-maquinavending-gastronomia.',
                'Medios no cuadran en Ventas: la suma de cuentas de caja debe igualar total cobrado antes de guardar.',
                'Dos presentaciones de la misma rendición: el sistema bloquea duplicados; elimine la presentación errónea en caja.',
            ],
        ],
    ],
];
