<?php

/**
 * Manual de usuario — Conciliación bancaria (Contaduría / Tesorería).
 * Audiencia: Enc-contaduría, Contaduría operativa, Tesorería, Ger-administracion.
 * v2.0 — alcance premium (oleadas 1–4 + polish).
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Conciliación bancaria premium (Contaduría / Tesorería)',
    'version' => '2.0',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Introducción: qué hace el módulo',
            'parrafos' => [
                'Este manual describe la conciliación bancaria premium de Anita ERP. Cruza el mayor (Anita: subdiario + ctamov) contra el extracto bancario (Interbanking o archivo Excel/CSV/OFX/CAMT.053/MT940), arma la carátula del mes, permite terminar a mano con multi-match, cerrar y aprobar con evidencia, gestionar worklist de excepciones, clasificar residuales y proyectar aging (forecast).',
                'El modelo operativo es el de Argentina: Interbanking + archivo de extracto. No hay feed open-banking europeo: el banco entra por IB y/o por archivo.',
                'Menú: Contable → Conciliación bancaria · Tablero · Aging · Worklist excepciones. Configuración: Configuración → por módulo → Contable → Conciliación bancaria (reglas). Atajos desde el index: Tablero, Aging, Worklist, Reglas, Manual, Excel.',
                'Alcance premium cerrado: extracto versionado + KPI + lock de período; clearing OP/cobranza/IE; worklist + residuales + parsers CAMT/MT940; forecast de aging + score IA + evidencia de cierre/aprobación; configuración 100 % en pantalla (incluye solapa Residuales).',
                'Este documento está en el Centro de ayuda. Úselo como guía del cierre de mes; el asistente de IA puede citar extractos cuando pregunte cómo conciliar, cerrar o configurar mapas.',
            ],
            'items' => [
                'Mayor = subdiario + ctamov (mismo criterio que el mayor plano). No hace falta “copiar” el subdiario a ctamov.',
                'Sin Interbanking se trabaja igual importando extracto (cuenta EXT-{código}), también CAMT.053 y MT940.',
                'Los emparejamientos (pares 1:1 y grupos multi) se guardan y no se vuelven a proponer en meses siguientes.',
                'Toda la parametrización de negocio vive en pantalla: no hace falta deploy para tolerancias, P.C.C., residuales ni cuentas de gasto.',
            ],
        ],
        [
            'titulo' => '2. Circuito mensual',
            'captura_id' => 'flujo_mensual',
            'parrafos' => [
                'El circuito recomendado es una secuencia clara. Cada paso tiene dueño (Contaduría, Tesorería, Enc-contaduría) y deja rastro auditable.',
            ],
            'tabla' => [
                'caption' => 'Del extracto al período aprobado',
                'headers' => ['Paso', 'Pantalla', 'Quién', 'Resultado'],
                'rows' => [
                    ['1. Corrida', 'Index → Conciliar', 'Contaduría', 'Carátula + pares + residuales + KPI'],
                    ['2. Revisar', 'Solapas del resultado', 'Contaduría', 'Cheques / IB / gastos / clearing'],
                    ['3. Manual', 'Workspace manual', 'Contaduría / Tesorería', 'Multi-match y extracto'],
                    ['4. Excepciones', 'Worklist', 'Contaduría', 'Asignar / resolver aging duro'],
                    ['5. Cerrar', 'Manual → Cerrar + evidencia', 'Contaduría', 'Snapshot + adjuntos'],
                    ['6. Aprobar', 'Manual → Aprobar (otro usuario)', 'Enc-contaduría', 'Período aprobado + evidencia'],
                    ['7. Control', 'Tablero / Aging / Forecast', 'Gerencia', 'Avance multi-cuenta'],
                ],
            ],
            'items' => [
                'Empiece por la corrida del index: sabe la diferencia, residuales clasificados y qué quedó pendiente.',
                'No saltee el workspace manual “porque el Excel Contaduría ya está”: el valor premium está en cerrar y aprobar con pares/grupos persistidos y evidencia.',
                'SoD: quien cerró no puede aprobar el mismo período.',
                'Gastos bancarios (egreso + ICO) se generan desde la solapa Gastos cuando Doc. gastos está completo.',
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
                    ['Carátula', 'Cuadro de saldos del mes (banco vs contable).'],
                    ['Par', 'Emparejamiento 1:1 guardado (mayor ↔ banco).'],
                    ['Grupo', 'Emparejamiento multi N:1 / 1:N / N:N.'],
                    ['Enganche', 'Estado de la cuenta: IB OK, EXT-… o sin extracto.'],
                    ['P.C.C.', 'Código de agrupación Interbanking (grouping_code_ib).'],
                    ['AC', 'Acumulado diario (sellos, IVA 10,5 %, etc.).'],
                    ['EXT-{código}', 'Cuenta virtual de extracto cuando no hay IB.'],
                    ['Dry-run', 'Análisis sin grabar (Alinear Excel Contaduría).'],
                    ['Tolerancia', 'Importe máximo de diferencia aceptable en multi-match / carátula.'],
                    ['Codificación', 'Código Contaduría 2…10 / AC sobre el extracto.'],
                    ['Worklist', 'Cola de excepciones (aging → asignar / resolver).'],
                    ['Residual', 'Débito/crédito de extracto sin par (interés, fee, FX) clasificado por regla.'],
                    ['Evidencia', 'Adjunto del cierre o de la aprobación (PDF, imagen, Excel).'],
                    ['Forecast', 'Proyección de importe pendiente por ventana de aging.'],
                    ['SoD', 'Segregation of duties: quien cierra ≠ quien aprueba.'],
                    ['Lock', 'Bloqueo contable Interbanking del mes cerrado/aprobado.'],
                    ['CAMT.053 / MT940', 'Formatos bancarios de extracto (además de Excel/CSV/OFX).'],
                    ['Clearing', 'Vínculo automático de OP / cobranza / IE con el movimiento IB.'],
                ],
            ],
        ],
        [
            'titulo' => '4. Roles, permisos y checklist',
            'parrafos' => [
                'No todos ven los mismos botones. Si falta una acción, suele ser permiso o estado incorrecto — no un “bug”. Roles habituales: administrador, Enc-contaduría, Ger-administracion, Enc-admin.',
            ],
            'tabla' => [
                'caption' => 'Permisos más usados',
                'headers' => ['Permiso (slug)', 'Para qué sirve'],
                'rows' => [
                    ['listar-conciliacion-bancaria', 'Index, consultar, workspace manual, Excel'],
                    ['ejecutar-conciliacion-bancaria', 'Cerrar / reabrir / adjuntar evidencia'],
                    ['listar-conciliacion-bancaria-tablero', 'Tablero + export Pdf/Excel/Csv'],
                    ['listar-conciliacion-bancaria-aging', 'Aging + forecast + export'],
                    ['listar-conciliacion-bancaria-excepciones', 'Worklist de excepciones (también abre con aging)'],
                    ['editar-configuracion-conciliacion-bancaria', 'Abrir pantalla de reglas y mapas'],
                    ['actualizar-configuracion-conciliacion-bancaria', 'Guardar reglas, mapas y residuales'],
                    ['aprobar-conciliacion-bancaria', 'Botón Aprobar tras el cierre (otro usuario)'],
                    ['alinear-conciliacion-bancaria-excel', 'Alinear Excel Contaduría (dry-run / aplicar)'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Antes de empezar',
                'headers' => ['Chequeo', 'Detalle'],
                'rows' => [
                    ['Cuenta de caja', 'Con cuenta contable; si hay IB, cuenta IB enganchada'],
                    ['Sin IB', 'Importar extracto (Excel/CSV/OFX/CAMT/MT940) → EXT-{código}'],
                    ['Mayor del período', 'Disponible en Anita (bridge)'],
                    ['Período', 'Mes / año a conciliar'],
                    ['Residuales', 'Cuenta o concepto IVA cargados en Configuración → Residuales'],
                    ['Opcional', 'Excel Contaduría del mes para semilla / alineación'],
                ],
            ],
        ],
        [
            'titulo' => '5. Pantalla principal (index)',
            'captura_id' => 'conc_index',
            'herramientas_clave' => 'index',
            'herramientas_incluir_listado' => true,
            'parrafos' => [
                'Ruta: Contable → Conciliación bancaria. Elija empresa, cuenta de caja (código + F1/lupa) y período; pulse Conciliar. Al elegir cuenta se muestra el enganche: IB OK, falta extracto, o hay que importar archivo.',
                'Tras la corrida verá KPI (pares, pendientes, score IA si aplica) y, si hay reglas residuales con match, el panel de residuales clasificados.',
            ],
            'tabla' => [
                'caption' => 'Carátula (resumen del mes)',
                'headers' => ['Concepto', 'Significado'],
                'rows' => [
                    ['Saldo banco (extracto)', 'Último saldo del extracto del período'],
                    ['Cheques no acreditados', 'Cheques propios con F.Dev en el mes'],
                    ['Pendientes banco', 'Movimientos banco sin match que entran a carátula'],
                    ['Saldo banco ajustado', 'Banco ± ajustes de carátula'],
                    ['Saldo contable', 'Del mayor de la cuenta'],
                    ['Diferencia', 'Debe tender a 0 (o a la tolerancia Contaduría)'],
                ],
            ],
            'parrafos2' => [
                'También verá: pares nuevos de la corrida, pendientes cpromae, ANULA, mayor sin match, pendientes banco y residuales a postear.',
            ],
        ],
        [
            'titulo' => '5.1 Solapas del resultado',
            'captura_id' => 'conc_solapas',
            'parrafos' => [
                'Tras la corrida, las solapas detallan el mes para Contaduría.',
            ],
            'tabla' => [
                'caption' => 'Solapas',
                'headers' => ['Solapa', 'Contenido'],
                'rows' => [
                    ['Pendientes cheques', 'Cheques propios (cpromae) + ANULA ya conciliadas en banco'],
                    ['Cruce cheques IB', 'Débitos CH* del extracto vs cheques ERP (código 9)'],
                    ['Pendientes otros', 'Movimientos de mayor sin match (no cheque)'],
                    ['Saldo banco (codificado)', 'Extracto con código Contaduría (2…10 / AC)'],
                    ['Pendientes banco', 'Banco sin match'],
                    ['Gastos bancarios', 'Resumen por código + generación de egreso/ICO'],
                ],
            ],
        ],
        [
            'titulo' => '5.2 Alinear Excel Contaduría',
            'captura_id' => 'conc_alinear',
            'parrafos' => [
                'Con permiso alinear: elija empresa, cuenta y período → Alinear Excel Contaduría → elegir .xlsx / .xls.',
                'Cancelar en el diálogo = solo dry-run (análisis). Aceptar = aplicar (persiste pares / import extracto según el análisis). Sirve para alinear el stock de Contaduría con lo del ERP.',
            ],
            'items' => [
                'Si “no grabó”: revisen si en el diálogo eligieron Cancelar (= dry-run).',
                'Con resultado en pantalla: Export Excel de la conciliación del período (filtros activos).',
            ],
        ],
        [
            'titulo' => '6. Workspace manual',
            'captura_id' => 'conc_manual',
            'herramientas_clave' => 'manual',
            'parrafos' => [
                'Entrada: index → Manual (con empresa, cuenta y período ya elegidos). Dos columnas: mayor (anitaERP) a la izquierda y banco a la derecha.',
            ],
            'tabla' => [
                'caption' => 'Modos (se guardan por empresa)',
                'headers' => ['Modo', 'Enfoque'],
                'rows' => [
                    ['Contaduría', 'Carátula, cheques, cierre formal, vista Contaduría'],
                    ['Tesorería', 'Operación diaria, traer banco destacado, pendientes del día'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Barra de herramientas',
                'headers' => ['Acción', 'Qué hace'],
                'rows' => [
                    ['Fecha / texto / importe', 'Filtran las grillas'],
                    ['Solo pendientes', 'Oculta ya conciliados'],
                    ['Incluir anteriores', 'Trae pendientes de meses previos'],
                    ['Traer del banco', 'Baja IB del día de la fecha filtro'],
                    ['Importar extracto', 'Excel / CSV / OFX / CAMT.053 / MT940 → EXT si no hay IB'],
                    ['Sugerir par (S)', 'Marca un candidato por importe'],
                    ['Conciliar (Enter)', 'Confirma selección (1:1 o multi)'],
                    ['Limpiar (Esc)', 'Saca la selección'],
                    ['Cerrar / Aprobar / Reabrir', 'Workflow del período'],
                    ['Adjuntar evidencia', 'PDF/imagen/Excel de respaldo del cierre o aprobación'],
                    ['Excel', 'Export del workspace'],
                ],
            ],
            'parrafos2' => [
                'Chips de tipo: Todos · Cheques · Transferencias · Depósitos · Gastos / débitos. Bancos sin IB: Importar extracto; el enganche indica EXT-…; “Traer del banco” no aplica.',
            ],
        ],
        [
            'titulo' => '6.1 Multi-match y atajos',
            'captura_id' => 'conc_multi',
            'parrafos' => [
                'La barra de totales vivos muestra suma anitaERP, suma banco y diferencia. En multi-match la diferencia debe quedar dentro de la tolerancia de importe configurada.',
            ],
            'tabla' => [
                'caption' => 'Cómo conciliar a mano',
                'headers' => ['Modo', 'Pasos'],
                'rows' => [
                    ['1:1', 'Clic mayor → clic banco (mismo importe o S) → Conciliar / Enter'],
                    ['Multi', 'Marcar varios de un lado y uno/varios del otro → ver totales → Conciliar (graba grupo)'],
                    ['Desconciliar', 'Desde el detalle del par/grupo ya conciliado'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Atajos de teclado',
                'headers' => ['Tecla', 'Acción'],
                'rows' => [
                    ['Enter', 'Conciliar selección'],
                    ['S', 'Sugerir par'],
                    ['Esc', 'Limpiar selección'],
                ],
            ],
        ],
        [
            'titulo' => '7. Cierre, aprobación y evidencia',
            'captura_id' => 'conc_cierre',
            'parrafos' => [
                'Estados del período (por empresa + cuenta + mes): borrador/abierto → cerrado → aprobado. El cierre registra snapshot; la aprobación es SoD (otro usuario).',
                'Panel Evidencia en el workspace: elija archivos antes de Cerrar/Aprobar (quedan pendientes y se suben juntos) o use Adjuntar si ya hay ejecución. Formatos: PDF, imagen, Excel, CSV, texto.',
            ],
            'tabla' => [
                'caption' => 'Estados',
                'headers' => ['Estado', 'Significado'],
                'rows' => [
                    ['Borrador / abierto', 'Se puede conciliar y traer banco'],
                    ['Cerrado', 'Snapshot + evidencia de cierre; Contaduría revisa'],
                    ['Aprobado', 'Cierre formal aceptado por otro usuario + evidencia de aprobación'],
                ],
            ],
            'items' => [
                'Terminar pendientes relevantes → adjuntar evidencia → Cerrar período → otro usuario con permiso Aprobar.',
                'Si hace falta corregir: Reabrir (vuelve a editable). El lock Interbanking del mes impide operar extracto/IB en período cerrado/aprobado sin reapertura.',
                'El cierre no borra pares ni grupos; deja constancia de quién cerró/aprobó y los adjuntos.',
                'SoD activo: el mismo usuario no puede cerrar y aprobar.',
            ],
        ],
        [
            'titulo' => '8. Tablero de avance',
            'captura_id' => 'conc_tablero',
            'herramientas_clave' => 'tablero',
            'parrafos' => [
                'Contable → Tablero conciliación bancaria. Uso típico: gerencia o Enc-contaduría al cierre de mes, multi-empresa.',
                'Filtros: empresas (checkboxes de asignadas), mes / año → Consultar. Por cuenta: extracto sí/no, pares del mes, grupos multi, hashes C/B, estado (sin corrida / borrador / cerrado / aprobado). Accesos a Manual y Aging. Export Pdf / Excel / Csv.',
            ],
        ],
        [
            'titulo' => '9. Aging y forecast',
            'captura_id' => 'conc_aging',
            'herramientas_clave' => 'aging',
            'parrafos' => [
                'Contable → Aging conciliación bancaria. Empresa + cuenta de caja (F1) + período → Consultar.',
                'Buckets: 0–7, 8–30, 31–60, 61+ días; total e |importe|. Detalle por lado (contable / banco), fecha, días, bucket, detalle, importe. Export Pdf / Excel / Csv.',
                'Forecast: encima del detalle, proyección de importe pendiente por ventana (misma lógica de buckets). Sirve para anticipar liquidez / limpieza de excepciones.',
                'Enlace a Worklist: sincroniza las excepciones del aging a la cola operativa.',
            ],
        ],
        [
            'titulo' => '10. Worklist de excepciones',
            'captura_id' => 'conc_worklist',
            'herramientas_clave' => 'worklist',
            'parrafos' => [
                'Contable → Worklist excepciones bancarias (también atajo Worklist desde index/aging).',
                'Al consultar con empresa + cuenta + período, sincroniza desde aging (crea/actualiza) y lista abiertas / en curso / resueltas / descartadas.',
                'Asigne un usuario operativo, marque resuelta o descarte con nota. Es la cola formal de lo que frena la diferencia de carátula.',
            ],
            'tabla' => [
                'caption' => 'Estados de excepción',
                'headers' => ['Estado', 'Uso'],
                'rows' => [
                    ['Abierta', 'Entró desde aging; sin dueño'],
                    ['En curso', 'Asignada a un usuario'],
                    ['Resuelta', 'Cerrada con resolución (manual / match / etc.)'],
                    ['Descartada', 'No aplica / no se trabaja'],
                ],
            ],
        ],
        [
            'titulo' => '11. Gastos bancarios y residuales',
            'captura_id' => 'conc_gastos',
            'parrafos' => [
                'En el index, solapa Gastos bancarios: agrupa débitos del extracto por código de codificación (2…8; no 9 ni 10). Con la configuración de Doc. gastos resuelve cuenta contable o concepto IVA compras. Si está “listo”, se genera el documento (EGR + ICO según abreviaturas de reglas).',
                'Residuales: movimientos de extracto sin par que matchean reglas (interés, fee, FX, …). El index muestra los clasificados; para postear hay que completar cuenta o concepto IVA en Configuración → Residuales.',
                'Si falta cuenta o concepto (gastos o residuales), el panel lista los faltantes: corregir en Configuración.',
            ],
        ],
        [
            'titulo' => '12. Configuración (sin intervención de sistemas)',
            'captura_id' => 'conc_config',
            'herramientas_clave' => 'config',
            'parrafos' => [
                'Configuración → Configuración por módulo → Contable → Conciliación bancaria (reglas), o atajo Reglas desde el index. Un solo Guardar aplica todas las solapas.',
            ],
            'tabla' => [
                'caption' => 'Solapas de configuración',
                'headers' => ['Solapa', 'Qué edita'],
                'rows' => [
                    ['Reglas', 'Tolerancias, lookback, multi-match auto, EGR/ICO, SoD, códigos default'],
                    ['P.C.C.', 'grouping_code_ib → código Contaduría (patrones | opcionales; AC = acumulado)'],
                    ['Conceptos IB', 'code_description_ib → código; marca Acumulado'],
                    ['Cód. operación', 'operation_code_ib → código (respaldo)'],
                    ['Patrones gasto', 'Capa final por texto + etiquetas de código 2…10'],
                    ['Tipo comp.', 'Compatibilidad OPP/OPA/TRF/… vs patrones extracto'],
                    ['Doc. gastos', 'Por código 2…8: concepto IVA o cuenta contable'],
                    ['Residuales', 'Clave, patrones, D/H, cuenta/concepto, tipo doc., activo'],
                ],
            ],
        ],
        [
            'titulo' => '12.1 Doc. gastos y residuales (lupa)',
            'captura_id' => 'conc_doc_gasto',
            'parrafos' => [
                'Por cada código de gasto (2…8): Concepto IVA compras (manda si está) o Cuenta contable (código del plan). Lupa / F1 para buscar cuenta y concepto. Los tipos EGR/ICO están en Reglas.',
                'En Residuales: misma lupa para cuenta/concepto. Patrones separados por |. D = débito / H = crédito del texto del banco. Tipo doc. típico EGR o ASI.',
            ],
            'tabla' => [
                'caption' => 'Códigos Contaduría (referencia; editables en Patrones gasto)',
                'headers' => ['Código', 'Uso habitual'],
                'rows' => [
                    ['2', 'Impuesto al débito/crédito'],
                    ['3', 'IIBB retención (SIRCREB / AGIP)'],
                    ['4', 'IIBB percepción (PBS/BSAS)'],
                    ['5', 'IVA 21 %'],
                    ['6', 'IVA percepción 3ros'],
                    ['7', 'Comisiones / intereses / sellos'],
                    ['8', 'Retención IVA'],
                    ['9', 'Cheques'],
                    ['10', 'Transferencias / créditos / depósitos'],
                    ['AC', 'Acumulado diario (no entra al egreso de gastos)'],
                ],
            ],
        ],
        [
            'titulo' => '13. Corrida automática, clearing y fuentes',
            'parrafos' => [
                'Orden típico del matcher: (1) overrides del Excel Contaduría (solapa Saldo) si hubo alineación; (2) emparejamientos 1:1 por referencia / cheque / importe+fecha; (3) clearing de documentos (OP, cobranza, IE) cuando el movimiento IB corresponde; (4) si multi-match automático está activo: grupos por suma de importes en ventana de días; (5) residuales clasificados por regla; (6) lo no matcheado queda pendiente / worklist.',
                'Los resúmenes IB de cierre (sistema V/C/T) no se duplican cuando ya hay detalle de subdiario del mes.',
                'Extracto versionado: cada import queda registrado (hash) para no duplicar el mismo archivo.',
            ],
            'tabla' => [
                'caption' => 'Fuentes de datos',
                'headers' => ['Lado', 'Fuente'],
                'rows' => [
                    ['Contable', 'Mayor Anita: subdiario + ctamov'],
                    ['Banco', 'interbanking_movimiento o extracto EXT-… (Excel/CSV/OFX/CAMT/MT940)'],
                    ['Cheques propios', 'cpromae (+ ANULA del mayor ya conciliadas en banco)'],
                    ['Pares / grupos', 'Tablas de conciliación del ERP (persisten)'],
                    ['Clearing', 'Vínculo OP / cobranza / IE ↔ movimiento IB'],
                    ['Evidencia', 'Adjuntos de la ejecución (cierre / aprobación)'],
                ],
            ],
        ],
        [
            'titulo' => '14. Problemas frecuentes y exportaciones',
            'parrafos' => [
                'Si algo no cierra, use esta tabla antes de pedir intervención de sistemas.',
            ],
            'tabla' => [
                'caption' => 'Síntoma → qué mirar',
                'headers' => ['Síntoma', 'Qué mirar'],
                'rows' => [
                    ['Enganche “sin extracto”', 'Traer IB o Importar extracto (Excel/CSV/OFX/CAMT/MT940)'],
                    ['Diferencia carátula ≠ 0', 'Aging + Worklist + Manual; cheques F.Dev; residuales'],
                    ['Cheque en banco y no en ERP', 'Solapa Cruce cheques IB → FALTAN'],
                    ['Gasto / residual sin documento', 'Doc. gastos o Residuales: falta cuenta o concepto'],
                    ['Multi no propone', 'Reglas: multi-match activo, tolerancia, ventana'],
                    ['No deja conciliar', 'Período cerrado/aprobado o lock → Reabrir'],
                    ['No deja aprobar', 'SoD: otro usuario; o falta permiso aprobar'],
                    ['Alinear Excel no graba', 'En el diálogo eligieron Cancelar (= dry-run)'],
                    ['Código de gasto “raro”', 'P.C.C. / Conceptos / Patrones en Configuración'],
                    ['Excepción sin dueño', 'Worklist → asignar usuario operativo'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Exportaciones',
                'headers' => ['Pantalla', 'Formatos'],
                'rows' => [
                    ['Index (con resultado)', 'Excel conciliación'],
                    ['Manual', 'Excel del workspace'],
                    ['Aging', 'Pdf, Excel, Csv'],
                    ['Tablero', 'Pdf, Excel, Csv'],
                    ['Evidencia', 'Descarga individual del adjunto'],
                ],
            ],
            'items' => [
                'Open banking / feed PSD2 no está en alcance: el banco entra por Interbanking y/o archivo (incl. CAMT.053 y MT940).',
                'El alta del plan de cuentas o conceptos IVA vive en sus pantallas; acá solo se consultan desde Doc. gastos / Residuales.',
                'Cambios de negocio (tolerancias, mapas, residuales, cuentas de gasto): Contaduría en Configuración. Bridge Anita / IB / permisos: Enc-sistemas.',
            ],
        ],
    ],
];
