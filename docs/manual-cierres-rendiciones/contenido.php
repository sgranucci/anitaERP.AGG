<?php

/**
 * Manual de usuario — Contaduría: cierres de rendiciones y asientos.
 * Audiencia: contaduría / tesorería sin experiencia técnica.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Contaduría: cierres de rendiciones y asientos',
    'version' => '1.1',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Introducción',
            'parrafos' => [
                'Este manual describe el trabajo de Contaduría sobre las rendiciones que Caja ya presentó y confirmó. En Caja el operador cierra turnos, arma la rendición y la presenta; en Contable usted convierte ese resultado operativo en asientos contables, los valida contra Flash y los envía a Anita cuando corresponde (FSL máquinas, FBI bingo, etc.).',
                'Las pantallas viven bajo Módulo Contable → Cierres de rendiciones (máquinas, bingo, estacionamiento, vending) y, donde aplique, Cierres turno gastronomía. Cada submódulo tiene listado agrupado, preview de asiento, ejecución, anulación y herramientas de conciliación.',
                'Antes de ejecutar un cierre controle que el período no esté cerrado para el módulo Caja/Contable, que las cuentas automáticas estén completas y que el preview cuadre debe = haber.',
            ],
            'items' => [
                'Caja presenta; Contaduría cierra contablemente (no reemplaza la rendición operativa).',
                'El preview es obligatorio antes del primer cierre de un día nuevo o ante diferencias.',
                'La anulación revierte asiento y marca; no borra la rendición de Caja.',
                'Flash es la fuente externa de ventas online para conciliar antes de cerrar máquinas.',
            ],
        ],
        [
            'titulo' => '2. Circuito general',
            'captura_id' => 'flujo_cierre_rendicion',
            'parrafos' => [
                'El flujo es siempre el mismo salvo matices por rubro (máquinas exigen turno C; bingo agrupa por sala; estacionamiento/vending por jornada).',
            ],
            'tabla' => [
                'caption' => 'De la operación Caja al asiento Anita',
                'headers' => ['Paso', 'Dónde', 'Quién', 'Resultado'],
                'rows' => [
                    ['1. Operación', 'Salón / POS / caja física', 'Caja', 'Turnos, arqueos, medios de cobro'],
                    ['2. Rendición', 'Caja → Rendición máquinas/bingo/etc.', 'Caja / Tesorería', 'Rendición confirmada con valores y gastos'],
                    ['3. Presentación', 'Estado confirmada en ERP', 'Caja', 'Documento listo para cierre contable'],
                    ['4. Agrupación', 'Contable → Cierre rendiciones', 'Contaduría', 'Grupo empresa + fecha (o jornada)'],
                    ['5. Preview', 'Modal preview asiento', 'Contaduría', 'Asiento propuesto sin grabar'],
                    ['6. Ejecución', 'Ejecutar cierre', 'Contaduría', 'Asiento(s) ERP + marca cierre_contable en rendiciones'],
                    ['7. Anita', 'Bridge FSL / FBI / etc.', 'Sistema', 'Comprobante en Anita según PV configurado'],
                    ['8. Control', 'Conciliación Flash / diario PV', 'Contaduría', 'Validación rendición vs Flash u otros informes'],
                ],
            ],
            'items' => [
                'Si el paso 6 falla por período cerrado, solicite apertura programada o espere la apertura del módulo Caja.',
                'El paso 7 puede omitirse en entornos sin bridge Anita; el asiento ERP igual queda registrado.',
            ],
        ],
        [
            'titulo' => '3. Cierre máquinas: listado',
            'captura_id' => 'cierre_maquina_listado',
            'herramientas_grupos' => [
                ['titulo' => 'Filtros y listado', 'clave' => 'maquina_listado'],
                ['titulo' => 'Acciones por grupo (día)', 'clave' => 'maquina_grupo_acciones'],
            ],
            'parrafos' => [
                'Ruta: contable/cierre-rendiciones-maquina. Menú: Contable → Cierres de rendiciones → Máquinas.',
                'El listado no muestra rendición por rendición suelta: agrupa por empresa y fecha del día, incluyendo solo rendiciones con turno C (cierre diario). Los turnos parciales A/B no entran al cierre contable.',
                'Cada fila indica cuántas rendiciones componen el grupo, si ya tienen asiento, el PV FSL configurado y el estado: pendiente (ninguna cerrada), parcial (algunas cerradas — revisar) o cerrada (todas con cierre contable).',
            ],
            'tabla' => [
                'caption' => 'Estados del grupo diario',
                'headers' => ['Estado', 'Significado', 'Acción recomendada'],
                'rows' => [
                    ['Pendiente', 'Hay rendiciones turno C confirmadas sin asiento', 'Preview → ejecutar cierre'],
                    ['Parcial', 'Mezcla cerradas y abiertas (anomalía)', 'Investigar antes de ejecutar; puede faltar anular o completar'],
                    ['Cerrada', 'Todas con asiento y fecha de cierre contable', 'Solo consulta o anular si hubo error (con permiso)'],
                ],
            ],
            'items' => [
                'Correlatividad: el sistema valida que no queden días anteriores pendientes antes de cerrar un rango (mensaje al ejecutar rango).',
                'Use export PDF/Excel para auditoría del detalle de rendiciones filtradas.',
            ],
        ],
        [
            'titulo' => '4. Cierre máquinas: preview y ejecución',
            'captura_id' => 'preview_asiento_maquina',
            'herramientas_grupos' => [
                ['titulo' => 'Modal de preview', 'clave' => 'maquina_preview'],
            ],
            'parrafos' => [
                'Preview asiento abre un modal con uno o más bloques: «Venta máquinas» (principal), «Pago diferido», «Canon lotería y casinos» y «Canon ent. de bien publico». Cada bloque es un asiento que debe cuadrar por separado y en conjunto.',
                'Ejecutar cierre usa exactamente la misma lógica que el preview. Si hay advertencia de descuadre mayor a 0,02, no confirme: revise rendiciones, Flash del día o cuentas automáticas faltantes.',
                'Tras ejecutar, las rendiciones quedan con asiento_id, cierre_contable_en y usuario; el enlace al asiento aparece en el listado. El monto FSL (maquinas_online + ruletas_online desde Flash) se envía a Anita con el punto de venta configurado por empresa.',
            ],
            'items' => [
                'Ejecutar rango: cierra varios días pendientes del filtro; conviene preview día a día la primera vez.',
                'Anular cierre: elimina el asiento generado y limpia la marca en rendiciones; requiere período contable abierto.',
                'Si falta una cuenta automática, el sistema lista cuáles faltan (Caja pesos, Tarjetas, etc.) — complete en Configuración → Cuentas automáticas.',
            ],
        ],
        [
            'titulo' => '5. Origen de cuentas del asiento máquinas',
            'captura_id' => 'matriz_cuentas_maquina',
            'parrafos' => [
                'Las cuentas se resuelven vía CierreRendicionMaquinaConfigSupport (claves cierre_maquina.* en cuentas automáticas por empresa). Los importes provienen de CierreRendicionMaquinaTotalesSupport, que suma rendiciones turno C del día y lee Flash para ventas online.',
                'La tabla siguiente es la referencia operativa origen → cuenta. Los códigos Anita legacy (p-vtamaquina.c) se replican en CierreRendicionMaquinaAsientoSupport.',
            ],
            'tabla' => [
                'caption' => 'Matriz origen de datos → cuenta (asiento «Venta máquinas»)',
                'headers' => ['Origen / total', 'Campo o fuente', 'Cuenta automática (slot)'],
                'rows' => [
                    ['Caja pesos', 'Valores rendición — efectivo pesos', 'cierre_maquina.caja_pesos'],
                    ['Tarjetas', 'Valores Visa/Master/Electron/Maestro', 'cierre_maquina.tarjetas'],
                    ['MEP', 'Valores con texto MEP', 'cierre_maquina.mep'],
                    ['Dólares', 'Moneda extranjera USD en pesos', 'cierre_maquina.dolares'],
                    ['Euros', 'Moneda EUR en pesos', 'cierre_maquina.euros'],
                    ['Cripto', 'Valores cripto/bitcoin en pesos', 'cierre_maquina.cripto'],
                    ['Totalcoin', 'Códigos valormae 25, 76, 100 o nombre totalcoin', 'cierre_maquina.totalcoin (+ refuerzo caja pesos)'],
                    ['Impuesto esp.', 'Suma impuesto_drop + venta + qr', 'cierre_maquina.impuesto_esp'],
                    ['Gastos / vales', 'Vales + reintegros de rendición', 'cierre_maquina.gastos'],
                    ['Gastos apertura', 'Gastos con apertura_gasto (cuenta + contrapartida)', 'Según AperturaGastoEmpresa'],
                    ['Ticket gastro', 'impuesto_pago acumulado', 'cierre_maquina.ticket_gastro'],
                    ['Pago 24', 'vta_ant_gastro', 'cierre_maquina.pago24'],
                    ['Ticket prom.', 'ticket_prom (debe + haber contrapartida)', 'cierre_maquina.ticket_prom_debe / ticket_prom_haber'],
                    ['Caja transitoria', 'tot_caja_trans + ticket gastro en transitoria', 'cierre_maquina.caja_transitoria'],
                    ['FF máquina', 'variacion_ff', 'cierre_maquina.ff_maquina'],
                    ['Ventas máquinas', 'maquinas_online (Flash win_ol_slot)', 'cierre_maquina.ventas'],
                    ['Ventas ruleta', 'ruletas_online (Flash win_ol_rul)', 'cierre_maquina.ventas_ruleta'],
                    ['Poder público', 'pago_diferido (asiento aparte + banco)', 'cierre_maquina.poder_publico'],
                    ['Diferencia caja', 'Ajuste neto tras online/real/totalcoin', 'cierre_maquina.diferencia_caja'],
                    ['Partida pendiente', 'Descuadre residual ≤ tolerancia', 'cierre_maquina.partida_pendiente'],
                    ['Canon lotería', '% sobre base online (config 34 % default)', 'cierre_maquina.canon_loteria + cont_canon_loteria'],
                    ['Canon hospital', '% sobre base online (config 1 % default)', 'cierre_maquina.canon_hospital + cont_canon_hospital'],
                ],
            ],
            'parrafos2' => [
                'Valores cuenta financiera (transferencias, bancos) imputan a la cuentacontable resuelta desde la cuentacaja de la rendición, no a un slot fijo.',
                'El total FSL para Anita es maquinas_online + ruletas_online del Flash del día, no la suma de efectivo en caja.',
            ],
        ],
        [
            'titulo' => '6. Cierre bingo',
            'captura_id' => 'cierre_bingo',
            'herramientas_grupos' => [
                ['titulo' => 'Listado y acciones', 'clave' => 'bingo_listado'],
            ],
            'parrafos' => [
                'Ruta: contable/cierre-rendiciones-bingo. Agrupa rendiciones de bingo confirmadas por empresa y fecha. Preview y ejecución generan asientos BIN (réplica p-vtabingo.c) con envío FBI a Anita según PV por empresa.',
            ],
            'tabla' => [
                'caption' => 'Cuentas del cierre bingo (CierreRendicionBingoConfigSupport)',
                'headers' => ['Concepto asiento', 'Origen / total', 'Slot cuenta automática'],
                'rows' => [
                    ['Caja / efectivo', 'in_monto + sobrante + redondeo', 'cierre_bingo.efectivo'],
                    ['Premio 53 %', 'tot_premio + tot_bingo', 'cierre_bingo.premio53'],
                    ['Pozo bingo', 'tot_pozo', 'cierre_bingo.pozo_bingo'],
                    ['Premio pantalla', 'tot_pantalla', 'cierre_bingo.pantalla'],
                    ['Otros premios', 'otros_premios', 'cierre_bingo.otros_premios'],
                    ['Diferencia caja', 'dif_caja_asiento / refuerzo', 'cierre_bingo.diferencia_caja'],
                    ['Ventas sala', 'Contrapartida cuadre asiento 1', 'cierre_bingo.ventas'],
                    ['Pozo 58 % (dev. acum.)', 'Asiento «Dev. pozo acum.»', 'cierre_bingo.pozo58'],
                    ['Pago hospital', 'tot_pago_hospital + contrapartida', 'cierre_bingo.pago_hospital / cont_hospital'],
                ],
            ],
        ],
        [
            'titulo' => '7. Cierre bingo: evolución pozo acumulado',
            'captura_id' => 'preview_bingo',
            'parrafos' => [
                'Además del asiento «Pago de premios», el cierre bingo puede generar el bloque «Dev. pozo acum.» cuando hay movimiento de pozo 58 %, premio 53 % y porcentaje de recaudación. Este segundo asiento refleja la evolución del pozo acumulado sin mezclarlo con el pago de premios del día.',
                'En preview verifique que ambos bloques cuadren y que el monto FBI (tot_recaudacion) coincida con lo esperado para Anita.',
            ],
            'items' => [
                'Pozo 58 %: línea «Dev. pozo acum. — Pozo 58%».',
                'Premio 53 % en devolución: contrapartida en el mismo bloque.',
                '% recaudación: imputa al pozo bingo según tot_porc_recaud.',
                'Canones adicionales (si vienen en totales) generan asientos propios con debe/haber configurados.',
            ],
        ],
        [
            'titulo' => '8. Estacionamiento y maquinavending',
            'herramientas_grupos' => [
                ['titulo' => 'Estacionamiento', 'clave' => 'estacionamiento_resumen'],
                ['titulo' => 'Maquinavending', 'clave' => 'maquinavending_resumen'],
            ],
            'parrafos' => [
                'Ambos módulos siguen el patrón listado agrupado → preview → ejecutar → anular, con agrupación por jornada además del cierre diario clásico.',
            ],
            'tabla' => [
                'caption' => 'Rutas principales',
                'headers' => ['Módulo', 'Listado', 'Conciliación Flash', 'Diario PV'],
                'rows' => [
                    ['Estacionamiento', 'contable/cierre-rendiciones-estacionamiento', '…/conciliacion-flash', '…/diario-puntoventa'],
                    ['Maquinavending', 'contable/cierre-rendiciones-maquinavending', '…/conciliacion-flash', '…/diario-puntoventa'],
                ],
            ],
            'items' => [
                'Ejecutar cierre jornada: cierra en bloque todas las rendiciones pendientes de una jornada (estacionamiento/vending).',
                'Permisos propios: listar / ejecutar / exportar / anular cierre-rendicion-{estacionamiento|maquinavending}-contable.',
                'Las cuentas automáticas y supports de asiento son análogos a máquinas pero con medios y CC propios del rubro.',
            ],
        ],
        [
            'titulo' => '9. Cierre turno gastronomía (contable)',
            'herramientas_grupos' => [
                ['titulo' => 'Consulta contable gastronomía', 'clave' => 'gastronomia_contable'],
            ],
            'parrafos' => [
                'Ruta: contable/cierres-turno-gastronomia (nombre de ruta cierres_turno_gastronomia_contable). No genera asiento de cierre de rendición como máquinas/bingo: es panel de consulta y conciliación para Contaduría sobre cierres de turno ya hechos en el módulo Gastronomía y rendidos en Caja.',
                'Desde aquí puede listar cierres por empresa/fecha, abrir comprobantes PDF, correr conciliación contra rendición gastronomía y ver diario por punto de venta.',
                'Los asientos contables de gastronomía Waitry (ventas, IVA, kiosco, fondo fijo, diferencias) se graban en Caja → Waitry cierre jornada (proceso analizar → lotes → grabar), no en esta pantalla Contable. No confunda las tres capas: cierre de turno operativo (Ventas), presentación/rendición (Caja) y asiento Waitry (Caja proceso).',
            ],
            'items' => [
                'La rendición gastronomía en Caja es el documento que alimenta tesorería; este listado contable no la reemplaza ni genera asiento.',
                'Presentación tipo jornada (Waitry/Z) y presentación tipo turno no alimentan el mismo pipeline que el cierre-asiento de máquinas/bingo.',
                'Permisos: listar-cierres-turno-gastronomia-contable, exportar-cierres-turno-gastronomia-contable.',
                'Si su empresa no opera gastronomía, omita este capítulo.',
            ],
        ],
        [
            'titulo' => '10. Controles operativos',
            'captura_id' => 'conciliacion_flash',
            'herramientas_grupos' => [
                ['titulo' => 'Conciliación Flash máquinas', 'clave' => 'conciliacion_flash_maquina'],
            ],
            'parrafos' => [
                'Período cerrado: PeriodoContableCierreSupport bloquea ejecutar y anular si la fecha del asiento cae en un período cerrado para Caja o Contable. El preview sigue disponible para diagnóstico.',
                'Preview vs ejecutar: deben coincidir línea a línea; si cambian datos entre preview y ejecución (nueva rendición, corrección Flash), vuelva a previsualizar.',
                'Anulación: solo con permiso anular-cierre-rendicion-*-contable; revierte asiento y desmarca rendiciones. No use anulación para corregir errores de Caja — corrija la rendición allí y vuelva a cerrar.',
                'Conciliación Flash (máquinas): compara por día la suma de rendiciones turno C contra flash_caja (win_ol_slot + win_ol_rul). Estado OK dentro de tolerancia (default 0,02); DIF indica revisar antes de cerrar. Exportable a PDF/Excel.',
            ],
            'tabla' => [
                'caption' => 'Checklist antes de ejecutar cierre',
                'headers' => ['Control', 'Dónde verificar'],
                'rows' => [
                    ['Rendiciones turno C confirmadas', 'Listado máquinas — grupo pendiente'],
                    ['Flash del día cargado', 'Conciliación Flash — columna total Flash'],
                    ['Cuentas automáticas completas', 'Preview — sin error «Faltan cuentas…»'],
                    ['Asiento cuadra', 'Preview — debe = haber ± 0,02'],
                    ['Período abierto', 'Mensaje al ejecutar / Cierre de período Contable'],
                    ['Días anteriores cerrados', 'Al ejecutar rango — correlatividad'],
                ],
            ],
        ],
        [
            'titulo' => '11. Permisos',
            'tabla' => [
                'caption' => 'Permisos por submódulo',
                'headers' => ['Permiso', 'Módulo', 'Uso'],
                'rows' => [
                    ['listar-cierre-rendicion-maquina-contable', 'Máquinas', 'Ver listado, preview, conciliación Flash'],
                    ['ejecutar-cierre-rendicion-maquina-contable', 'Máquinas', 'Ejecutar cierre y rango'],
                    ['exportar-cierre-rendicion-maquina-contable', 'Máquinas', 'Export listado y conciliación'],
                    ['anular-cierre-rendicion-maquina-contable', 'Máquinas', 'Anular cierre contable'],
                    ['listar-cierre-rendicion-bingo-contable', 'Bingo', 'Ver listado y preview'],
                    ['ejecutar-cierre-rendicion-bingo-contable', 'Bingo', 'Ejecutar cierre'],
                    ['exportar-cierre-rendicion-bingo-contable', 'Bingo', 'Export'],
                    ['anular-cierre-rendicion-bingo-contable', 'Bingo', 'Anular'],
                    ['listar-cierre-rendicion-estacionamiento-contable', 'Estacionamiento', 'Ver y conciliar'],
                    ['ejecutar-cierre-rendicion-estacionamiento-contable', 'Estacionamiento', 'Ejecutar'],
                    ['anular-cierre-rendicion-estacionamiento-contable', 'Estacionamiento', 'Anular'],
                    ['listar-cierre-rendicion-maquinavending-contable', 'Vending', 'Ver y conciliar'],
                    ['ejecutar-cierre-rendicion-maquinavending-contable', 'Vending', 'Ejecutar'],
                    ['anular-cierre-rendicion-maquinavending-contable', 'Vending', 'Anular'],
                    ['listar-cierres-turno-gastronomia-contable', 'Gastronomía', 'Consulta contable cierres turno'],
                    ['exportar-cierres-turno-gastronomia-contable', 'Gastronomía', 'Export listados'],
                ],
            ],
        ],
        [
            'titulo' => '12. Preguntas frecuentes (FAQ)',
            'items' => [
                '¿Por qué no aparece mi rendición en el listado de cierre? — Verifique turno C, estado confirmada y filtros de fecha/empresa. Turnos A/B no entran al cierre contable máquinas.',
                '¿Por qué el preview no cuadra? — Revise Flash del día, totalcoin, diferencia de caja y gastos de apertura. Use conciliación Flash para detectar desvíos online vs real.',
                '¿Puedo cerrar un día si el anterior quedó pendiente? — Ejecutar rango exige correlatividad; cierre los días en orden o use preview individual.',
                '¿Qué hago si falta una cuenta automática? — Complete el slot cierre_maquina.* o cierre_bingo.* en Configuración → Cuentas automáticas para la empresa.',
                '¿Anular borra la rendición de Caja? — No. Solo revierte el asiento contable y la marca de cierre; la rendición operativa sigue en Caja.',
                '¿Cuándo se envía a Anita? — Al ejecutar cierre: FSL (máquinas) y FBI (bingo) con PV por empresa en config rendicion_maquina_anita / bingo.',
                '¿Flash en cero y rendición con valores? — Estado DIF en conciliación; cargue Flash del día o corrija rendición antes de cerrar.',
                '¿Grupo parcial? — Algunas rendiciones del día tienen asiento y otras no; investigue cierres manuales incompletos o anule y re-ejecute el grupo entero.',
            ],
        ],
    ],
];
