<?php

/**
 * Manual de usuario — Módulo Caja / tesorería.
 * Audiencia: operadores de caja, tesorería y supervisores sin experiencia técnica.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Caja, posición financiera, Flash, máquinas y bingo',
    'version' => '1.1',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Introducción — Caja y tesorería',
            'parrafos' => [
                'El módulo Caja de Anita ERP concentra la operación diaria de tesorería: registro de ingresos y egresos, rendiciones de sala (máquinas, bingo, gastronomía, estacionamiento, vending), el Flash gerencial del día y la Posición financiera mensual que consolida todos los movimientos.',
                'Este manual está pensado para el operador de caja y el supervisor de tesorería. Describe qué pantalla usar en cada momento del día, de dónde salen los números y cómo se relacionan con el cierre contable.',
                'Menú principal: Módulo Caja. Las acciones visibles dependen de los permisos asignados a su usuario y de la empresa seleccionada.',
            ],
            'items' => [
                'Caja opera sobre datos del ERP y, cuando corresponde, de Wigos (SQL Server de sala) y del bridge Anita legacy.',
                'El Flash resume el día; la Posición financiera resume el mes.',
                'Las rendiciones de máquinas alimentan impuestos y totales que el Flash consume en el turno C (cierre).',
                'Gastronomía, estacionamiento y vending tienen manuales propios; aquí se resume su vínculo con Caja.',
            ],
        ],
        [
            'titulo' => '2. Mapa de módulos',
            'captura_id' => 'mapa_caja',
            'parrafos' => [
                'La siguiente tabla resume las pantallas más usadas del área Caja y su rol en el circuito diario.',
            ],
            'tabla' => [
                'caption' => 'Pantallas principales — Módulo Caja',
                'headers' => ['Pantalla', 'Ruta', 'Para qué sirve'],
                'rows' => [
                    ['Cuentas de caja', 'caja/cuentacaja', 'Maestro de medios (efectivo, banco, QR, etc.) por empresa.'],
                    ['Ingresos / egresos', 'caja/ingreso, caja/egreso', 'Movimientos manuales de tesorería.'],
                    ['Remesas', 'caja/remesa', 'Envío de efectivo a banco / destinos (Macro, Provincia, etc.).'],
                    ['Rendición máquinas', 'caja/rendicion-maquina', 'Cierre parcial M/T/N y cierre C de sala Wigos.'],
                    ['Flash', 'caja/flash', 'Resumen gerencial diario: slots, ruletas, AyB, estac, vending, bingo.'],
                    ['Posición financiera', 'caja/posicion-financiera', 'Informe mensual tipo l-posfinanc.c con columnas por día.'],
                    ['Bingo — terminal', 'caja/bingo/rendicion/cargar', 'Carga operativa de cartones y premios por turno.'],
                    ['Bingo — presentación caja', 'caja/rendicionbingo', 'Presentación tesorería del cierre bingo.'],
                    ['Rendiciones vending', 'caja/rendicionmaquinavending', 'Presentación caja de cierres vending (Ventas).'],
                    ['Parámetros Flash', 'caja/flash/parametro', 'Metas y parámetros por empresa/período.'],
                ],
            ],
            'parrafos2' => [
                'Contable ejecuta los cierres de rendiciones (máquinas, bingo, gastro, estacionamiento, vending) desde su propio menú; Caja registra la operación y alimenta esos procesos.',
            ],
        ],
        [
            'titulo' => '3. Flujo de datos diario',
            'captura_id' => 'flujo_datos',
            'parrafos' => [
                'El circuito estándar de un día operativo en casino/sala sigue este orden. Respetarlo evita diferencias entre Wigos, Flash, posición financiera y contabilidad.',
            ],
            'items' => [
                '1) Durante el día: Wigos registra drops, tickets, sesiones y QR. Gastronomía/estacionamiento/vending facturan en sus módulos.',
                '2) Por turno: se cargan rendiciones de máquinas M, T y N (parciales). Al cierre del día, turno C con impuestos y totales completos.',
                '3) Bingo: cierres de turno en terminal → presentación en caja/rendicionbingo.',
                '4) Flash: calcular desde ERP/Wigos para la fecha y empresa; revisar origen de totales si hay diferencia.',
                '5) Posición financiera: al cierre del mes, consultar el informe y confirmar saldo si el período ya finalizó.',
                '6) Contable: cierre de rendiciones (máquinas, bingo, etc.) genera asientos; conciliación Flash disponible en Contable.',
            ],
            'tabla' => [
                'caption' => 'Relación entre sistemas',
                'headers' => ['Etapa', 'Sistema', 'Salida'],
                'rows' => [
                    ['Operación sala', 'Wigos', 'Drops, tickets, sesiones, coin-in, win OL'],
                    ['Rendición parcial', 'ERP caja/rendicion-maquina', 'rendmaquina + valormae por turno M/T/N'],
                    ['Cierre día máquinas', 'ERP turno C', 'Impuesto drop/venta para Flash slot_d / slot_r'],
                    ['Resumen gerencial', 'ERP caja/flash', 'Totales diarios por bloque de negocio'],
                    ['Control mensual', 'ERP posicion-financiera', 'Grilla por día + saldo inicial/final'],
                    ['Asientos', 'Contable cierre rendiciones', 'Mayor Anita / ERP contable'],
                ],
            ],
        ],
        [
            'titulo' => '4. Posición financiera',
            'captura_id' => 'posicion_financiera',
            'herramientas_grupos' => [
                ['titulo' => 'Herramientas de la pantalla', 'clave' => 'posicion_financiera'],
            ],
            'parrafos' => [
                'Pantalla: Caja → Posición financiera (caja/posicion-financiera). Es el informe mensual de tesorería, port del programa legacy l-posfinanc.c implementado en EfePosicionFinancieraSupport.',
                'Seleccione empresa y mes/año, luego pulse Consultar. La grilla muestra una columna por cada día del mes más la columna Total mensual.',
                'Los datos provienen del bridge Anita (rendbingo, rendmaquina, rendgastro, rendvalor, valormae, rememae, saldoposf) complementado con remesas ERP y rendiciones de máquinas ERP cuando corresponde.',
            ],
            'tabla' => [
                'caption' => 'Bloques del informe (orden de impresión)',
                'headers' => ['Bloque', 'Contenido típico'],
                'rows' => [
                    ['Bingo', 'Recaudación, premios, cartones, conceptos rendbingo/concbingo'],
                    ['Gastronomía', 'Totales por sucursal/PV de rendgastro + rendvalor gastronomía'],
                    ['Estacionamiento', 'Totales de jornadas estacionamiento (signo invertido vs gastro)'],
                    ['Vending', 'Rendiciones maquinavending ERP (bloque agregado respecto al .c legacy)'],
                    ['Máquinas', 'Drop, win, soft/hard count, impuestos — excluye turnos M/T/N sueltos post-marzo/2010'],
                    ['Medios', 'Apertura por tipo valormae: efectivo pesos/dólar/euro, bancos, cripto'],
                    ['Egresos', 'Pagos, remesas, gastos de rendición'],
                    ['Saldos', 'Saldo inicial, movimientos del mes, saldo final calculado'],
                ],
            ],
            'items' => [
                'Saldo inicial: toma el último saldo confirmado en ERP (PosicionFinancieraSaldoSupport); si no existe, el último saldoposf Anita anterior al mes.',
                'Saldo final: calculado por el support a partir de ingresos, egresos y bloques del mes.',
                'Confirmar saldo: solo disponible si el mes ya finalizó y tiene permiso confirmar-saldo-posicion-financiera. Graba saldo inicial/final oficial para encadenar meses.',
                'Diferencia vs EFE contable: el EFE completo vive en Contable; tesorería usa solo esta solapa. Compare totales con Contable → EFE si hay desvío.',
            ],
            'parrafos2' => [
                'Si aparecen errores_bridge en pantalla, el bridge Anita no respondió para alguna tabla; el informe puede venir incompleto. Reintente o contacte soporte antes de confirmar saldo.',
            ],
        ],
        [
            'titulo' => '5. Flash de caja',
            'captura_id' => 'flash_form',
            'herramientas_grupos' => [
                ['titulo' => 'Listado', 'clave' => 'flash_listado', 'incluir_listado' => true],
                ['titulo' => 'Formulario diario', 'clave' => 'flash_form'],
            ],
            'parrafos' => [
                'Pantalla: Caja → Flash (caja/flash). Un registro por empresa y fecha de working day. Resume la performance del día para gerencia y alimenta conciliaciones contables.',
                'A las 14:30 el cron flash:calcular-diario arma el cálculo (Wigos + ERP) de las tres empresas sobre la jornada de ayer (siempre jornada cerrada). Si un usuario ya cargó esa fecha en el ABM, esa empresa se omite y no se pisa.',
                'Antes de grabar a mano conviene usar Calcular desde ERP/Wigos para alinear todos los campos con las fuentes actuales.',
                'Los parámetros Flash (caja/flash/parametro) definen metas y días del período; no modifican fórmulas de cálculo.',
            ],
            'tabla' => [
                'caption' => 'Bloques principales del formulario',
                'headers' => ['Bloque', 'Campos destacados', 'Origen'],
                'rows' => [
                    ['Slots', 'slot_d, slot_r, soft_count, hard_count, coin-in, win OL, cantidad', 'Wigos + rendición turno C'],
                    ['Ruletas', 'rul_d, rul_r, soft_rul, hard_rul, coin-in, win OL', 'Wigos turno M (+ pagos en win)'],
                    ['AyB', 'ayb', 'Neto facturación gastronomía ERP'],
                    ['Estacionamiento', 'estac, cant_vehic', 'Jornadas estacionamiento cerradas ERP'],
                    ['Vending', 'vending', 'Rendiciones maquinavending ERP'],
                    ['Bingo', 'bingo_cant_carton, bingo_total_venta, bingo_resultado', 'Rendiciones/presentaciones bingo ERP'],
                ],
            ],
        ],
        [
            'titulo' => '6. Flash — origen de cada total',
            'captura_id' => 'flash_origen',
            'parrafos' => [
                'Cada total numérico del Flash tiene un botón de origen que llama a la API flash_caja_api_origen_total. El backend usa FlashCajaOrigenTotalSupport para armar la explicación.',
                'El modal muestra: título del campo, fórmula, cuenta (suma algebraica de componentes), total recalculado y secciones de detalle.',
            ],
            'tabla' => [
                'caption' => 'Origen de totales clave (FlashCajaOrigenTotalSupport)',
                'headers' => ['Campo', 'Fórmula resumida', 'Fuente'],
                'rows' => [
                    ['slot_d', 'Bill + ventas tickets + ventas caja + QR neto − impuestos turno C', 'Wigos M + rendmaquina C'],
                    ['slot_r', 'slot_d − pagos tickets − pagos manuales (M+T+N) − impuestos', 'Wigos + rendición'],
                    ['soft_count', 'Bill slots − Bill poker', 'spDropDiarioPorTerminal turno M'],
                    ['hard_count', 'Tito slots (pagos tickets sesión M+T+N) − poker', 'spGananciaDeSalaPorSesion'],
                    ['rul_d / rul_r', 'Bill ruletas + ventas − pagos ruletas', 'Wigos tickets TerminalType=2'],
                    ['ayb', 'Facturas − NC gastronomía del día', 'ERP Ventas'],
                    ['estac', 'Σ neto jornadas estacionamiento', 'ERP estacionamiento'],
                    ['vending', 'Σ total_ventas rendiciones vending', 'ERP Ventas/Caja'],
                    ['bingo_*', 'Totales bingo del día', 'ERP rendición bingo'],
                ],
            ],
            'items' => [
                'Si el valor en pantalla difiere del cálculo actual, el modal avisa: use Calcular desde ERP/Wigos antes de confiar en un flash importado de Anita legacy. «Origen actual» no implica necesariamente el valor que se grabó al importar Anita.',
                'Desglose Wigos Excel: exporta movimientos del working day para auditoría detallada (SP drop, tickets, QR, sesiones, win EGM).',
                'Reporte histórico / Consolidated Income: Net Revenues = gaming (win OL + bingo) + AyB + estacionamiento (+ show si aplica). Vending se muestra en el Flash pero no suma a Net Revenues (compatibilidad con l-flash.c / Anita).',
                'Reporte histórico: serie temporal de totales entre fechas; útil para comparar semanas o detectar saltos.',
            ],
        ],
        [
            'titulo' => '7. Rendición de máquinas',
            'captura_id' => 'rendicion_maquina',
            'herramientas_grupos' => [
                ['titulo' => 'Herramientas', 'clave' => 'rendicion_maquina', 'incluir_listado' => true],
            ],
            'parrafos' => [
                'Pantalla: Caja → Rendición de máquinas (caja/rendicion-maquina). Registra el cierre de sala por turno y sincroniza con Anita rendmaquina.',
                'Turnos: M (mañana), T (tarde), N (noche) son parciales del working day; C (completo) es el cierre del día e incluye impuestos que el Flash resta en slot_d y slot_r.',
            ],
            'tabla' => [
                'caption' => 'Turnos y modo Wigos',
                'headers' => ['Turno', 'Uso operativo', 'Wigos'],
                'rows' => [
                    ['M', 'Primer corte del día; baseline drop y tickets', 'Parcial — drop D-1 según config'],
                    ['T', 'Corte tarde', 'Parcial'],
                    ['N', 'Corte noche', 'Parcial'],
                    ['C', 'Cierre financiero del día; impuestos drop/venta', 'Modo cierre — drop real del día'],
                ],
            ],
            'items' => [
                'Traer Wigos: completa la grilla de valores desde calc_datos_wigos; turno C fuerza OL=M pero drop real.',
                'Valores: cada línea mapea a valormae Anita; los gastos van en rendmapgasto vía grilla de gastos.',
                'Relación Flash: impuesto_drop e impuesto_venta del turno C alimentan slot_d y slot_r; pagos manuales M+T+N restan en slot_r.',
                'Cierre contable: Contable → Cierre rendiciones máquinas genera asiento; conciliación Flash compara ERP vs flash del día.',
            ],
        ],
        [
            'titulo' => '8. Bingo',
            'captura_id' => 'bingo_rendicion',
            'herramientas_grupos' => [
                ['titulo' => 'Terminal operativo', 'clave' => 'bingo_terminal'],
                ['titulo' => 'Presentación caja', 'clave' => 'bingo_presentacion', 'incluir_listado' => true],
            ],
            'parrafos' => [
                'El bingo en Caja tiene dos capas: operación en terminal (cartones, premios, turnos) y presentación en tesorería (caja/rendicionbingo) que impacta medios de cobro y posición financiera.',
                'Configuración previa: cartones (caja/bingo/carton), conceptos rendición (caja/bingo/concepto-rendicion), jornada, turnos maestros y PV bingo.',
            ],
            'tabla' => [
                'caption' => 'Cálculo de premios y pozo acumulado',
                'headers' => ['Concepto', 'Descripción'],
                'rows' => [
                    ['Conceptos rendición', 'Cada línea tiene código concbingo; algunos acumulan pozo, otros pagan premio fijo.'],
                    ['Recaudación del día', 'Suma de cartones vendidos menos devoluciones según turnos rendidos.'],
                    ['Pozo acumulado (SI)', 'BingoPozoAcumuladoSupport: evoluciona desde semilla del día anterior + reglas concbingo.'],
                    ['Cierre contable', 'Al ejecutar cierre bingo en Contable se registra el SI del día en bingo_pozo_acumulado.'],
                    ['Presentación caja', 'Distribuye el total del turno en cuentas de caja; sync Anita rendbingo.'],
                ],
            ],
            'items' => [
                'Semilla pozo: último importe confirmado con fecha anterior; si no hay, usa config bingo.cierre_rendicion_contable.pozo_acumulado_semilla_por_empresa.',
                'Anular cierre contable borra pozos desde esa fecha (excepto semillas Anita importadas).',
                'Los totales bingo del Flash (bingo_cant_carton, bingo_total_venta, bingo_resultado) leen rendiciones ERP del día.',
            ],
            'parrafos2' => [
                'Captura bingo_pozo: consulte el pozo acumulado en el detalle de cierre contable bingo o en reportes de conciliación Flash cuando esté habilitado.',
            ],
        ],
        [
            'titulo' => '9. Otras rendiciones — gastronomía, estacionamiento, vending',
            'parrafos' => [
                'Estos negocios se operan principalmente en Ventas pero terminan en Caja como presentación o totales en Flash/posición financiera.',
            ],
            'tabla' => [
                'caption' => 'Resumen y manuales de referencia',
                'headers' => ['Negocio', 'Operación', 'Presentación Caja', 'Manual'],
                'rows' => [
                    ['Gastronomía', 'Jornada, turnos, facturación POS, cierres', 'Rendición gastro / informe Z jornada', 'docs/manual-gastronomia'],
                    ['Estacionamiento', 'Jornada, turnos, facturación', 'Rendición estacionamiento', 'Manual gastronomía (módulo estacionamiento)'],
                    ['Vending', 'Rendición X en Ventas', 'caja/rendicionmaquinavending', 'docs/manual-vending'],
                ],
            ],
            'items' => [
                'Flash ayb = neto gastronomía ERP del día (facturas − NC).',
                'Flash estac / cant_vehic = jornadas estacionamiento cerradas.',
                'Flash vending = Σ total_ventas rendiciones maquinavending.',
                'Posición financiera incluye bloques gastro, estac y vending con la misma lógica que l-posfinanc.c (+ vending ERP).',
            ],
        ],
        [
            'titulo' => '10. Permisos principales',
            'tabla' => [
                'caption' => 'Permisos frecuentes — Caja',
                'headers' => ['Permiso', 'Pantalla'],
                'rows' => [
                    ['listar-posicion-financiera', 'Posición financiera — consulta y export'],
                    ['confirmar-saldo-posicion-financiera', 'Posición financiera — confirmar/anular saldo'],
                    ['listar-flash-caja / crear-flash-caja / editar-flash-caja', 'Flash — listado y ABM'],
                    ['listar-flash-parametro / crear-flash-parametro', 'Parámetros Flash'],
                    ['listar-rendicion-maquina / crear-rendicion-maquina', 'Rendición máquinas'],
                    ['actualizar-rendicion-maquina / borrar-rendicion-maquina', 'Rendición máquinas — edición/baja'],
                    ['listar-rendicion-bingo-caja / crear-rendicion-bingo-caja', 'Presentación bingo en caja'],
                    ['listar-rendicion-maquinavending-caja', 'Presentación vending en caja'],
                    ['listar-cuentacaja / crear-cuentacaja', 'Maestro cuentas de caja'],
                ],
            ],
            'parrafos' => [
                'Los cierres contables (asientos) requieren permisos del módulo Contable, no de Caja. Un operador de caja puede cargar rendiciones sin poder ejecutar el cierre contable.',
            ],
        ],
        [
            'titulo' => '11. Checklist diario de tesorería',
            'items' => [
                'Verificar que todas las rendiciones de máquinas del día estén cargadas (M, T, N y C).',
                'Confirmar presentaciones bingo / gastro / estacionamiento / vending pendientes.',
                'Calcular y grabar el Flash del día; revisar origen de slot_d y slot_r si hay diferencia con Wigos.',
                'Conciliar totales Flash vs rendiciones antes del cierre contable (Contable → conciliación Flash).',
                'Al fin de mes: consultar Posición financiera, comparar saldo final con arqueo y confirmar saldo si el mes cerró.',
                'Registrar remesas e ingresos/egresos manuales del día.',
            ],
        ],
        [
            'titulo' => '12. Preguntas frecuentes (FAQ)',
            'tabla' => [
                'caption' => 'FAQ operativo',
                'headers' => ['Pregunta', 'Respuesta'],
                'rows' => [
                    ['El Flash no coincide con Wigos', 'Pulse Calcular desde ERP/Wigos. Abra origen de total en slot_d/slot_r. Verifique turno C cargado con impuestos.'],
                    ['Posición financiera con errores_bridge', 'Falló lectura Anita. Reintente; si persiste, soporte debe revisar bridge. No confirme saldo incompleto.'],
                    ['Saldo inicial distinto al mes anterior', 'Confirme que el mes previo tenga saldo confirmado en ERP; si no, tomará saldoposf Anita.'],
                    ['¿Puedo editar un flash importado de Anita?', 'Sí, pero origen de total avisará diferencia. Prefiera recalcular para alinear con ERP/Wigos.'],
                    ['Turno C sin rendición', 'slot_d/slot_r quedarán sin impuestos; Flash sobreestimará. Cargue turno C antes del flash.'],
                    ['Bingo: pozo incorrecto', 'Verifique cierres contables bingo; BingoPozoAcumuladoSupport recalcula desde semilla. Anular cierre y re-ejecutar si hubo error.'],
                    ['¿Dónde está el EFE completo?', 'Contable → EFE mensual. Posición financiera es solo la solapa tesorería (l-posfinanc.c).'],
                ],
            ],
        ],
    ],
];
