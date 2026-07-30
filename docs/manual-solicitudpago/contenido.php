<?php

/**
 * Manual de usuario — Solicitudes de pago.
 * Audiencia: operadores de tesorería / pagos / administración / capital humano / impuestos.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Solicitudes de pago',
    'version' => '1.1',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Introducción',
            'parrafos' => [
                'Este manual explica el circuito de Solicitudes de pago (SP) en Anita ERP: cómo listar, filtrar, cargar (una a una o por CSV masivo), autorizar, pagar y consultar planes con cuotas (madre e hijas). Está pensado para quien usa el sistema en el día a día; no hace falta saber programación.',
                'Una solicitud de pago es el pedido formal de desembolsar dinero a un proveedor o beneficiario. Puede ser un pago único o formar parte de un plan de cuotas. El sistema acompaña el circuito desde la emisión hasta el pago (y, si corresponde, la conciliación con el mayor contable).',
                'Menú principal: Solicitud de pago → Solicitudes de pago (listado operativo) e Informe solicitudes de pago (consulta analítica tipo Anita). Las acciones visibles dependen de los permisos de su usuario.',
                'La documentación oficial está en el Centro de ayuda del ERP. Ahí encontrará este manual y el resto de guías de módulo. El asistente de IA (panel FAB) también puede citar extractos de este manual cuando pregunta cómo operar una SP.',
            ],
            'items' => [
                'El listado recuerda los filtros de la sesión: si vuelve al menú, recupera la última consulta.',
                'Las SP hijas de un plan no llevan cuotas propias: el plan se edita siempre en la SP madre.',
                'Desde el ícono de plan/cuotas puede consultar madre e hijas sin perder el listado.',
                'Carga masiva CSV: botón en el listado (permiso crear) para incorporar muchas SP de una vez, con vista previa.',
            ],
        ],
        [
            'titulo' => '2. Conceptos básicos',
            'captura_id' => 'flujo_operativo',
            'parrafos' => [
                'Antes de operar conviene tener claros estos términos. Aparecen en filtros, badges del listado y solapas del formulario.',
            ],
            'tabla' => [
                'caption' => 'Glosario rápido',
                'headers' => ['Término', 'Significado para el operador'],
                'rows' => [
                    ['SP', 'Solicitud de pago: comprobante interno con código correlativo.'],
                    ['Concepto', 'Clasifica el motivo del pago; define si exige plan de cuotas o no.'],
                    ['Tratamiento', 'Normal, Urgente, Anticipada, Plan de pago o Recurrente.'],
                    ['SP madre', 'Solicitud con plan de cuotas. Genera (o vinculará) SP hijas.'],
                    ['SP hija', 'Cuota generada a partir de una madre. Hereda concepto; no arma plan propio.'],
                    ['Cuota', 'Renglón del plan (nº, vencimiento, monto) en la madre.'],
                    ['Asiento', 'Solapa Cuentas: Debe/Haber que debe balancear antes de grabar.'],
                    ['Árbol', 'Circuito de aprobación por niveles (correos a firmantes).'],
                    ['IE', 'Instrumento de egreso / pago en caja al autorizar una SP.'],
                    ['Informe SP', 'Consulta analítica con filtros de período, familia y conciliación.'],
                    ['Carga masiva', 'Alta de muchas SP desde un CSV (formato Anita p-cargasolpm), con preview y confirmación.'],
                ],
            ],
            'items' => [
                'Badge Madre: la SP tiene plan de cuotas o hijas vinculadas.',
                'Badge Hija: la SP pertenece a un plan; en la columna SP madre aparece el código del plan.',
                'Columna Cuotas: muestra generadas/total (ej. 3/9) cuando hay plan.',
            ],
        ],
        [
            'titulo' => '3. Circuito del proceso',
            'captura_id' => 'circuito_estados',
            'parrafos' => [
                'El ciclo habitual de una SP es: Emitida → (Controlada) → Autorizada → Pagada. También puede Suspenderse, Rechazarse o marcarse Terminada según el negocio.',
                'En planes de pago: primero se carga la madre con el concepto de cuotas y el detalle del plan; luego el sistema (o un proceso) genera hijas por cuota. Cada hija sigue su propio circuito de aprobación y pago.',
                'Si necesita rearmar notificaciones del árbol (sin estar Pagada), use Reenviar al árbol o Reenviar correo en la edición de la SP.',
                'Las SP creadas por carga masiva CSV nacen Autorizadas (igual que en Anita): no disparan el árbol al crear. Sirven para liquidaciones y lotes ya revisados fuera del ERP.',
            ],
            'tabla' => [
                'caption' => 'Estados principales',
                'headers' => ['Estado', 'Qué implica'],
                'rows' => [
                    ['Emitida', 'SP cargada; inicia o espera el circuito de aprobación.'],
                    ['Controlada', 'Pasó un control intermedio (según árbol / Anita).'],
                    ['Autorizada', 'Lista para pagar (IE) o marcar pagada. También estado de salida de la carga masiva CSV.'],
                    ['Pagada', 'Desembolso registrado; no se reenvía al árbol.'],
                    ['Suspendida', 'Fuera de circuito temporalmente (fila gris en listado).'],
                    ['Rechazada', 'No continúa el circuito de pago.'],
                    ['Terminada', 'Cierre operativo del comprobante.'],
                ],
            ],
        ],
        [
            'titulo' => '4. Listado de solicitudes',
            'herramientas_grupos' => [
                ['titulo' => 'Barra superior, filtros y acciones', 'clave' => 'sp_listado', 'incluir_listado' => true],
            ],
            'captura_id' => 'sp_listado',
            'parrafos' => [
                'Pantalla: Solicitud de pago → Solicitudes de pago. Muestra código, fecha, concepto, proveedor/beneficiario, monto, tratamiento, estado, SP madre y avance de cuotas.',
                'Haga clic en el código (azul) para editar o consultar. El enlace de SP madre abre el plan. El contador de cuotas lleva a la solapa Cuotas de la madre.',
                'Exportación PDF / Excel / CSV: respeta los filtros activos de toda la consulta, no solo la página visible.',
                'Memoria de filtros: al consultar, el sistema guarda los criterios en la sesión. Si entra de nuevo al listado sin parámetros, recupera esa consulta. Para empezar de cero use Limpiar filtros (o ?limpiar_filtros=1).',
                'Documentación: use el Centro de ayuda del ERP (no hay botón Manual en el listado).',
            ],
            'items' => [
                'Lápiz: editar la solicitud.',
                'Ícono sitemap (plan/cuotas): abre el modal con el plan madre e hijas sin salir del listado.',
                'Papelera: eliminar (según permiso); confirme antes de borrar.',
                'Nuevo registro: alta de una SP.',
                'Carga masiva: importa un CSV Anita, muestra preview y genera las SP.',
            ],
        ],
        [
            'titulo' => '5. Carga masiva desde CSV',
            'parrafos' => [
                'En el listado, el botón Carga masiva (permiso crear-solicitud-pago) abre un modal para incorporar muchas solicitudes de una vez, con el mismo formato que Anita (proceso p-cargasolpm).',
                'Paso 1 — Archivo: suba un CSV (coma o punto y coma). La primera fila de encabezado se ignora automáticamente. Máximo 1000 filas de datos.',
                'Paso 2 — Vista previa: el sistema valida empresa, proveedor, concepto, sector, forma de pago, moneda y cuentas contables (por empresa). Muestra cuántas SP se generarán, monto total, totales por empresa, códigos estimados y el detalle fila a fila (OK o error).',
                'Paso 3 — Confirmar: pulse Generar N solicitudes. Cada fila válida se graba como SP Autorizada, tratamiento Normal, fecha de entrega = hoy, sin plan de cuotas ni archivos. Al terminar verá el rango de códigos creados y links a las primeras SP.',
            ],
            'tabla' => [
                'caption' => 'Columnas del CSV (orden fijo Anita)',
                'headers' => ['Col', 'Campo', 'Notas'],
                'rows' => [
                    ['1', 'Empresa', 'Código Anita de empresa (ej. 1, 2, 3).'],
                    ['2', 'Proveedor', 'Código de proveedor (se completa a 6 dígitos).'],
                    ['3', 'Concepto', 'Código de concepto de solicitud de pago.'],
                    ['4', 'Sector', 'Código de sector SP (también aporta el centro de costo de línea).'],
                    ['5', 'Forma de pago', 'Código de forma de pago SP.'],
                    ['6', 'Beneficiario', 'Texto opcional.'],
                    ['7', 'Moneda', 'Código moneda Anita (ej. 1 = pesos).'],
                    ['8', 'Detalle', 'Texto descriptivo (máx. 180).'],
                    ['9', 'Fecha vencimiento', 'dd/mm/aaaa; vacío = sin vencimiento.'],
                    ['10', 'Monto total', 'Importe del pago (sin espacios; se limpian comas de miles).'],
                    ['11+', 'Pares cuenta/importe', 'Cuenta con guión (ej. 111050-009) + importe; primera = Haber, segunda = Debe, y así.'],
                ],
            ],
            'items' => [
                'Si el importe de la primera cuenta es 0, se usa el monto total en las (hasta) dos primeras cuentas.',
                'Filas con error (maestro inexistente, cuenta no hallada, monto 0, empresa no asignada) no se generan; el preview las lista.',
                'Revise siempre el preview antes de confirmar: la generación escribe en ERP y en Anita.',
                'Roles típicos con permiso: Impuestos (Enc/Op) y Capital Humano (enc/op/ger/opcont).',
            ],
        ],
        [
            'titulo' => '6. Filtros del listado (detalle)',
            'captura_id' => 'sp_filtros',
            'parrafos' => [
                'Hay dos modos de búsqueda. Entenderlos evita “no encuentro la SP” cuando en realidad el filtro la está ocultando.',
                'Búsqueda rápida (panel cerrado): escriba en la caja superior y pulse Enter o la lupa. Busca en código, detalle, beneficiario y observación. Tolera errores de tipeo en textos descriptivos.',
                'Panel Filtros (abierto): defina campo + condición + valor, y/o use los selectores de Estado, Tratamiento, Madre/Hija y rango de fechas. Pulse Aplicar filtros. Con el panel abierto, Enter aplica los criterios del panel (no la búsqueda rápida).',
                'Cuando hay criterios activos, el botón Filtros y el campo de búsqueda se marcan en amarillo y aparece Limpiar filtros.',
            ],
            'tabla' => [
                'caption' => 'Filtro Madre / Hija',
                'headers' => ['Opción', 'Qué muestra'],
                'rows' => [
                    ['— Todas —', 'Sin filtro por vínculo familiar.'],
                    ['Solo madres (sin vínculo)', 'SP sin madre (pueden o no tener plan).'],
                    ['Solo hijas', 'SP generadas desde un plan (tienen SP madre).'],
                    ['Madres con plan / cuotas', 'Madres que ya tienen renglones de cuota.'],
                    ['Familia (madres e hijas)', 'Madres con plan/hijas y todas las hijas.'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Otros filtros del panel',
                'headers' => ['Filtro', 'Uso típico'],
                'rows' => [
                    ['Estado', 'Ej. Autorizada para armar la cola de pagos.'],
                    ['Tratamiento', 'Ej. Plan de pago para ver solo planes.'],
                    ['Fecha desde / hasta', 'Restringe por fecha de la SP.'],
                    ['Campo + condición', 'Ej. Código igual a 10970; Detalle contiene “alquiler”.'],
                ],
            ],
            'parrafos2' => [
                'Consejo: para localizar una cuota (hija) por período, filtre Solo hijas + fechas. Para ver el plan completo de una madre, use el ícono plan/cuotas o abra la madre y la solapa Cuotas.',
            ],
        ],
        [
            'titulo' => '7. Consulta de madres, hijas y cuotas',
            'captura_id' => 'sp_modal_familia',
            'parrafos' => [
                'Desde el listado, el ícono de plan/cuotas (sitemap) abre un modal con el plan de la familia: si eligió una hija, el sistema muestra el plan de su madre.',
                'En el modal verá el avance (cuotas generadas / total), cada cuota con vencimiento, monto, código de SP hija y estado. Los enlaces azules abren la SP en una solapa nueva sin menú (modo consulta).',
                'Al terminar, cierre esa solapa con Cerrar solapa: vuelve al listado con el mismo filtro y el modal de origen intacto.',
                'En la edición de una hija verá el aviso “Esta SP es hija del plan” con link a la madre. La solapa Cuotas no pide cargar un plan: el plan se mantiene solo en la madre, aunque el concepto sea de cuotas.',
            ],
            'tabla' => [
                'caption' => 'Dónde mirar cada cosa',
                'headers' => ['Necesito…', 'Dónde hacerlo'],
                'rows' => [
                    ['Ver todas las cuotas de un plan', 'Modal plan/cuotas o solapa Cuotas de la madre.'],
                    ['Abrir una hija sin perder el listado', 'Link del modal → solapa consulta → Cerrar solapa.'],
                    ['Editar vencimientos/montos del plan', 'Solo en la SP madre (solapa Cuotas).'],
                    ['Regrabar una hija', 'Formulario de la hija: asiento sí; cuotas no obligatorias.'],
                    ['Informe con detalle de cuotas', 'Informe solicitudes de pago (tratamiento Familia / Con plan).'],
                ],
            ],
            'items' => [
                'No duplique el plan en una hija: el sistema lo rechaza o lo ignora a propósito.',
                'Si la madre está fuera del rango de fechas del informe, use el tratamiento Familia para traerla junto con sus hijas del período.',
            ],
        ],
        [
            'titulo' => '8. Alta y edición de una SP',
            'herramientas_grupos' => [
                ['titulo' => 'Cabecera y solapas', 'clave' => 'sp_formulario'],
            ],
            'captura_id' => 'sp_formulario',
            'parrafos' => [
                'Flujo recomendado: Nuevo registro → empresa, fecha, tratamiento, concepto (lupa), proveedor o beneficiario, monto → solapa Cuentas (asiento balanceado) → si el concepto exige cuotas, solapa Cuotas → Archivos si hay adjuntos → Guardar.',
                'Solapas: Datos (cabecera), Cuentas (asiento Debe/Haber), Cuotas (solo madres con concepto de cuotas), Archivos, Historial.',
                'El asiento es obligatorio y debe balancear (total Debe = total Haber). Si no, el sistema no deja grabar.',
                'Concepto con forma de pago “Cuotas”: en la madre exige al menos una cuota con vencimiento e importe. En una hija ese control no aplica.',
                'Desde modo consulta (abierto desde modal o informe) puede ver o actualizar según permiso, y siempre Cerrar solapa para volver al origen.',
            ],
            'items' => [
                'Suspender / Levantar suspensión: saca o reincorpora la SP del circuito.',
                'Reenviar al árbol: reinicia movimientos del árbol y vuelve a notificar.',
                'Reenviar correo: reenvía el mail del nivel pendiente sin reiniciar el árbol.',
                'Pagar (IE) / Marcar pagada: disponibles cuando está Autorizada.',
            ],
        ],
        [
            'titulo' => '9. Plan de cuotas (madre)',
            'captura_id' => 'sp_cuotas',
            'parrafos' => [
                'En la solapa Cuotas de una madre puede agregar filas (nº, vencimiento, monto) o importar un Excel (columnas flexibles: nro, vencimiento, monto y alias).',
                'Cada cuota puede quedar vinculada a una SP hija cuando el proceso de generación la crea. Mientras esté Pendiente, aún no existe hija.',
                'Conserve la coherencia: la suma de cuotas debería explicar el monto del plan según la política de su empresa.',
            ],
            'items' => [
                'Importar Excel: elija archivo y pulse Importar; revise la grilla antes de guardar la SP.',
                'Agregar cuota: agrega una fila vacía al final.',
                'Eliminar fila: quita esa cuota del plan (al guardar).',
            ],
        ],
        [
            'titulo' => '10. Informe de solicitudes de pago',
            'herramientas_grupos' => [
                ['titulo' => 'Consulta e informe', 'clave' => 'sp_informe'],
            ],
            'captura_id' => 'sp_informe',
            'parrafos' => [
                'Pantalla: Solicitud de pago → Informe solicitudes de pago. Es la consulta analítica (equivalente funcional al listado Anita de SP) con filtros de empresa, período, sector, estado y tratamiento.',
                'Tratamientos del informe: Todas; Sin SP automáticas; Solo madres con plan; Solo hijas; Familia completa (expande madres e hijas del período, útil cuando la madre tiene fecha fuera del rango pero hay hijas adentro).',
                'Opcionalmente puede incluir conciliación contra el mayor (subdiario + ctamov) para SP pagadas vía IE de caja.',
                'Los códigos azules abren la SP en solapa de consulta sin menú. Exporte PDF, Excel o CSV con los mismos filtros.',
            ],
            'tabla' => [
                'caption' => 'Tratamientos del informe',
                'headers' => ['Opción', 'Cuándo usarla'],
                'rows' => [
                    ['Todas', 'Vista general del período.'],
                    ['Sin SP automáticas', 'Excluye madres de plan (enfoque en pagos “simples”).'],
                    ['Solo madres con plan', 'Auditoría de planes cargados.'],
                    ['Solo hijas', 'Cuotas generadas en el período.'],
                    ['Familia completa', 'Madre + hijas aunque la madre tenga otra fecha.'],
                ],
            ],
        ],
        [
            'titulo' => '11. Permisos',
            'parrafos' => [
                'Si no ve un botón descrito aquí, su rol probablemente no tiene el permiso. Solicítelo al administrador de usuarios.',
                'Roles operativos habituales con el CRUD de SP (incluye carga masiva): Impuestos (Enc-impuestos, Op-impuestos) y Capital Humano (enc-Capital Humano, op-Capital Humano, ger-capitalhumano, opcont-capitalhumano), además de administrador.',
            ],
            'tabla' => [
                'caption' => 'Permisos principales',
                'headers' => ['Permiso', 'Para qué sirve'],
                'rows' => [
                    ['listar-solicitud-pago', 'Ver listado, exportar, modal de plan, modo consulta.'],
                    ['crear-solicitud-pago', 'Nuevo registro y carga masiva CSV.'],
                    ['editar-solicitud-pago', 'Abrir formulario de edición.'],
                    ['actualizar-solicitud-pago', 'Guardar cambios, importar cuotas.'],
                    ['borrar-solicitud-pago', 'Eliminar SP.'],
                    ['listar-informe-solicitudpago', 'Informe analítico y sus exportaciones.'],
                    ['listar-todas-solicitud-pago', 'Ver todas las SP sin restringir por centro de costo (Impuestos).'],
                ],
            ],
        ],
        [
            'titulo' => '12. Buenas prácticas',
            'parrafos' => [
                'Antes de grabar una madre de plan, confirme concepto (Cuotas), montos y vencimientos: corregir después de generar hijas es más costoso.',
                'Use el modal de familia desde el listado para recorrer hijas sin perder filtros.',
                'Para colas de pago: filtre Estado = Autorizada y exporte Excel si necesita trabajar fuera del ERP.',
                'Si “desapareció” una SP del listado, revise filtros activos (amarillo) o Limpiar filtros; la memoria de sesión puede estar aplicando un criterio viejo.',
                'En hijas, no intente cargar cuotas: el sistema las omite a propósito. Edite el plan en la madre.',
                'En carga masiva: valide el CSV en el preview; no confirme si hay muchas filas en error. Conserve una copia del archivo fuera del ERP.',
            ],
            'items' => [
                'Mantenga el asiento balanceado: es el control más frecuente al grabar.',
                'Adjunte comprobantes en Archivos para la auditoría del árbol.',
                'Documente en Detalle/Observación el vínculo con OC, contrato o legajo cuando aplique.',
                'Consulte siempre el Centro de ayuda para el procedimiento vigente; el copiloto IA cita este manual.',
            ],
        ],
    ],
];
