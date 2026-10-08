<?php

/**
 * Manual de usuario — Módulo Sueldos y Jornales (documento único).
 * Integra sanciones, Libro de Sueldos Digital y reportes definibles.
 * Audiencia: RR.HH. / liquidación, sin jerga de desarrollo.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Sueldos y jornales',
    'version' => '2.0',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Introducción — Sueldos y jornales',
            'parrafos' => [
                'El módulo Sueldos y jornales liquida haberes, descuentos, aportes y conceptos informativos de cada legajo, arma el recibo, el asiento de devengamiento y los archivos que ARCA pide para el Libro de Sueldos Digital. Este manual es el documento único del módulo: reúne lo que antes estaba partido entre sanciones, Libro de Sueldos Digital y reportes definibles, y agrega el circuito de elegibilidad, grupos y criterios premium.',
                'Menú: Módulo Sueldos y Jornales. Las acciones que ve dependen de los permisos de su usuario. Desde el Centro de ayuda o el botón Manual de las pantallas del circuito vuelve a este documento.',
                'Un jornal y un sueldo mensual usan el mismo motor. La diferencia está en el concepto (fórmula, tipo y momento) y en las novedades del período (horas, días, importes), no en un módulo aparte.',
            ],
            'items' => [
                'El set de conceptos de un legajo no es “todos los del catálogo” ni “solo los del grupo”: se arma en un orden fijo (capítulo 6).',
                'La elegibilidad filtra después de armar candidatos. Un concepto que vino por grupo o por novedad igual puede quedar afuera.',
                'La asignación explícita del legajo (forzar incluir o forzar excluir) manda al final, con vigencia.',
                'El Libro de Sueldos Digital no calcula el recibo: toma una liquidación ya cerrada y arma el TXT para ARCA.',
            ],
        ],
        [
            'titulo' => '2. Mapa de pantallas',
            'parrafos' => [
                'Cada pantalla pertenece a un submenú. Si no la ve, suele faltar el permiso o el ítem de menú de su rol.',
            ],
            'tabla' => [
                'caption' => 'Dónde se hace cada cosa',
                'headers' => ['Submenú', 'Pantalla', 'Ruta'],
                'rows' => [
                    ['Personal', 'Empleados', 'sueldos/empleado'],
                    ['Personal', 'SiRADIG (F.572 Ganancias)', 'sueldos/siradig'],
                    ['Liquidación', 'Corridas de liquidación', 'sueldos/liquidacion'],
                    ['Liquidación', 'Consulta Ganancias', 'sueldos/ganancias'],
                    ['Liquidación', 'Novedades de liquidación', 'sueldos/novedad'],
                    ['Liquidación', 'Descuentos por fallos', 'sueldos/descuento-fallo'],
                    ['Liquidación', 'Libro de Sueldos Digital (ARCA)', 'sueldos/libro-sueldos-digital'],
                    ['Tablas de liquidación', 'Conceptos, grupos, acumuladores, antigüedad, Ganancias', 'sueldos/concepto y relacionadas'],
                    ['Tablas de Sueldos', 'Categorías, obras sociales, sindicatos, ausencias, sanciones, imputación', 'sueldos/categoria y relacionadas'],
                    ['Reportes de Sueldos', 'Vacaciones, fallos, reportes definibles, sanciones', 'sueldos/reporte-definible y relacionadas'],
                    ['Indumentaria', 'Prendas, entregas, planificación, solicitudes, árbol', 'sueldos/prenda y relacionadas'],
                    ['Configuración → Sueldos', 'Parámetros de liquidación (detracción, tope SIPA)', 'sueldos/parametro'],
                ],
            ],
        ],
        [
            'titulo' => '3. Circuito del mes',
            'parrafos' => [
                'El orden habitual evita liquidar con datos viejos y evita que ARCA rechace el TXT por una liquidación especial pendiente.',
            ],
            'tabla' => [
                'caption' => 'Pasos del período',
                'headers' => ['Paso', 'Pantalla', 'Resultado'],
                'rows' => [
                    ['1. Legajo', 'Empleados', 'Altas, bajas, categoría, sindicato, obra social, grupos'],
                    ['2. Novedades', 'Novedades o solapa del empleado', 'Horas, días, importes y sanciones que descuentan'],
                    ['3. Revisar el set', 'Empleado → Liquidación', 'Modo grupos o sin grupo, y la lista “Por qué no entraron”'],
                    ['4. Calcular', 'Corridas de liquidación', 'Recibos en borrador o calculada'],
                    ['5. Cerrar', 'Misma grilla, candado', 'La corrida queda lista para LSD y asiento'],
                    ['6. Especiales primero', 'Libro de Sueldos Digital', 'Vacaciones, SAC y final (tipo E) antes que la mensual'],
                    ['7. TXT e importar', 'Misma pantalla', 'Archivo para ARCA; después marcar presentada'],
                    ['8. Reportes', 'Reportes definibles y el resto', 'Listados, comparación y, si aplica, distribución'],
                ],
            ],
            'items' => [
                'Una liquidación especial sin recibos no bloquea la mensual.',
                'Un archivo LSD = una liquidación cerrada. No se mezclan dos corridas en el mismo TXT.',
            ],
        ],
        [
            'titulo' => '4. Legajo del empleado',
            'herramientas_grupos' => [
                ['titulo' => 'Listado de empleados', 'clave' => 'empleado_listado', 'incluir_listado' => true],
                ['titulo' => 'Ficha', 'clave' => 'empleado_ficha'],
            ],
            'parrafos' => [
                'Pantalla: Personal → Empleados. El listado es un workbench: vista, diseñador, consulta avanzada (QBE) y chips de estado (Activos por defecto, Provisorios, Bajas, Todos) y de empresa. Esos chips quedan por encima de la consulta avanzada y se combinan con AND. Al cambiar de vista o exportar se conservan.',
                'La ficha tiene solapas. Actualizar graba la cabecera desde cualquier solapa. Las altas propias (ausencias, familiares, novedades, sanciones, préstamos) se graban con el botón de esa solapa.',
            ],
            'tabla' => [
                'caption' => 'Solapas que usa liquidación',
                'headers' => ['Solapa', 'Para qué'],
                'rows' => [
                    ['Datos personales / Laborales', 'CUIL, empresa, categoría, sindicato, obra social, agrupamiento, lugar, condición SIJP.'],
                    ['Liquidación', 'Bases del legajo, grupos de conceptos, tilde sin grupo, asignación explícita y set efectivo.'],
                    ['Vacaciones / Ausencias', 'Licencias y vacaciones. No duplique una suspensión si el tipo de sanción ya genera novedad.'],
                    ['Préstamos / Cuotas', 'Planes que entran al recibo como concepto de cuota.'],
                    ['Novedades', 'Horas, días o importes del período, atados a un concepto.'],
                    ['Sanciones', 'Expediente disciplinario. El concepto lo define el tipo, no esta solapa.'],
                    ['Familiares y SiRADIG', 'Cargas de familia y F.572 para el impuesto a las ganancias.'],
                    ['Fallos', 'Descuentos de caja ligados al legajo.'],
                    ['Indumentaria / Archivos / Foto', 'Entregas, adjuntos y foto. No cambian el cálculo del recibo.'],
                ],
            ],
        ],
        [
            'titulo' => '5. Conceptos de liquidación',
            'herramientas_grupos' => [
                ['titulo' => 'Listado', 'clave' => 'concepto_listado', 'incluir_listado' => true],
                ['titulo' => 'Alta / edición', 'clave' => 'concepto_form'],
            ],
            'parrafos' => [
                'Pantalla: Tablas de liquidación → Conceptos de liquidación. El concepto es la regla: código, descripción, tipo, momento, fórmulas de importe, cantidad y valor, y —si va a ARCA— el bloque LSD.',
                'En edición, el debugger prueba la fórmula contra un legajo y un tipo de corrida sin grabar, con rastro paso a paso. Sirve para ver por qué un jornal dio cero antes de liquidar a toda la planta.',
            ],
            'tabla' => [
                'caption' => 'Ejemplo: horas extras al 50 % (jornal)',
                'headers' => ['Campo', 'Qué poner', 'Efecto'],
                'rows' => [
                    ['Código / descripción', '120 — HORAS EXTRAS 50%', 'Identificación del recibo y del LSD.'],
                    ['Tipo', 'Remunerativo', 'Suma al bruto y a la base jubilatoria.'],
                    ['Momento', 'Siempre / mensual', 'Entra en mensual, quincena, SAC, vacaciones y final.'],
                    ['Fórmula cantidad', 'La novedad de horas (valor 1)', 'Las horas las carga Novedades, no la fórmula fija.'],
                    ['Fórmula valor', 'Valor hora × 1,50', 'El 50 % vive en el concepto, no en cada legajo.'],
                    ['Fórmula importe', 'Cantidad × valor, o la expresión que usen', 'Ese importe es el que ve el recibo.'],
                    ['Concepto AFIP (LSD)', '110006 — Horas extras', 'Sin este código, ARCA no reconoce la línea.'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Tipos y qué hacen en el recibo',
                'headers' => ['Tipo', 'En el neto', 'Notas'],
                'rows' => [
                    ['Remunerativo', 'Suma', 'Base de aportes y contribuciones.'],
                    ['No remunerativo', 'Suma', 'No integra la misma base que el remunerativo.'],
                    ['Descuento / aporte / retención', 'Resta', 'El aporte es del trabajador.'],
                    ['Asignación familiar', 'Suma', 'Según la regla del concepto.'],
                    ['Contribución empleador', 'No baja el neto', 'Solo recibo de contribuciones (CE) y asiento.'],
                    ['Neto', 'Cierra', 'No se imputa como un haber más.'],
                    ['Informativo', 'No mueve el neto', 'Reportes, topes, detracción visible (1002).'],
                ],
            ],
            'items' => [
                'Para elegir un concepto en otro formulario use lupa, F1 o código + Enter. No hay un combo con todo el catálogo.',
                'Momento “No se liquida” saca el concepto de cualquier corrida, aunque esté en un grupo.',
            ],
        ],
        [
            'titulo' => '6. Cómo se arma el set (grupos, elegibilidad y explícitos)',
            'parrafos' => [
                'Antes de calcular, el sistema decide qué conceptos entran a ese legajo en esa corrida. El orden es siempre el mismo. La solapa Liquidación del empleado muestra el resultado: badge de modo, set efectivo y la tabla Por qué no entraron.',
            ],
            'tabla' => [
                'caption' => 'Orden de resolución',
                'headers' => ['Orden', 'De dónde sale', 'Qué puede pasar después'],
                'rows' => [
                    ['1. Base', 'Si hay grupos: la unión de sus conceptos activos. Si no hay grupos: catálogo o solo novedades (capítulo 8).', 'Todavía no es el recibo.'],
                    ['2. Momento del grupo', 'En vacaciones el grupo solo aporta conceptos de momento vacaciones.', 'El resto figura como excluido por momento.'],
                    ['3. Novedad vigente', 'Una novedad del período suma el concepto aunque no estuviera en el grupo.', 'Después igual pasa por elegibilidad.'],
                    ['4. Elegibilidad', 'Reglas del concepto contra el perfil del legajo (capítulo 7).', 'Si no cumple, sale del set y se explica el motivo.'],
                    ['5. Explícito +/−', 'Forzar incluir vuelve a meterlo. Forzar excluir lo saca aunque haya pasado todo lo anterior.', 'Respeta desde / hasta.'],
                    ['6. Momento de la corrida', 'El concepto tiene que aplicar al tipo de liquidación (mensual, SAC, final…).', 'Una novedad de momento “siempre” sí entra en vacaciones.'],
                ],
            ],
            'items' => [
                'Anita traía hasta 3 grupos por legajo. Acá no hay tope: se agregan los que hagan falta.',
                'El origen de cada línea del set se ve como etiqueta: Grupo, Novedad, Catálogo + elegibilidad, Explícito +, Plan de cuotas o Sistema.',
                'Planes de cuota suman su concepto cuando el plan está vigente; no hace falta repetirlos en el grupo.',
            ],
        ],
        [
            'titulo' => '7. Modo grupos',
            'herramientas_grupos' => [
                ['titulo' => 'Grupo de conceptos', 'clave' => 'grupo_form'],
                ['titulo' => 'Set en el legajo', 'clave' => 'set_legajo'],
            ],
            'parrafos' => [
                'Pantalla del catálogo: Tablas de liquidación → Grupos de conceptos. Ahí se define el código, la descripción, la empresa (vacío = todas) y los conceptos del grupo (Ctrl o Cmd + clic).',
                'En el empleado, solapa Liquidación, se asignan uno o más grupos. Si hay al menos uno, el badge dice Modo grupos. La base del set es la unión de esos grupos, no la intersección: un concepto que está en cualquiera de los grupos es candidato.',
            ],
            'tabla' => [
                'caption' => 'Ejemplo: planta mensual + convenio',
                'headers' => ['Pieza', 'Contenido', 'Qué liquida'],
                'rows' => [
                    ['Grupo 10 — Mensual planta', 'Sueldo, presentismo, horas extras, obra social', 'Haberes comunes de la planta.'],
                    ['Grupo 20 — CCT comercio', 'Aporte sindical y cuota solidaria', 'Solo quien tenga este grupo además del 10.'],
                    ['Legajo con 10 y 20', 'Unión de ambos', 'Candidatos = haberes del 10 más descuentos del 20.'],
                    ['Legajo solo con 10', 'Sin el 20', 'No entra el aporte sindical, salvo novedad o explícito +.'],
                    ['Elegibilidad del aporte', 'Sindicato igual a 1', 'Aunque el grupo 20 esté asignado, si el legajo es de otro sindicato el aporte se excluye.'],
                ],
            ],
            'parrafos2' => [
                'Quitar un grupo no borra el catálogo: solo deja de aportar sus conceptos. Si al quitar el último grupo el tilde “Sin grupos: no liquidar por elegibilidad” está destildado, el legajo pasa al catálogo completo filtrado por elegibilidad. Si está tildado, pasa a esperar novedades (capítulo 8).',
                'Un grupo marcado Anita vino del sincronismo. Se puede consultar en otra solapa; quitarlo del legajo no borra el grupo maestro.',
            ],
        ],
        [
            'titulo' => '8. Elegibilidad',
            'herramientas_grupos' => [
                ['titulo' => 'Reglas en el concepto', 'clave' => 'elegibilidad'],
            ],
            'parrafos' => [
                'Las reglas viven en el concepto (editar concepto → Reglas de elegibilidad), no en el legajo. Comparan un dato del empleado con un valor. Si el concepto no tiene reglas activas y vigentes, no se filtra por perfil: entra por grupo, catálogo o novedad.',
                'Dentro del mismo número de Grupo OR basta con que se cumpla una regla. Entre números distintos se tienen que cumplir todos (AND de grupos OR). La vigencia desde / hasta se compara con la fecha de la liquidación; vacía = siempre.',
            ],
            'tabla' => [
                'caption' => 'Campos, operadores y un ejemplo',
                'headers' => ['Campo', 'Ejemplo de regla', 'Legajo que entra'],
                'rows' => [
                    ['Sindicato (código)', 'Igual a 1', 'Solo el convenio 1.'],
                    ['Sindicato (código)', 'En lista 1,2 — o dos reglas con el mismo Grupo OR', 'Convenio 1 o 2. Una sola regla “en lista” alcanza.'],
                    ['Obra social (código)', 'Distinto de 0, Grupo OR nuevo', 'AND: sindicato 1 o 2, y además obra social cargada.'],
                    ['Categoría (código)', 'Igual a 40', 'Solo esa categoría (jornal de oficiales, por ejemplo).'],
                    ['Agrupamiento (código)', 'En lista PLANTA,OBRA', 'Esos agrupamientos.'],
                    ['Empresa (ID)', 'Igual al id de la empresa', 'El concepto no corre en otra razón social.'],
                    ['Cualquier campo', 'Vacío / sin valor', 'El dato no está cargado.'],
                    ['Cualquier campo', 'Con valor', 'El dato está cargado, sea cual sea.'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Ejemplo armado: aporte sindical del convenio 1 o 2, no jubilados',
                'headers' => ['Grupo OR', 'Regla', 'Lectura'],
                'rows' => [
                    ['1', 'Sindicato igual a 1', 'Primera alternativa.'],
                    ['1', 'Sindicato igual a 2', 'Misma alternativa: con una de las dos alcanza.'],
                    ['2', 'Categoría distinto de JUB', 'Además, la categoría no es jubilado. Si falla este grupo, el concepto sale.'],
                ],
            ],
            'items' => [
                'Deje el Grupo OR que propone el formulario (el siguiente número) cuando la regla nueva es un AND. Reutilice el número cuando es un OR.',
                'También existen sindicato, obra social y categoría por ID interno. Prefiera el código: es el que ve en la ficha.',
                'Si no cumple, Por qué no entraron dice de dónde venía (grupo, novedad o catálogo) y qué regla falló, con el valor del legajo.',
                'Destildar Activo o poner una vigencia fuera del mes apaga la regla sin borrarla.',
            ],
        ],
        [
            'titulo' => '9. Sin grupos: elegibilidad o solo novedades',
            'parrafos' => [
                'Si el legajo no tiene ningún grupo, el tilde de la solapa Liquidación decide el modo. Con grupos asignados el tilde no cambia el cálculo: queda guardado para cuando se quiten todos.',
            ],
            'tabla' => [
                'caption' => 'Los dos modos sin grupo',
                'headers' => ['Tilde “no liquidar por elegibilidad”', 'Modo', 'Qué entra'],
                'rows' => [
                    ['Destildado (recomendado si el catálogo ya tiene reglas)', 'Sin grupo (catálogo + elegibilidad)', 'Todo concepto activo que no sea “no se liquida”, filtrado por elegibilidad, más novedades y explícitos.'],
                    ['Tildado', 'Sin grupo (solo novedades)', 'No arma el catálogo. Solo novedades vigentes, planes de cuota y asignaciones explícitas. La elegibilidad igual se aplica a esos candidatos.'],
                ],
            ],
            'parrafos2' => [
                'Ejemplo. Un legajo nuevo, sin grupos, tilde destildado: entran sueldo, presentismo y obra social porque sus reglas de categoría y obra social se cumplen, y no entra el aporte del otro sindicato. El mismo legajo con el tilde marcado y sin novedades: el set queda vacío hasta que cargue horas, un préstamo o un explícito +.',
                'Use el tilde marcado cuando el grupo todavía no está armado y no quiere que un alta dispare todo el catálogo. Use el destildado cuando las reglas de elegibilidad ya describen la planta (estilo de perfiles: el concepto dice a quién aplica).',
            ],
        ],
        [
            'titulo' => '10. Asignación explícita',
            'parrafos' => [
                'En la misma solapa, Asignación explícita (+/−) pisa el resultado anterior para un concepto y un rango de fechas. Vacío en desde o hasta significa abierto.',
            ],
            'tabla' => [
                'caption' => 'Ejemplos',
                'headers' => ['Acción', 'Caso', 'Efecto'],
                'rows' => [
                    ['Forzar incluir', 'Un jornalero de otra categoría cobra un plus de turno solo en marzo', 'Entra aunque no esté en el grupo ni cumpla la categoría. Desde 01/03, hasta 31/03.'],
                    ['Forzar excluir', 'Está en el grupo de obra social pero este mes tiene cobertura externa', 'Sale del set aunque la elegibilidad diera bien.'],
                    ['Quitar la fila', 'Terminó la excepción', 'Vuelve a valer grupo + elegibilidad.'],
                ],
            ],
            'items' => [
                'El explícito + no saltea el momento de la corrida: un concepto de solo SAC no se liquida en la mensual aunque lo fuerce.',
                'El concepto tiene que estar activo.',
            ],
        ],
        [
            'titulo' => '11. Novedades',
            'herramientas_grupos' => [
                ['titulo' => 'Novedades', 'clave' => 'novedad_listado', 'incluir_listado' => true],
            ],
            'parrafos' => [
                'Pantalla: Liquidación → Novedades de liquidación, o la solapa Novedades del empleado. Una novedad vigente suma el concepto al set (y después pasa por elegibilidad). Si el concepto ya estaba, la novedad solo aporta cantidades o importes a la fórmula.',
                'Valor 1 suele ser cantidad (horas, días). Valor 2 suele ser importe. La fórmula del concepto decide cuál usa. Una novedad manual o importada manda sobre el espejo que venga de Anita para el mismo hecho.',
            ],
            'tabla' => [
                'caption' => 'Ejemplo: 6 horas extras en la quincena',
                'headers' => ['Campo', 'Valor', 'Notas'],
                'rows' => [
                    ['Empleado', 'Legajo del jornalero', 'Lupa / F1 / código + Enter.'],
                    ['Concepto', '120 HORAS EXTRAS 50%', 'Tiene que estar activo.'],
                    ['Período o desde–hasta', 'La quincena que se liquida', 'Fuera de vigencia no entra a esa corrida.'],
                    ['Cantidad', '6', 'La fórmula multiplica por el valor hora con el 50 %.'],
                    ['Estado', 'Distinto de anulada', 'Anulada no suma ni al set ni al recibo.'],
                ],
            ],
            'items' => [
                'Importar novedades trae un lote; revise el resultado antes de calcular.',
                'La sanción que genera novedad no se carga otra vez a mano: el expediente la crea y la actualiza (capítulo 16).',
            ],
        ],
        [
            'titulo' => '12. Corridas de liquidación',
            'herramientas_grupos' => [
                ['titulo' => 'Corridas', 'clave' => 'liquidacion_listado', 'incluir_listado' => true],
            ],
            'parrafos' => [
                'Pantalla: Liquidación → Corridas de liquidación. Cada corrida tiene empresa, período, tipo y alcance de recibos. Calcular arma los recibos. Cerrar los congela para el LSD y el asiento. Reabrir vuelve a un estado editable si el permiso lo permite.',
                'Alcance Todos: legajos de la empresa de la corrida. Empresa actual: solo legajos de una sola empresa. Multiempresa: legajos que están en más de una; al emitir, incluye los recibos del mismo legajo, período y tipo en las otras empresas.',
            ],
            'tabla' => [
                'caption' => 'Tipos de corrida que más se usan',
                'headers' => ['Tipo', 'Cuándo', 'LSD'],
                'rows' => [
                    ['Mensual', 'Haberes del mes', 'Envío M, después de las especiales.'],
                    ['1ra / 2da quincena', 'Jornales quincenales', 'Envío Q.'],
                    ['SAC / Aguinaldo', 'Solo conceptos de momento SAC (y los de siempre)', 'Especial E, antes que la mensual.'],
                    ['Vacaciones', 'Conceptos de momento vacaciones', 'Especial E.'],
                    ['Liquidación final', 'Egreso', 'Especial E. Generarla antes que la mensual del mismo mes.'],
                    ['Complementaria / ajuste / gratificación / especial', 'Reliquidar o pagar un concepto puntual', 'Según el tipo ARCA que asigne el circuito.'],
                ],
            ],
            'items' => [
                'Estados en los que se puede recalcular: borrador, calculada y revisada. Cerrada, no.',
                'Ver recibos abre el resultado. El rastro de una línea muestra cómo se evaluó la fórmula.',
                'Calidad / asiento revisa el devengamiento antes de contabilizar. El asiento se abre en otra solapa.',
                'Sincronizar desde Anita (si el botón está) trae cabeceras y después novedades. Es un paso de sistemas o de puesta en marcha, no el cierre mensual habitual.',
            ],
        ],
        [
            'titulo' => '13. Momento del concepto y tipo de corrida',
            'parrafos' => [
                'El momento dice en qué corridas puede aparecer el concepto. “Siempre / mensual” entra en todas. El resto es exclusivo o acotado.',
            ],
            'tabla' => [
                'caption' => 'Qué momento entra en cada corrida',
                'headers' => ['Momento del concepto', 'Entra en'],
                'rows' => [
                    ['Siempre / mensual', 'Mensual, quincenas, SAC, vacaciones, final y el resto.'],
                    ['1ra quincena', 'Solo 1ra quincena.'],
                    ['2da quincena', '2da quincena, mensual y final.'],
                    ['Mensual / 2da quincena', '2da quincena, mensual, final y semanal.'],
                    ['Vacaciones (y p/ o s/ quincena)', 'Solo corrida de vacaciones.'],
                    ['S.A.C.', 'Solo corrida de SAC.'],
                    ['Liquidación final / Especial', 'Corridas de ese tipo.'],
                    ['No se liquida', 'Ninguna.'],
                ],
            ],
            'parrafos2' => [
                'Ejemplo. El sueldo básico está en “siempre”: aparece en la mensual y también en la final. El aguinaldo está en “S.A.C.”: no aparece en la mensual aunque el legajo tenga el grupo. En una corrida de vacaciones, el grupo solo aporta conceptos de momento vacaciones; las novedades de conceptos “siempre” sí se liquidan en esa corrida.',
            ],
        ],
        [
            'titulo' => '14. Maestros, bases y parámetros',
            'parrafos' => [
                'Tablas de Sueldos arma el perfil que lee la elegibilidad: categorías, obras sociales, sindicatos, agrupamientos, lugares, ART, motivos de egreso, nombres de bases y tipos de ausencia. Tablas de liquidación arma el cálculo: conceptos, grupos, acumuladores, tablas de antigüedad y tablas de Ganancias.',
                'Las bases del legajo y de la categoría (solapa Liquidación y ABM de categoría) son importes con vigencia: sueldo, valor hora, adicionales. La fórmula los lee por el nombre de base. Una vigencia nueva no pisa la anterior: el sistema toma la que corresponde a la fecha de la corrida.',
                'Parámetros de liquidación (Configuración → Sueldos → Parámetros de liquidación) guardan montos con vigencia, globales o por empresa. El de la empresa gana. No pise la fila vieja: agregue vigencia.',
            ],
            'tabla' => [
                'caption' => 'Parámetros que usa el Libro de Sueldos Digital',
                'headers' => ['Código', 'Qué es', 'Si está en 0'],
                'rows' => [
                    ['DETRACCION_LEY_27430', 'Detracción mensual (Ley 27.430)', 'No detrae, salvo el valor de fábrica si el parámetro no existiera.'],
                    ['DETRACCION_TIEMPO_PARCIAL', 'Factor de jornada parcial AFIP', 'Usa 0,67 solo si hay modalidades cargadas.'],
                    ['DETRACCION_MODALIDADES_PARCIAL', 'Códigos SIJP que se consideran parciales', 'Vacío = no aplica el factor. A propósito, si la planta sigue con el código viejo 8 de Anita.'],
                    ['TOPE_SIPA', 'Techo de la base jubilatoria', 'No recorta: informa la remuneración completa.'],
                    ['MINIMO_SIPA', 'Piso de la base jubilatoria', 'No eleva. Tampoco limita la detracción.'],
                ],
            ],
        ],
        [
            'titulo' => '15. Vacaciones, ausencias y préstamos',
            'parrafos' => [
                'Vacaciones (Tablas de Sueldos) define la escala por antigüedad: días que corresponden según los años. El reporte Saldos de vacaciones muestra lo gozado y lo pendiente. La corrida de tipo Vacaciones liquida los conceptos de ese momento.',
                'Tipos de ausencia clasifican licencias. Una ausencia de suspensión (el tipo histórico 41) descuenta días por el circuito de ausencias. Si el tipo de sanción ya genera novedad por los mismos días, no cargue las dos: el descuento se duplica.',
                'Préstamos / Cuotas en la ficha arman un plan. Cada cuota vigente suma su concepto al set (origen Plan de cuotas) en la corrida que corresponde, hasta agotar el plan.',
            ],
            'items' => [
                'Ejemplo de vacaciones: categoría con 14 días al año, 10 ya gozados → el saldo informa 4. La liquidación de vacaciones usa los conceptos de momento vacaciones, no el reporte.',
                'Ejemplo de préstamo: $ 100.000 en 10 cuotas de $ 10.000 sobre el concepto de préstamo. En cada mensual entra una cuota mientras el plan esté vigente.',
            ],
        ],
        [
            'titulo' => '16. Sanciones disciplinarias',
            'captura_id' => 'flujo_sancion',
            'herramientas_grupos' => [
                ['titulo' => 'Tipos', 'clave' => 'tipo_listado', 'incluir_listado' => true],
                ['titulo' => 'Alta del tipo', 'clave' => 'tipo_form'],
                ['titulo' => 'Motivos', 'clave' => 'motivo_listado', 'incluir_listado' => true],
                ['titulo' => 'Solapa del empleado', 'clave' => 'empleado_sancion'],
                ['titulo' => 'Reporte', 'clave' => 'reporte'],
            ],
            'parrafos' => [
                'El expediente se carga en el empleado. El concepto de liquidación se define en el tipo de sanción, no en cada carga. Importe no cobrado es el salario que no se paga (por ejemplo una suspensión: días × jornal). No es un porcentaje ni una multa. La ley no permite descontar una penalidad extra.',
                'Menú: Tablas de Sueldos → Tipos de sanción y Motivos de sanción; ficha → Sanciones; Reportes de Sueldos → Sanciones de empleados.',
            ],
            'tabla' => [
                'caption' => 'Tildes del tipo',
                'headers' => ['Tilde', 'Cuándo marcarlo', 'Si no'],
                'rows' => [
                    ['Requiere días', 'Hay que indicar días o período (suspensión).', 'Típico de notificación o apercibimiento.'],
                    ['Goza sueldo', 'Esos días se pagan.', 'Destildado = suspensión sin goce.'],
                    ['Genera novedad de liquidación', 'Debe impactar el recibo. Hace falta el concepto.', 'Queda solo el expediente.'],
                    ['Activo', 'Se puede elegir en cargas nuevas.', 'El histórico no se borra.'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Ejemplo: suspensión de 2 días sin goce',
                'headers' => ['Paso', 'Qué cargar', 'Resultado'],
                'rows' => [
                    ['Tipo', 'Concepto de suspensión, requiere días, sin goce, genera novedad', 'El descuento tiene regla.'],
                    ['Expediente', '2 días, importe no cobrado = 2 × jornal, estado notificada o firme', 'Se arma la novedad: valor 1 = días, valor 2 = importe.'],
                    ['Carta', 'PDF desde la grilla', 'Notificación. El texto extra sale de la plantilla del tipo.'],
                    ['Estados que liquidan', 'Notificada, con descargo, firme', 'Borrador, impugnada y anulada no liquidan.'],
                ],
            ],
            'items' => [
                'Al guardar se crea o actualiza la novedad solo si el tipo genera novedad, el estado liquida, y hay días o importe distinto de cero.',
                'Si cambia el tipo o anula el expediente, la novedad ligada se actualiza o se anula sola.',
                'El histórico importado de Anita quedó firme y sin novedad: es consulta, no descuento automático. Anita no trae el concepto: asígnelo en el tipo si las cargas nuevas deben descontar.',
                'El reporte filtra por empresa, fechas, estado, legajo, tipo y motivo, y exporta PDF, Excel o CSV del filtro completo.',
            ],
        ],
        [
            'titulo' => '17. Fallos de caja y Ganancias',
            'parrafos' => [
                'Fallos de caja (Tablas de Sueldos) y Descuentos por fallos (Liquidación) registran faltantes que se descuentan al legajo. El reporte Cta. cte. fallos muestra el saldo. El descuento entra al recibo por el concepto que tenga asociado el circuito, igual que cualquier otra novedad.',
                'Ganancias usa el plan de líneas, la escala del art. 94 y las deducciones del art. 30 (Tablas de liquidación). En el empleado, Familiares y SiRADIG cargan el F.572. Consulta Ganancias muestra el impuesto del período. SiRADIG (Personal) concentra la presentación.',
            ],
            'items' => [
                'Ejemplo de fallo: faltante de $ 15.000 en un turno, descuento en dos cuotas. La cuenta corriente muestra el saldo hasta que las cuotas se liquidan.',
                'Ejemplo de ganancias: un familiar a cargo declarado en la solapa y deducido en el art. 30 baja la base del impuesto de esa corrida, no de los meses ya cerrados.',
            ],
        ],
        [
            'titulo' => '18. Libro de Sueldos Digital — circuito',
            'herramientas_grupos' => [
                ['titulo' => 'Pantalla LSD', 'clave' => 'lsd_workbench', 'incluir_listado' => true],
            ],
            'parrafos' => [
                'El Libro de Sueldos Digital (LSD) es el envío que ARCA usa para el libro de sueldos y la declaración jurada F.931. Anita ERP no emite el PDF del libro ni el F.931: genera archivos de texto (TXT, Windows-1252) para importar en el sitio de ARCA.',
                'Menú: Liquidación → Libro de Sueldos Digital (ARCA). Hay dos archivos. El TXT de conceptos se importa una vez (o cuando agregue un concepto) en ARCA → Conceptos. El TXT de liquidación se genera por cada liquidación cerrada y se importa en ARCA → Liquidaciones y DDJJ.',
                'En la pantalla hay tres bloques: parametrización de conceptos, circuito del período y el formulario para generar. ARCA pide primero las liquidaciones especiales (vacaciones, SAC, final) y después la mensual.',
            ],
            'tabla' => [
                'caption' => 'Orden del mes en ARCA',
                'headers' => ['Paso', 'Qué hace', 'Resultado'],
                'rows' => [
                    ['1. Conceptos', 'Exportar TXT de conceptos e importarlo en ARCA si hubo altas o cambios.', 'ARCA conoce los códigos de empleador.'],
                    ['2. Cerrar', 'Cerrar en el ERP cada liquidación del mes.', 'Solo las cerradas aparecen en el combo.'],
                    ['3. Tipo E', 'Generar vacaciones, SAC o final.', 'El circuito muestra si quedan E pendientes.'],
                    ['4. Mensual o quincena', 'Recién entonces la M o la Q.', 'Si falta una E con recibos, el sistema bloquea M/Q.'],
                    ['5. Importar', 'Bajar el TXT e importarlo en Liquidaciones y DDJJ.', 'ARCA arma el libro y el F.931.'],
                    ['6. Marcar presentada', 'En el detalle de la presentación.', 'Ese envío no se regenera. Para corregir, use RE.'],
                ],
            ],
            'items' => [
                'El CUIT del empleador sale de Empresa → Nro. de inscripción. Si está mal, ARCA rechaza el archivo.',
                'Las contribuciones patronales no van al TXT de conceptos ni al registro 03 del recibo.',
                'Envío SJ = libro + F.931. Envío RE = rectificativa (omite los registros 02 y 03).',
                'El 1002 (base no imponible) ya no hace falta para armar el F.931: el sistema calcula la detracción solo.',
            ],
        ],
        [
            'titulo' => '19. Libro de Sueldos Digital — concepto, detracción y tope',
            'parrafos' => [
                'En el concepto, el bloque LSD pide el código AFIP de 6 dígitos. Al elegirlo, el sistema precarga los tildes de subsistemas: un remunerativo lleva todos en 1; un descuento, todos en 0. Si el código no está en la lista, escríbalo en Código AFIP libre. Cód. empleador LSD: vacío = el código interno rellenado a 10 dígitos. No lo cambie si ya importó el TXT de conceptos.',
                'La detracción es un descuento que la ley permite sobre la base de contribuciones patronales. No baja el neto del empleado. El motor la calcula al generar el TXT, aunque el concepto 1002 no esté en el recibo. El 1002 puede quedar como informativo, fórmula detraccion(), si quieren verla en el recibo.',
            ],
            'tabla' => [
                'caption' => 'Cómo calcula la detracción',
                'headers' => ['Regla', 'Qué hace', 'Ejemplo'],
                'rows' => [
                    ['Monto de tabla', 'Lee DETRACCION_LEY_27430.', 'Un valor de referencia de planta es $ 7.003,68 por mes (el vigente está en Parámetros).'],
                    ['Días', 'Monto × min(días, 30) / 30.', '15 días → la mitad. 31 días → el mes completo.'],
                    ['Tope de la base', 'Nunca detrae más que la remuneración del período.', 'Si el bruto es menor, detrae el bruto.'],
                    ['Jubilado', 'Condición SIJP 2: no detrae.', 'Base e importe a detraer en cero.'],
                    ['Varias liquidaciones del mes', 'En el mes entero, como máximo el monto de tabla.', 'Final + mensual no suman dos detracciones llenas.'],
                    ['Piso previsional', 'Si cargaron MINIMO_SIPA, la base no queda debajo.', 'Con el mínimo en 0, esta regla no recorta.'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Qué no mapear a AFIP',
                'headers' => ['Concepto', 'Por qué'],
                'rows' => [
                    ['999, 1000, 1002, 1501, 1502, 3630', 'Informativos: no van al TXT de conceptos.'],
                    ['1002', 'No hace falta liquidarlo para el F.931. El motor detrae igual.'],
                    ['Contribuciones patronales', 'No viajan en el registro 03.'],
                    ['Haber normal', 'No lleva bases del registro 04: entra por el código AFIP.'],
                ],
            ],
            'items' => [
                'Después de un concepto nuevo: guardar, exportar TXT de conceptos, importarlo en ARCA, recién entonces generar la liquidación.',
                'Ver cobertura lista los conceptos exportables que todavía no tienen código AFIP, con enlace al ABM.',
                'El tope SIPA es el techo de las bases jubilatorias del F.931. El mínimo es el piso si el empleado trabajó el mes completo. Hoy pueden estar en cero: cargue la vigencia cuando ANSES publique el trimestre, con la fecha desde la que rige. El sistema toma el último valor con fecha menor o igual a la fecha de pago.',
                'Si una liquidación ya trae haberes 1000 / 3630, el LSD respeta esas sumas y después aplica la detracción. No hace falta rearmar esas fórmulas para el F.931.',
            ],
        ],
        [
            'titulo' => '20. Libro de Sueldos Digital — generar, casos y rechazos',
            'herramientas_grupos' => [
                ['titulo' => 'Detalle de la presentación', 'clave' => 'lsd_ver'],
            ],
            'parrafos' => [
                'En Generar liquidación elija empresa, mes, año y la liquidación cerrada. El nro. AFIP lo sugiere el sistema (el siguiente libre); cámbielo si ARCA ya usó ese número. Fecha de pago y rúbrica son las que pide el organismo. El tilde Incluir licencias sin recibo agrega empleados de licencia que no tienen recibo (solo registro 04).',
                'El TXT lleva registros 01 (cabecera), 02 (trabajador), 03 (conceptos del recibo), 04 (bases), y 05 o 06 si corresponden. El archivo se llama LSD_AAAAMM_NNNNN.txt. No lo abra con Excel: se rompe el ancho fijo. Vuelva a descargarlo desde el ERP.',
            ],
            'tabla' => [
                'caption' => 'Estados y casos',
                'headers' => ['Situación', 'Qué hacer'],
                'rows' => [
                    ['Generada', 'Descargar, marcar presentada, marcar rechazada o eliminar.'],
                    ['Presentada', 'No se regenera. Corrección: Generar rectificativa RE.'],
                    ['Rechazada', 'Corregir datos y volver a generar, o usar RE si ya estaba presentada.'],
                    ['Final con recibos, mensual bloqueada', 'Generar primero la E. Una final sin recibos no bloquea.'],
                    ['Mismo empleado en final y mensual', 'El motor no vuelve a detraer el monto lleno si el mes ya usó el tope.'],
                    ['Quincena de 15 días', 'Detracción prorrateada. El tope del mes sigue siendo el monto de tabla en total.'],
                    ['Jubilado (SIJP 2)', 'Bases previsionales e importe a detraer en cero. El dato está en la ficha, no en el concepto.'],
                    ['RE', 'Omite registros 02 y 03. Úsela cuando ARCA pide corregir solo el F.931.'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Si algo no cierra',
                'headers' => ['Qué se ve', 'Qué mirar'],
                'rows' => [
                    ['No aparece la liquidación', 'Sigue abierta, o empresa / período no coinciden.'],
                    ['ARCA no reconoce un concepto', 'Cobertura, código AFIP, reexportar TXT de conceptos.'],
                    ['CUIT o CUIL inválido', 'Nro. de inscripción de la empresa y CUIL del empleado (11 dígitos).'],
                    ['Importe a detraer en cero', 'Jubilado, remuneración 0, o parámetro de detracción en 0.'],
                    ['Tope que no recorta', 'TOPE_SIPA en 0. Cargue la vigencia ANSES.'],
                    ['detraccion() da 0 en el recibo', 'Sin remunerativo en esa corrida, o jubilado. El TXT igual calcula la detracción legal.'],
                ],
            ],
        ],
        [
            'titulo' => '21. Reportes definibles y criterios premium',
            'herramientas_grupos' => [
                ['titulo' => 'Catálogo', 'clave' => 'reporte_definible', 'incluir_listado' => true],
                ['titulo' => 'Criterios premium', 'clave' => 'reporte_premium'],
            ],
            'parrafos' => [
                'Pantalla: Reportes de Sueldos → Reportes definibles. Cada listado define columnas (dato del empleado o suma de conceptos: importe, cantidad o valor, con signo) y se ejecuta sobre una liquidación o sobre el padrón de empleados. Equivale a los listados que en Anita se armaban con el generador de listas. Se pueden importar, crear a mano o partir de una plantilla.',
                'Los criterios premium son los que este ERP suma sobre ese generador. No cambian el recibo: cambian cómo se consulta, se controla y se reparte el resultado.',
            ],
            'tabla' => [
                'caption' => 'Criterios premium',
                'headers' => ['Criterio', 'Ejemplo', 'Para qué sirve'],
                'rows' => [
                    ['Fórmula de columna', 'C1+C2, o si(C1>0, C2, 0), o entre', 'Neto, diferencia o un tramo sin otro listado.'],
                    ['Drill', 'Clic en un importe de la grilla', 'Abre las líneas del recibo que armaron esa celda.'],
                    ['Variación (Δ)', 'Ejecutar contra dos liquidaciones', 'Columna de diferencia: este mes contra el anterior.'],
                    ['Versiones', 'Publicar y restaurar', 'El listado publicado no se pisa al editar; se puede volver atrás.'],
                    ['ACL', 'Dueño y usuarios con ver o editar', 'Un reporte de nómina no queda abierto a todo el rol.'],
                    ['Confidencial', 'Nómina marcada confidencial', 'Publicar un dataset de origen Anita exige certificación vigente si hay alerta bloqueante.'],
                    ['Paridad y acta', 'Pantalla de paridad, botón Certificar', 'Compara con Anita y deja un acta por liquidación.'],
                    ['Suscripción', 'Email por centro de costo, lugar, agrupamiento o legajo', 'Cada paquete sale con su recorte. Por legajo, al email del empleado.'],
                    ['API', 'Contrato en /api/v1/sueldos/reportes-definibles/openapi.json', 'Otra sistema lee el dataset paginado, el estado (certificación y paridad) y puede recibir un aviso firmado.'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Ejemplos de uso',
                'headers' => ['Necesidad', 'Cómo'],
                'rows' => [
                    ['Neto por legajo', 'Plantilla de neto, o columnas de haberes menos descuentos con fórmula C1−C2.'],
                    ['Obra social o sindicato', 'Listado importado (por ejemplo obra social o sindicatos) ejecutado sobre la liquidación cerrada.'],
                    ['¿Por qué este importe?', 'Drill de la celda al detalle del recibo.'],
                    ['Subió el bruto', 'Misma definición, variación contra la liquidación anterior.'],
                    ['Mandar el centro de costo al jefe', 'Suscripción segmentada por centro de costo, probar envío, después el cron de distribución.'],
                    ['Cerrar la paridad del mes', 'Paridad de esa liquidación y certificar. Sin acta vigente no se publica un dataset confidencial con alerta bloqueante.'],
                ],
            ],
            'items' => [
                'Flujo: importar o crear, editar columnas, ejecutar con liquidación y agrupación, exportar PDF / Excel / CSV. Opcional: versión, ACL y suscripción.',
                'La pantalla del reporte tiene además un resumen propio (botón Manual de ese listado) con los comandos de importación y de paridad. El contenido de uso está en este capítulo.',
                'Siguen fuera de alcance: envío por organigrama de jefes, consulta OData completa y un catálogo que cruce sueldos con contabilidad en un solo modelo.',
            ],
        ],
        [
            'titulo' => '22. Asiento, imputación e indumentaria',
            'parrafos' => [
                'Imputación contable de conceptos (Tablas de Sueldos) dice a qué cuenta va cada concepto en el asiento de devengamiento. Los tipos que imputan son remunerativo, no remunerativo, descuento, aporte, contribución, retención y asignación. El neto y el informativo no arman una línea de haber por sí mismos. Cuentas automáticas cubre lo que no está concepto por concepto.',
                'Desde la corrida, Calidad / asiento muestra el devengamiento antes de contabilizar. Hay dos modos por empresa. ERP: un asiento por corrida, centro de costo en todas las líneas. Anita: un asiento por centro de costo, pasivos en centro 0. Lo define la configuración de asiento de esa empresa; no se elige en cada corrida.',
                'Indumentaria no liquida sueldo. Prendas define el artículo y las variantes. Entregas registra lo que se dio al legajo (comprobante). Planificación de compra proyecta lo que hay que comprar. Solicitudes y el árbol de aprobación siguen el mismo circuito de mis aprobaciones, filtrado por indumentaria. La solapa Indumentaria del empleado muestra lo entregado.',
            ],
            'items' => [
                'Ejemplo de imputación: horas extras al haber de jornales y el aporte sindical al pasivo del sindicato. Si falta la cuenta, la calidad del asiento lo marca antes de contabilizar.',
                'Ejemplo de indumentaria: solicitud de dos camisas, aprobación por el árbol, entrega con comprobante. No genera novedad de sueldo salvo que ustedes carguen un concepto aparte.',
            ],
        ],
        [
            'titulo' => '23. Permisos y preguntas frecuentes',
            'parrafos' => [
                'Si falta un botón, revise el permiso antes de tratarlo como un error de pantalla. Los nombres siguen el patrón listar / crear / editar / actualizar / borrar más el recurso.',
            ],
            'tabla' => [
                'caption' => 'Permisos que más se consultan',
                'headers' => ['Permiso', 'Pantalla'],
                'rows' => [
                    ['empleado-sueldos', 'Ficha y solapas'],
                    ['concepto-sueldos / grupo-concepto-sueldos', 'Conceptos, grupos y elegibilidad'],
                    ['novedad-sueldos / liquidacion-sueldos', 'Novedades y corridas (calcular, cerrar, reabrir)'],
                    ['tipo-sancion / motivo-sancion / sancion-empleado / sancion-reporte', 'Expediente y listado'],
                    ['lsd-sueldos y exportar-conceptos / generar / presentar / rectificar', 'TXT, estados y RE'],
                    ['reporte-sueldos-definible, ver-confidencial y ver-pii', 'Listados premium, nómina confidencial y datos sensibles. Certificar usa actualizar.'],
                    ['imputacion-concepto-sueldos', 'Cuentas del asiento'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Dudas habituales',
                'headers' => ['Pregunta', 'Respuesta'],
                'rows' => [
                    ['¿Por qué este concepto no está en el recibo?', 'Mire Por qué no entraron: grupo, elegibilidad, explícito, momento o novedad anulada.'],
                    ['Tiene grupos y también el tilde de no liquidar por elegibilidad', 'Mandan los grupos. El tilde aplica cuando no queda ninguno.'],
                    ['¿Dónde elijo el concepto de una sanción?', 'En el tipo de sanción, no en la carga.'],
                    ['¿Qué es el importe no cobrado?', 'Salario que no se paga. No es una multa.'],
                    ['La mensual del LSD no deja generar', 'Falta el TXT de una especial con recibos del mismo período.'],
                    ['¿El 1002 tiene que estar en el grupo?', 'No, para el F.931. Sí, si quieren ver la detracción impresa en el recibo.'],
                    ['¿Puedo abrir el TXT con Excel?', 'No. Se rompe el formato. Descárguelo de nuevo.'],
                    ['¿Un jornal es otro módulo?', 'No. Es un concepto (valor hora, momento de quincena) más la novedad de horas o días.'],
                ],
            ],
            'parrafos2' => [
                'La versión en pantalla (botón Manual o Centro de ayuda) es la vigente. PDF y Word se bajan desde la misma página.',
            ],
        ],
    ],
];
