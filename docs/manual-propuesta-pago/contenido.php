<?php

/**
 * Manual de usuario — Propuesta de pagos / Tesorería AP premium.
 * Audiencia: tesorería, finanzas, aprobadores, operaciones de pagos.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Cuentas a pagar / Propuesta de pagos y tesorería AP',
    'version' => '1.1',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Introducción: qué tienen en las manos',
            'parrafos' => [
                'Este manual describe el circuito de Cuentas a pagar / pagos a proveedores de Anita ERP. No es “cargar una orden de pago suelta”: es un proceso de gobierno de caja alineado a lo que hacen sistemas como SAP (propuesta F110), Oracle (Payment Process Request) y la práctica operativa de proyecciones tipo AGG.',
                'La idea central: primero se analiza la deuda (proyección), después se arma y se aprueba qué se va a pagar (el lote), y recién entonces el sistema genera las órdenes de pago, el archivo bancario y la conciliación. Así la autorización es sobre la decisión de desembolso, no sobre cada factura aislada ni sobre cada OP.',
                'Pantallas principales del circuito: Proyección de pagos, Precarga y comprobantes de proveedor, Propuesta de pagos, Órdenes de pago, Clearing bancario, Cash position y Cockpit de tesorería. También conviven Solicitud de pago (SP) e Ingreso/Egreso (IE) como canales complementarios.',
                'Menú: Compras → Cuentas a pagar → Precarga · Comprobantes · Pago a proveedores · Propuesta de pagos · Reportes (Errores de precarga, Proyección de pagos). Rutas típicas: `compras/proyeccion-pagos`, `compras/propuesta-pago`, `compras/pagoproveedor`, `compras/tesoreria`, `compras/clearing-bancario`. Las acciones visibles dependen de los permisos de su usuario (roles Enc-pagos / Op-Pagos).',
                'Este documento está en el Centro de ayuda del ERP. Úselo como guía operativa del día a día; el asistente de IA también puede citar extractos cuando pregunte cómo proyectar, autorizar o ejecutar un lote.',
            ],
            'items' => [
                'Premium = árbol de aprobación sobre el lote + instrumentos + lote bancario + clearing + cockpit.',
                'Light = misma propuesta y ejecución, pero la autorización del lote puede auto-aprobarse sin árbol (configurable por empresa).',
                'La OP unitaria sin propuesta sigue existiendo si la empresa lo permite; es el bypass, no el camino premium.',
                'La proyección de pagos es el informe analítico de deuda abierta (equivalente Anita l-proy); alimenta la decisión de armar el lote.',
            ],
        ],
        [
            'titulo' => '2. Visión del circuito premium',
            'captura_id' => 'flujo_premium',
            'parrafos' => [
                'El circuito recomendado es una secuencia clara. Cada paso tiene dueño (tesorería, aprobador, banco) y deja rastro auditable.',
            ],
            'tabla' => [
                'caption' => 'De la deuda al banco',
                'headers' => ['Paso', 'Pantalla', 'Quién', 'Resultado'],
                'rows' => [
                    ['0. Proyectar', 'Reportes → Proyección de pagos', 'Pagos / Tesorería', 'Deuda abierta por tramos / conceptos'],
                    ['1. Armar lote', 'Propuesta de pagos → Nueva', 'Tesorería', 'Borrador con deudas por vencimiento'],
                    ['2. Ajustar montos', 'Editar propuesta', 'Tesorería', 'Inclusiones, parciales, medios'],
                    ['3. Enviar a aprobación', 'Botón Enviar / Autorizar', 'Tesorería', 'Árbol PP o auto-autorización Light'],
                    ['4. Firmar el lote', 'Árbol de aprobación (mails/links)', 'Firmantes', 'Estado AUTORIZADA + monto autorizado'],
                    ['5. Instrumentos', 'Editar (caja/cuenta/chequera)', 'Tesorería', 'Cómo se va a pagar sin bajar el autorizado'],
                    ['6. Ejecutar', 'Ejecutar → OP', 'Tesorería', 'OP por proveedor+medio + retenciones'],
                    ['7. Archivo banco', 'Generar / Exportar lote', 'Tesorería', 'CSV (o driver de convenio)'],
                    ['8. Marcar enviado', 'Marcar enviado banco', 'Tesorería', 'OP bloqueadas contra re-propuesta'],
                    ['9. Clearing', 'Clearing bancario', 'Tesorería', 'Match OP ↔ Interbanking / extracto'],
                    ['10. Seguimiento', 'Cockpit / Cash / Auditoría', 'Finanzas', 'Posición, forecast y compliance'],
                ],
            ],
            'items' => [
                'Empiece por la proyección: sabe cuánto vence, qué está pendiente de aprobación y qué adelantos tiene aplicados.',
                'No saltee la aprobación del lote “porque después controlo en la OP”: el valor premium está en aprobar el desembolso completo.',
                'Si algo falla en la ejecución, el sistema puede dejar EJECUTADA_PARCIAL: use reopen parcial o propuesta delta.',
            ],
        ],
        [
            'titulo' => '3. Conceptos básicos',
            'parrafos' => [
                'Estos términos aparecen en pantallas, badges y auditoría. Conviene manejarlos antes de operar.',
            ],
            'tabla' => [
                'caption' => 'Glosario del módulo',
                'headers' => ['Término', 'Significado para el operador'],
                'rows' => [
                    ['Propuesta (PP)', 'Lote de pagos armado desde la deuda por vencimiento.'],
                    ['Línea', 'Cada deuda incluida (o excluida) en la propuesta.'],
                    ['Monto propuesto', 'Cuánto se quiere pagar de esa deuda (puede ser parcial).'],
                    ['Monto autorizado', 'Tope aprobado por el árbol; no baja al excluir líneas después.'],
                    ['Árbol PP', 'Circuito de firmas por nivel/monto sobre la propuesta.'],
                    ['Premium / Light', 'Con árbol obligatorio vs auto-autorización configurable.'],
                    ['OP', 'Orden de pago a proveedor generada al ejecutar el lote.'],
                    ['Instrumento', 'Caja, cuenta de egreso, chequera, CBU/alias del proveedor.'],
                    ['Lote bancario', 'Archivo de transferencias (CSV genérico o convenio).'],
                    ['ENVIADO', 'Lote marcado como enviado al banco; bloquea esas OP.'],
                    ['Clearing', 'Emparejar OP con vouchers/movimientos Interbanking.'],
                    ['Cockpit', 'Workbench unificado: propuestas + SP + IE + KPIs.'],
                    ['Cash position', 'Saldos IB vs deuda vencida y propuestas abiertas.'],
                    ['Forecast 7/15/30', 'Proyección de egresos por ventanas de vencimiento (cockpit).'],
                    ['Proyección de pagos', 'Informe de deuda abierta por tramos (Anita l-proy); menú Cuentas a pagar → Reportes.'],
                    ['Concepto cash flow', 'Conceptogasto (Anita concoper): se asigna a la cuenta contable y al pago.'],
                    ['Delta', 'Nueva propuesta con lo pendiente/excluido de otra.'],
                    ['SP', 'Solicitud de pago (gastos/conceptos); canal distinto a PP.'],
                    ['IE', 'Ingreso/Egreso de caja (TRA, canje, tesorería omnibus).'],
                ],
            ],
        ],
        [
            'titulo' => '4. Roles, permisos y modos',
            'parrafos' => [
                'No todos ven los mismos botones. Si falta una acción, suele ser permiso o estado incorrecto — no un “bug”. Los roles Enc-pagos y Op-Pagos operan el circuito de Cuentas a pagar junto con administrador.',
            ],
            'tabla' => [
                'caption' => 'Permisos más usados',
                'headers' => ['Permiso (slug)', 'Para qué sirve'],
                'rows' => [
                    ['listar-reporte-proyeccion-pagos', 'Informe Proyección de pagos (consultar / PDF / Excel / CSV)'],
                    ['listar-propuesta-pago', 'Ver listado, PDF, cash, cockpit, auditoría'],
                    ['crear-propuesta-pago', 'Nueva propuesta y propuesta delta'],
                    ['editar-propuesta-pago / actualizar-…', 'Armar y ajustar el lote'],
                    ['enviar-aprobacion-propuesta-pago', 'Enviar al árbol o autorizar Light'],
                    ['ejecutar-propuesta-pago', 'Ejecutar OP, lote, clearing, marcar enviado'],
                    ['borrar-propuesta-pago', 'Eliminar borradores/rechazadas'],
                    ['editar-configuracion-propuesta-pago', 'Premium/Light por empresa'],
                    ['listar-pagoproveedor / crear-… / editar-…', 'Órdenes de pago a proveedores'],
                    ['listar-solicitud-pago / listar-ingresos-egresos-caja', 'Filas SP/IE en el cockpit'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Premium vs Light (por empresa)',
                'headers' => ['Modo', 'Al enviar aprobación', 'Cuándo usarlo'],
                'rows' => [
                    ['Premium', 'Dispara árbol PP; queda EN_APROBACION hasta firmar', 'Gobierno formal de desembolsos'],
                    ['Light', 'Autoriza el lote sin árbol (botón Autorizar light)', 'Empresas chicas o circuitos ágiles'],
                ],
            ],
            'parrafos2' => [
                'La configuración se edita en Compras → Configuración propuesta de pagos (`compras/configuracion-propuesta-pago`). Elija la empresa, el modo y si se permiten OP sin propuesta.',
            ],
        ],
        [
            'titulo' => '5. Configuración Premium / Light',
            'captura_id' => 'config_premium',
            'herramientas_clave' => 'config',
            'parrafos' => [
                'Pantalla: Configuración propuesta de pagos. Similar al ABM de configuración de comprobante proveedor: una ficha por empresa.',
                'Defina: modo Premium u Light, si exige árbol, si al ejecutar la OP nace CONFIRMADA, si se calculan retenciones al ejecutar, y si se permiten OP sueltas sin propuesta.',
                'Cambiar a Premium no “rompe” propuestas ya autorizadas; aplica a los próximos envíos a aprobación.',
            ],
            'items' => [
                'Revise con Finanzas qué empresas deben firmar el lote y cuáles pueden ir Light.',
                'El bridge/clearing Interbanking se habilita por variable de entorno (administración técnica); el operador lo usa desde Clearing.',
            ],
        ],
        [
            'titulo' => '6. Listado de propuestas',
            'captura_id' => 'pp_listado',
            'herramientas_grupos' => [
                ['titulo' => 'Barra y acciones del listado', 'clave' => 'pp_listado', 'incluir_listado' => true],
            ],
            'parrafos' => [
                'Pantalla: Propuesta de pagos. Ve el ID, fecha, empresa, estado, monto total y autorizado.',
                'Use filtros de empresa/estado y la exportación PDF/Excel/CSV (respeta toda la consulta, no solo la página).',
                'Desde aquí puede ir al Cockpit, Cash position y Config. El lápiz abre la propuesta.',
            ],
            'items' => [
                'Estados abiertos habituales: BORRADOR, EN_APROBACION, AUTORIZADA, EJECUTADA_PARCIAL.',
                'EJECUTADA es el cierre feliz del lote; ANULADA/RECHAZADA salen del circuito activo.',
            ],
        ],
        [
            'titulo' => '7. Armar una propuesta (desde la deuda)',
            'captura_id' => 'pp_crear',
            'herramientas_clave' => 'pp_formulario',
            'parrafos' => [
                'Pulse Nueva propuesta. Indique empresa, fecha del lote y el rango de vencimientos que quiere incluir. Opcional: detalle, moneda, caja/cuenta/chequera (también se pueden completar después de autorizar).',
                'Al guardar, el sistema trae la deuda abierta de proveedores en ese rango (cuenta corriente con saldo) y arma la grilla: proveedor, comprobante, vencimiento, medio de pago (M.Pago), detalle, buckets vencidos / a vencer, monto propuesto.',
                'En BORRADOR puede: cambiar montos, desmarcar líneas (excluir), rearmar desde deuda, y guardar. El total del lote se recalcula con las líneas incluidas.',
            ],
            'tabla' => [
                'caption' => 'Columnas típicas de la grilla (estilo proyección)',
                'headers' => ['Columna', 'Para qué mirarla'],
                'rows' => [
                    ['Incluir', 'Si está tildada entra al lote y al monto'],
                    ['Proveedor / comprobante', 'Qué deuda se está pagando'],
                    ['Vencimiento', 'Prioridad de caja'],
                    ['M.Pago / Detalle', 'Cheque, transferencia, etc.'],
                    ['Vencidos / A vencer', 'Clasificación temporal del lote'],
                    ['Monto propuesto', 'Puede ser menor al saldo (pago parcial)'],
                    ['Cuenta (override)', 'Cuenta de egreso distinta a la de cabecera'],
                ],
            ],
            'items' => [
                'No invente deudas: si falta un comprobante, revise vencimientos/filtros o la CC del proveedor.',
                'Rearmar líneas reemplaza la grilla: úselo solo si está seguro.',
            ],
        ],
        [
            'titulo' => '8. Autorización de pagos (el corazón premium)',
            'captura_id' => 'pp_autorizacion',
            'herramientas_clave' => 'pp_aprobacion',
            'parrafos' => [
                'Cuando el lote está listo en BORRADOR (o RECHAZADA), use Enviar a aprobación (Premium) o Autorizar (Light).',
                'En Premium el sistema dispara el árbol de tipo PP: busca el árbol configurado por empresa y monto del lote, crea movimientos a firmantes y envía los links de aprobación/rechazo (mismo esquema de mails que OC/requisiciones). Mientras tanto la propuesta queda EN_APROBACION.',
                'Cada firmante aprueba o rechaza su nivel. Al completar el circuito, la propuesta pasa a AUTORIZADA y se fija el monto autorizado (snapshot). Ese monto es el techo de gobierno: si después excluye líneas, el autorizado no baja solo por eso.',
                'En Light no hay firmante: el botón autoriza el lote de inmediato. Sigue siendo una decisión explícita de “este lote se paga”, no un alta silenciosa de OP.',
                'Importante: la aprobación es del lote completo, no de cada renglón de deuda ni de cada OP futura. Eso es exactamente lo que buscan F110 / PPR / AGG.',
            ],
            'tabla' => [
                'caption' => 'Qué ve cada rol en la autorización',
                'headers' => ['Rol', 'Qué hace', 'Dónde'],
                'rows' => [
                    ['Tesorero', 'Arma el lote y lo envía', 'Editar propuesta → Enviar a aprobación'],
                    ['Firmante nivel N', 'Aprueba/rechaza según monto', 'Mail / link del árbol PP'],
                    ['Finanzas', 'Audita quién firmó qué', 'Auditoría de la propuesta (PDF)'],
                    ['Admin árbol', 'Configura niveles y montos', 'ABM Árbol de aprobación tipo PP'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Estados de la propuesta en el tramo de aprobación',
                'headers' => ['Estado', 'Significado'],
                'rows' => [
                    ['BORRADOR', 'Editable; aún no pedida la firma'],
                    ['EN_APROBACION', 'En circuito de árbol'],
                    ['AUTORIZADA', 'Lista para instrumentos y ejecución'],
                    ['RECHAZADA', 'Volvió; se puede corregir y reenviar'],
                ],
            ],
            'parrafos2' => [
                'Si el árbol no encuentra configuración para la empresa/monto, el envío falla con mensaje claro: hay que cargar el árbol PP antes de operar Premium.',
                'Reabrir (solo AUTORIZADA sin OP): vuelve a BORRADOR y limpia el monto autorizado. Use con criterio: invalida la firma previa.',
            ],
        ],
        [
            'titulo' => '9. Instrumentos y exclusiones post-aprobación',
            'captura_id' => 'pp_instrumentos',
            'parrafos' => [
                'Con la propuesta AUTORIZADA puede completar caja, cuenta de egreso y chequera, y desmarcar líneas que finalmente no se pagan (exclusión soft).',
                'Guardar en ese estado no reabre el árbol: guarda instrumentos y exclusiones. El monto autorizado permanece como rastro de lo aprobado.',
                'También puede indicar cuenta por línea (multi-cuenta) cuando un proveedor sale por otra cuenta.',
            ],
            'items' => [
                'Cheque: el sistema puede tomar el próximo número de la chequera.',
                'Transferencia: conviene tener CBU (y alias) en la forma de pago del proveedor.',
                'Si falta instrumento, la OP puede nacer en PRE CARGA para completar después.',
            ],
        ],
        [
            'titulo' => '10. Ejecutar el lote → órdenes de pago',
            'captura_id' => 'pp_ejecutar',
            'herramientas_clave' => 'pp_ejecucion',
            'parrafos' => [
                'Botón Ejecutar → OP. El sistema agrupa líneas incluidas pendientes por proveedor + forma de pago, calcula retenciones (si está configurado), crea las OP en pagoproveedor y marca las líneas como ejecutadas.',
                'Si todas las líneas salen bien: EJECUTADA. Si algunas fallan o quedan pendientes: EJECUTADA_PARCIAL.',
                'Tras ejecutar, normalmente se intenta el bridge/clearing y se genera el lote bancario de transferencias.',
                'Cada OP queda vinculada a la propuesta (`propuesta_pago_id`). Puede abrirlas desde la grilla del lote bancario o desde Órdenes de pago.',
            ],
            'tabla' => [
                'caption' => 'Resultados posibles',
                'headers' => ['Resultado', 'Qué hacer después'],
                'rows' => [
                    ['EJECUTADA', 'Exportar archivo, marcar enviado, seguir clearing'],
                    ['EJECUTADA_PARCIAL', 'Reabrir parcial o delta; corregir errores y re-ejecutar'],
                    ['Error total', 'Leer mensaje (instrumento, retención, período cerrado, etc.)'],
                ],
            ],
        ],
        [
            'titulo' => '11. Lote bancario y envío al banco',
            'captura_id' => 'pp_lote_bancario',
            'parrafos' => [
                'Generar lote bancario arma el snapshot de transferencias (CUIT, CBU, alias, neto, referencia OP). Exportar descarga el archivo (CSV genérico por defecto).',
                'Valida CBU (22 dígitos BCRA). Líneas sin CBU válido no entran al archivo.',
                'Cuando el archivo ya se subió/envió al home banking, use Marcar enviado banco: el lote pasa a ENVIADO y las OP quedan bloqueadas (no se re-proponen ni se regeneran livianamente).',
                'Si más adelante hay convenio con un banco, el sistema ya tiene el gancho de driver (`convenio_driver`): el operador seguirá el mismo botón Exportar, pero el archivo saldrá en el layout acordado.',
            ],
            'items' => [
                'Exportar ≠ Enviado: exportar es bajar el archivo; marcar enviado es el sello operativo.',
                'Un lote ENVIADO no se reemplaza: se generan lotes nuevos solo con OP no bloqueadas.',
            ],
        ],
        [
            'titulo' => '12. Clearing bancario (OP ↔ Interbanking)',
            'captura_id' => 'clearing',
            'herramientas_clave' => 'clearing',
            'parrafos' => [
                'Pantalla: Clearing bancario. Es el FEBAN “operativo” del módulo: empareja OP confirmadas/pagadas con transferencias Interbanking ya sincronizadas y, si corresponde, con movimientos de extracto.',
                'El motor puntúa candidatos (neto preferido, bruto, CBU, CUIT, cercanía de fecha). Score alto → auto-concilia. Score medio → sugerencia. Sin candidato o ambiguo → excepción.',
                'En el workbench verá: sugerencias/excepciones con botones OK/X, OP pendientes, transferencias libres, movimientos débito, y un formulario de match manual (forzar vínculo OP↔T o OP↔M).',
                'También puede reprocesar una propuesta indicando su ID. El job diario de bridge usa el mismo motor.',
            ],
            'tabla' => [
                'caption' => 'Estados de una sugerencia de clearing',
                'headers' => ['Estado', 'Significado'],
                'rows' => [
                    ['SUGERIDO', 'Hay candidato; confirme o rechace'],
                    ['AUTO', 'Conciliado automáticamente por score alto'],
                    ['CONFIRMADO', 'Match manual o confirmado por usuario'],
                    ['EXCEPCION', 'Sin match claro o score bajo'],
                    ['RECHAZADO', 'Descartado (manual o supersedido)'],
                ],
            ],
            'items' => [
                'Una transferencia o movimiento no puede vincularse a dos OP.',
                'Al conciliar, la OP pasa a PAGADA/CONCILIADA y queda bloqueada como enviada al banco.',
            ],
        ],
        [
            'titulo' => '13. Proyección de pagos (reporte)',
            'captura_id' => 'proyeccion_pagos',
            'herramientas_clave' => 'proyeccion',
            'parrafos' => [
                'Menú: Compras → Cuentas a pagar → Reportes → Proyección de pagos (`compras/proyeccion-pagos`). Permiso: `listar-reporte-proyeccion-pagos` (roles Enc-pagos / Op-Pagos / administrador).',
                'Es el equivalente ERP del informe Anita l-proy: muestra la deuda abierta de proveedores leída solo desde anitaERP (cuenta corriente + aplicaciones hasta la fecha base). No usa el bridge Informix.',
                'Sirve para decidir qué pagar la próxima semana o mes, cuánto está vencido, qué falta aprobar y cómo se reparte la deuda por concepto de cash flow. Con esa lectura arma después la Propuesta de pagos.',
                'Elija empresas, fecha base y tipo de informe: A vencer (proyección hacia adelante) o Vencidos (antigüedad hacia atrás). Defina tramos por días (ej. 7,15,30,60,90,120) o por mes. Opcional: abre saldo anterior, moneda de expresión, proveedores (F1 / lupa), tipos de comprobante, condiciones a compensar, incluir adelantos, estado de aprobación y fecha de carga.',
                'La grilla se agrupa (proveedor, empresa, moneda, medio de pago, condición, tramo o concepto de cash flow) y se ordena (código, nombre, mayor/menor deuda, vencimiento, días). Puede ver detalle por comprobante o solo totales por grupo.',
            ],
            'tabla' => [
                'caption' => 'Columnas y conceptos clave del informe',
                'headers' => ['Columna / idea', 'Qué significa'],
                'rows' => [
                    ['Tramos / Posterior', 'Importes según días (o meses) al vencimiento respecto de la fecha base'],
                    ['A compensar', 'Deuda con condición de pago marcada como compensación'],
                    ['Adelantos', 'Pagos a cuenta sin aplicar (restan del adeudado)'],
                    ['Pend.aprob. / Total aprob.', 'Según estado del comprobante (aprobado/contabilizado vs pendiente)'],
                    ['Total adeudado', 'Deuda neta a pagar en la moneda del informe'],
                    ['N.Con. / Detalle del concepto', 'Concepto de cash flow (conceptogasto = Anita concoper), no el concepto IVA compra'],
                    ['Cuenta cash flow', 'Cuenta contable que aporta el concepto (link al plan de cuentas)'],
                    ['Links azules', 'Proveedor, comprobante, OC, requisición, concepto y cuenta (según permiso)'],
                ],
            ],
            'tabla2' => [
                'caption' => 'De dónde sale el concepto de cash flow',
                'headers' => ['Prioridad', 'Origen'],
                'rows' => [
                    ['1', 'Concepto del movimiento de caja del pago'],
                    ['2', 'Cuenta imputada en la línea del comprobante'],
                    ['3', 'Cuenta de mayor peso del asiento (prioriza resultados sobre pasivo)'],
                    ['4', 'Concepto por defecto del proveedor'],
                ],
            ],
            'parrafos2' => [
                'Use el botón Columnas para armar vistas (Ejecutiva, Tesorería, Análisis de origen, Cash flow o Todo): elige y ordena columnas; la preferencia se guarda por usuario. Exporta PDF, Excel o CSV con los mismos filtros y columnas (no solo la página visible).',
                'En PDF multiempresa sin consolidar, el sistema genera un PDF por empresa y los une. En pantalla, haga clic en la cabecera del grupo para colapsar el detalle.',
            ],
            'items' => [
                'No confunda concepto IVA compra (tablas de compras) con concepto cash flow (Caja → Conceptos de gasto).',
                'Si un total en moneda extranjera “no cierra”, revise el modo de cotización (fecha base vs histórica) y las advertencias de cotización vigente.',
                'Después de proyectar: arme la propuesta con el mismo rango de vencimientos que acaba de mirar.',
            ],
        ],
        [
            'titulo' => '14. Cockpit, cash position y forecast',
            'captura_id' => 'cockpit',
            'herramientas_clave' => 'cockpit',
            'parrafos' => [
                'Cockpit de tesorería (`compras/tesoreria`): no es solo un menú de accesos. Incluye KPIs (saldos IB, deuda vencida, propuestas, disponible), forecast 7/15/30 y una grilla operativa única con Propuestas (PP), Solicitudes de pago (SP) e Ingresos/Egresos (IE).',
                'Filtre por empresa, tipo (PP/SP/IE) y ventana de días. Cada fila tiene link al documento (abre en solapa).',
                'Cash position profundiza saldos Interbanking, deuda vencida y propuestas abiertas. El forecast muestra cuánto vence en cada ventana y el saldo proyectado si se pagara. Complementa —no reemplaza— el informe de Proyección de pagos (más analítico y configurable).',
            ],
            'items' => [
                'Use el cockpit a la mañana para priorizar: qué firmar, qué ejecutar, qué SP/IE hay abiertos.',
                'Los accesos inferiores llevan a OP, Interbanking, Clearing, etc.',
            ],
        ],
        [
            'titulo' => '15. Excepciones: reabrir, parcial y delta',
            'captura_id' => 'excepciones',
            'parrafos' => [
                'La vida real no es lineal. El módulo contempla excepciones de gobierno sin romper el rastro.',
            ],
            'tabla' => [
                'caption' => 'Herramientas de excepción',
                'headers' => ['Acción', 'Cuándo', 'Efecto'],
                'rows' => [
                    ['Reabrir', 'AUTORIZADA sin OP aún', 'Vuelve a BORRADOR; limpia autorizado'],
                    ['Reabrir parcial', 'EJECUTADA_PARCIAL', 'Pendientes vuelven a AUTORIZADA; OP enviadas siguen bloqueadas'],
                    ['Propuesta delta', 'Autorizada/ejecutada con pendientes o excluidas', 'Nueva BORRADOR con esas líneas'],
                    ['Excluir línea (soft)', 'AUTORIZADA', 'No se ejecuta; no baja monto autorizado'],
                    ['Marcar enviado', 'Hay lote exportado', 'Bloquea OP del archivo'],
                ],
            ],
            'parrafos2' => [
                'Regla de oro: lo ya enviado al banco no se “deshace” con un reopen. Si hay que corregir, trabaje con delta o anulación formal de OP según política de la empresa.',
            ],
        ],
        [
            'titulo' => '16. Auditoría y compliance',
            'captura_id' => 'auditoria',
            'parrafos' => [
                'Desde la propuesta: Auditoría (pantalla y PDF). Resume montos, líneas incluidas/ejecutadas/pendientes/excluidas, historia de estados con usuario, firmas del árbol PP (nivel, destinatario, fechas), OP con marca de bloqueo banco, y lotes bancarios (estado, archivo, driver, enviado).',
                'Use este pack para auditorías internas, compliance y para responder “quién autorizó este desembolso”.',
            ],
            'items' => [
                'La historia de estados también se ve al pie de la edición de la propuesta.',
                'Conserve el PDF de auditoría junto al archivo bancario del lote cuando el banco lo requiera.',
            ],
        ],
        [
            'titulo' => '17. Relación con OP, SP e IE',
            'parrafos' => [
                'Órdenes de pago (`compras/pagoproveedor`): documento de ejecución del lote (retenciones, aplicación a CC, asiento). Pueden existir OP sin propuesta si la configuración lo permite.',
                'Solicitud de pago: canal de gastos/conceptos con su propio árbol. En el cockpit aparecen junto a las propuestas para ver la cola completa de tesorería.',
                'Ingreso/Egreso: omnibus de caja (transferencias entre cuentas, canje de cheques, etc.). No lleva el árbol de retenciones AP; el selector de modo de uso ayuda a elegir el circuito correcto al crear.',
                'No mezcle conceptos: la autorización premium de pagos a proveedores vive en la Propuesta; la SP autoriza otro tipo de desembolso; el IE mueve caja. La proyección solo informa; no genera OP.',
            ],
        ],
        [
            'titulo' => '18. Buenas prácticas y preguntas frecuentes',
            'parrafos' => [
                'Antes de armar el lote: corra la proyección con los mismos tramos que va a usar en la propuesta.',
                'Antes de enviar a aprobación: revise vencidos vs a vencer, medios de pago y que los CBU estén cargados en el proveedor.',
                'Antes de ejecutar: confirme caja/cuenta/chequera y exclusiones finales.',
                'Antes de marcar enviado: verifique el archivo exportado y que el home banking aceptó el lote.',
                'Diario: corra Clearing o deje el job automático; revise excepciones del workbench.',
            ],
            'tabla' => [
                'caption' => 'FAQ rápida',
                'headers' => ['Pregunta', 'Respuesta corta'],
                'rows' => [
                    ['¿Dónde está la proyección?', 'Compras → Cuentas a pagar → Reportes → Proyección de pagos.'],
                    ['¿Por qué no veo el concepto?', 'Active las columnas Cash flow o use la vista predefinida Cash flow.'],
                    ['¿Concepto IVA o cash flow?', 'Cash flow = conceptogasto; IVA compra es otra tabla (impuestos).'],
                    ['¿Por qué no puedo ejecutar?', 'Debe estar AUTORIZADA y con líneas incluidas pendientes.'],
                    ['¿Por qué no hay árbol?', 'Modo Light, o falta ABM árbol tipo PP para la empresa/monto.'],
                    ['¿Por qué el CSV no incluye una OP?', 'CBU inválido/ausente, o es cheque sin CBU, o OP bloqueada.'],
                    ['¿Puedo bajar el autorizado excluyendo líneas?', 'No automáticamente: el autorizado es snapshot de firma.'],
                    ['¿Qué es el delta?', 'Nueva propuesta con lo que no se pagó / se excluyó.'],
                    ['¿Clearing borra datos del banco?', 'No: solo vincula registros ya sincronizados de Interbanking.'],
                    ['¿Dónde veo todo junto?', 'Cockpit de tesorería.'],
                ],
            ],
            'items' => [
                'Si el período contable está cerrado, la ejecución de OP fallará: coordine con Contabilidad.',
                'Para soporte técnico, indique ID de propuesta, estado, y si el lote está ENVIADO; o filtros de la proyección si el problema es el informe.',
            ],
        ],
    ],
];
