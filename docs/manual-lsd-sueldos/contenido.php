<?php

/**
 * Manual de usuario — Libro de Sueldos Digital (ARCA).
 * Audiencia: liquidación / RR.HH., sin jerga de desarrollo.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Sueldos · Libro de Sueldos Digital (ARCA)',
    'version' => '1.0',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Qué es el Libro de Sueldos Digital',
            'parrafos' => [
                'El Libro de Sueldos Digital (LSD) es el envío que ARCA (ex AFIP) usa para el libro de sueldos y la declaración jurada F.931. Anita ERP no emite el PDF del libro ni el F.931: genera archivos de texto (TXT, Windows-1252) para importar en el sitio de ARCA, igual que Tango, Bejerman o Xubio.',
                'Menú: Sueldos → Liquidación → Libro de Sueldos Digital (ARCA). También puede abrir este manual con el botón Manual de esa pantalla, o desde el Centro de ayuda.',
                'Hay dos archivos distintos. El TXT de conceptos se importa una vez (o cuando agregue un concepto nuevo) en ARCA → Conceptos. El TXT de liquidación se genera por cada liquidación cerrada y se importa en ARCA → Liquidaciones y DDJJ.',
            ],
            'items' => [
                'El CUIT del empleador sale de Empresa → Nro. de inscripción. Si está mal, ARCA rechaza el archivo.',
                'Las contribuciones patronales (lo que paga la empresa) no van al TXT de conceptos ni al registro 03 del recibo.',
                'El 1002 (base no imponible) ya no hace falta para armar el F.931: el sistema calcula la detracción solo.',
            ],
        ],
        [
            'titulo' => '2. Dónde está cada cosa',
            'parrafos' => [
                'Estas son las pantallas del circuito. Las acciones que ve dependen de sus permisos.',
            ],
            'tabla' => [
                'caption' => 'Pantallas del LSD',
                'headers' => ['Pantalla', 'Menú / ruta', 'Para qué sirve'],
                'rows' => [
                    ['Libro de Sueldos Digital', 'Sueldos → Liquidación → Libro de Sueldos Digital', 'Exportar conceptos, generar el TXT del mes y ver presentaciones.'],
                    ['Cobertura LSD', 'Botón Ver cobertura (misma pantalla)', 'Lista los conceptos que todavía no tienen código AFIP.'],
                    ['Conceptos', 'Sueldos → Tablas → Conceptos', 'Alta y edición: tipo, fórmula, código AFIP y bases del 04.'],
                    ['Parámetros', 'Sueldos → Tablas → Parámetros', 'Montos con vigencia: detracción, tope SIPA, mínimo SIPA.'],
                    ['Empleado', 'Sueldos → Empleados', 'Datos SIJP: condición, modalidad, actividad, localidad AFIP.'],
                    ['Liquidación', 'Sueldos → Liquidación', 'Hay que cerrar la liquidación antes de generar el TXT.'],
                ],
            ],
        ],
        [
            'titulo' => '3. Circuito del mes (paso a paso)',
            'captura_id' => 'lsd_workbench',
            'parrafos' => [
                'En la pantalla del LSD hay tres bloques, de arriba hacia abajo: parametrización de conceptos, circuito del período y el formulario para generar. Siga este orden. ARCA pide primero las liquidaciones especiales (vacaciones, SAC, final) y después la mensual.',
            ],
            'tabla' => [
                'caption' => 'Orden del mes',
                'headers' => ['Paso', 'Qué hace', 'Resultado'],
                'rows' => [
                    ['1. Conceptos', 'Exportar TXT de conceptos e importarlo en ARCA → Conceptos (si hubo altas o cambios).', 'ARCA conoce sus códigos de empleador.'],
                    ['2. Cerrar liquidaciones', 'Cerrar en el ERP cada liquidación del mes (final, vacaciones, SAC, mensual).', 'Solo las cerradas aparecen en el combo.'],
                    ['3. Tipo E primero', 'Generar el TXT de vacaciones, SAC o final (ARCA las marca como E).', 'El circuito muestra “no hay E pendientes”.'],
                    ['4. Mensual / quincena', 'Recién entonces generar la mensual (M) o la quincena (Q).', 'Si falta una E con recibos, el sistema bloquea M/Q.'],
                    ['5. Importar en ARCA', 'Bajar el TXT e importarlo en Liquidaciones y DDJJ.', 'ARCA arma el libro y el F.931.'],
                    ['6. Marcar presentada', 'En el detalle de la presentación, botón Marcar presentada en ARCA.', 'No se puede volver a generar esa misma; si hay que corregir, use RE.'],
                ],
            ],
            'items' => [
                'Una liquidación especial sin recibos (cero empleados) no bloquea la mensual.',
                'Un archivo = una liquidación cerrada. No se mezclan dos liquidaciones en el mismo TXT.',
                'Envío SJ = libro + F.931. Envío RE = rectificativa (omite los registros 02 y 03).',
            ],
        ],
        [
            'titulo' => '4. Cómo crear y mapear un concepto',
            'herramientas_grupos' => [
                ['titulo' => 'Listado de conceptos', 'clave' => 'concepto_listado', 'incluir_listado' => true],
                ['titulo' => 'Alta / edición', 'clave' => 'concepto_form'],
            ],
            'parrafos' => [
                'Pantalla: Sueldos → Tablas → Conceptos → Nuevo. Complete primero lo de siempre (código, descripción, tipo, momento, fórmula). Después complete el bloque LSD, más abajo en el mismo formulario.',
                'El código AFIP es de 6 dígitos y sale del catálogo oficial. Al elegirlo, el sistema precarga los tildes de subsistemas: un remunerativo lleva todos en 1; un descuento, todos en 0. Si el código no está en la lista (rango libre, por ejemplo 111001), escríbalo en Código AFIP libre.',
                'Cód. empleador LSD: si lo deja vacío, el sistema usa el código interno del concepto, rellenado a 10 dígitos. Ese es el código que ARCA asocia a su empresa. No lo cambie si ya importó el TXT de conceptos.',
            ],
            'tabla' => [
                'caption' => 'Ejemplo: alta de horas extras 50 %',
                'headers' => ['Campo', 'Qué poner', 'Por qué'],
                'rows' => [
                    ['Código', 'Vacío o el que usen (ej. 120)', 'Identificación interna; va al recibo.'],
                    ['Descripción', 'HORAS EXTRAS 50%', 'Texto que ve el empleado y ARCA (hasta 150 caracteres en el TXT).'],
                    ['Tipo', 'Remunerativo', 'Suma al bruto y a la base jubilatoria.'],
                    ['Fórmula', 'Según cómo las liquiden (novedad × valor, etc.)', 'El LSD no calcula el importe: toma lo ya liquidado.'],
                    ['Concepto AFIP (LSD)', '110006 — Horas extras', 'Catálogo oficial. Si no aparece, use un 11xxxx libre.'],
                    ['Subsistemas LSD', 'Dejar los que precargó el sistema', 'Remunerativo = todos en 1.'],
                    ['Bases registro 04', 'Solo si es un informativo tipo 1000/3630', 'Un haber normal no lleva bases 04: entra por el código AFIP.'],
                    ['Va al recibo', 'Tildado', 'Si no, no viaja al TXT de liquidación.'],
                ],
            ],
            'parrafos2' => [
                'Después de guardar un concepto nuevo, vuelva al LSD y exporte otra vez el TXT de conceptos. Impórtelo en ARCA antes de generar la liquidación del mes. El botón Ver cobertura le muestra lo que todavía no tiene código AFIP.',
                'No mapee a AFIP los informativos 999, 1000, 1002, 1501, 1502 ni el 3630: no van al TXT de conceptos. El 1002 ni siquiera hace falta liquidarlo para el F.931 (ver sección 5).',
            ],
        ],
        [
            'titulo' => '5. Detracción Ley 27.430 (reemplazo del 1002)',
            'captura_id' => 'concepto_1002',
            'parrafos' => [
                'La detracción es un descuento que la ley permite sobre la base de contribuciones patronales (columna “importe a detraer” y base 10 del F.931). No es un descuento al empleado: no baja el neto.',
                'Antes se armaba con el concepto 1002 y una fórmula muy larga de Anita (DTBR, modalidad 8/14, un tope escrito a mano). Eso ya no manda. El motor del LSD calcula la detracción al generar el TXT, aunque el 1002 no esté en el recibo.',
                'El 1002 quedó como informativo, con la fórmula detraccion(), por si quieren seguir viéndolo en el recibo. Si lo sacan del grupo, el F.931 sale igual.',
            ],
            'tabla' => [
                'caption' => 'Cómo calcula el motor',
                'headers' => ['Regla', 'Qué hace', 'Ejemplo'],
                'rows' => [
                    ['Monto de tabla', 'Lee DETRACCION_LEY_27430 (Sueldos → Parámetros).', 'Hoy $ 7.003,68 por mes (valor nominal congelado).'],
                    ['Días', 'Prorratea: monto × min(días, 30) / 30.', '15 días → $ 3.501,84. 31 días → $ 7.003,68.'],
                    ['Tope de la base', 'Nunca detrae más que la remuneración del período.', 'Si el bruto es $ 5.000, detrae $ 5.000.'],
                    ['Jubilado', 'Condición SIJP 2: no detrae.', 'Base 10 e importe a detraer en cero.'],
                    ['Varias liquidaciones del mes', 'No duplica el tope: en el mes entero, como máximo el monto de tabla.', 'Final + mensual: no suma 7.003 + 7.003.'],
                    ['Piso previsional', 'Si cargaron MINIMO_SIPA, la base 10 no queda debajo de ese piso.', 'Hasta que el mínimo esté en 0, esta regla no recorta.'],
                ],
            ],
            'items' => [
                'El monto se cambia en Parámetros, no tocando la fórmula. Si un establecimiento usa el valor sectorial ($ 17.509,20), cargue un valor por empresa.',
                'DETRACCION_MODALIDADES_PARCIAL está vacío a propósito. Casi toda la planta tiene modalidad 8 (código viejo de Anita), que acá no significa “tiempo parcial AFIP”. Si más adelante las modalidades quedan bien (01 completa, 08/14 parcial), ponga 8,14 en ese parámetro y el motor aplicará el 67 %.',
                'Si detraccion() no les da el importe a las chicas al liquidar, avisen: se puede volver a la fórmula vieja. El LSD va a seguir calculando igual.',
            ],
        ],
        [
            'titulo' => '6. Tope SIPA y mínimo imponible',
            'captura_id' => 'parametro_tope',
            'parrafos' => [
                'El tope SIPA es el techo de la base jubilatoria (bases 1, 2, 3, 5, 9 del F.931). El mínimo SIPA es el piso: si el empleado trabajó el mes completo y su remuneración queda por debajo, se informa el mínimo.',
                'Los dos viven en Sueldos → Tablas → Parámetros, con fecha de vigencia. Puede haber un valor global y otro por empresa (el de la empresa gana). Hoy TOPE_SIPA y MINIMO_SIPA están en cero: el motor no recorta ni eleva. Cuando ANSES publique el trimestre, cargue el valor con la fecha desde la que rige (por ejemplo 2026-09-01).',
            ],
            'tabla' => [
                'caption' => 'Parámetros que usa el LSD',
                'headers' => ['Código', 'Qué es', 'Si está en 0'],
                'rows' => [
                    ['DETRACCION_LEY_27430', 'Detracción mensual art. 4', 'No detrae (salvo el fallback de fábrica $ 7.003,68 si el parámetro no existiera).'],
                    ['DETRACCION_TIEMPO_PARCIAL', 'Factor 0,67 para jornada parcial AFIP', 'Usa 0,67 solo si hay modalidades cargadas.'],
                    ['DETRACCION_MODALIDADES_PARCIAL', 'Códigos SIJP que se consideran parciales', 'Vacío = no aplica el 67 %.'],
                    ['TOPE_SIPA', 'Tope de la base jubilatoria', 'No recorta: informa la remuneración completa.'],
                    ['MINIMO_SIPA', 'Piso de la base jubilatoria', 'No eleva. Tampoco limita la detracción.'],
                ],
            ],
            'parrafos2' => [
                'Cómo cargar un valor nuevo: edite el parámetro → Agregar vigencia → fecha + importe → Guardar. No pise la vigencia anterior: deje el histórico (marzo, junio, septiembre…). El sistema toma el último valor con fecha menor o igual a la fecha de pago de la liquidación.',
                'Si una liquidación ya trae haberes 1000 / 3630 (topes informativos de Anita), el LSD respeta esas sumas y después aplica el motor de detracción. No hace falta rearmar esas fórmulas para el F.931.',
            ],
        ],
        [
            'titulo' => '7. Generar el TXT e importarlo en ARCA',
            'captura_id' => 'lsd_ver',
            'herramientas_grupos' => [
                ['titulo' => 'Pantalla LSD', 'clave' => 'lsd_workbench', 'incluir_listado' => true],
                ['titulo' => 'Detalle de la presentación', 'clave' => 'lsd_ver'],
            ],
            'parrafos' => [
                'En Generar liquidación elija empresa, mes y año del período (ejemplo julio 2026) y la liquidación cerrada. El nro. AFIP lo sugiere el sistema (el siguiente libre de esa empresa y período); puede cambiarlo si ARCA ya usó ese número. Fecha de pago y rúbrica son las que pide el organismo. El tilde Incluir licencias sin recibo agrega empleados de licencia que no tienen recibo en esa liquidación (solo registro 04).',
                'Al generar, el sistema arma los registros 01 (cabecera), 02 (trabajador), 03 (conceptos del recibo), 04 (bases F.931), 05 y 06 si correspondan. Después puede previsualizar, bajar el TXT e importarlo en ARCA. El PDF del libro y el F.931 los emite el organismo, no el ERP.',
            ],
            'tabla' => [
                'caption' => 'Estados de una presentación',
                'headers' => ['Estado', 'Significado', 'Qué puede hacer'],
                'rows' => [
                    ['Generada', 'El TXT está listo; todavía no lo confirmaron en ARCA.', 'Descargar, marcar presentada, marcar rechazada o eliminar.'],
                    ['Presentada', 'Ya se importó y aceptó en ARCA.', 'No se regenera. Si hay que corregir: Generar rectificativa RE.'],
                    ['Rechazada', 'ARCA no la tomó (o la marcaron a mano).', 'Corregir datos, eliminar y volver a generar, o usar RE.'],
                ],
            ],
            'items' => [
                'Después de importar con éxito en ARCA, pulse Marcar presentada. Así el circuito del mes queda al día y nadie pisa ese envío.',
                'La rectificativa RE omite los registros 02 y 03 (no reenvía el detalle de recibos). Úsela cuando ARCA pide corregir solo el F.931.',
                'El archivo se llama LSD_AAAAMM_NNNNN.txt (ejemplo LSD_202607_00001.txt). No lo abra con Excel: se rompe el formato de ancho fijo.',
            ],
        ],
        [
            'titulo' => '8. Casos prácticos',
            'parrafos' => [
                'Los números de abajo son de liquidaciones reales de este entorno (Biyemas, julio 2026). Sirven de guía: los de ustedes pueden diferir en importes, no en el procedimiento.',
            ],
            'tabla' => [
                'caption' => 'Caso A — Liquidación final de julio (tipo E)',
                'headers' => ['Dato', 'Valor', 'Qué tiene que pasar'],
                'rows' => [
                    ['Liquidación', 'Nº 4 · LIQ.FINAL 07-26 · empresa Biyemas', 'Cerrada. Aparece en el combo al elegir julio 2026.'],
                    ['Tipo ARCA', 'E (especial)', 'Hay que generarla antes que la mensual de julio.'],
                    ['Trabajadores', '11 recibos', 'El 04 tiene 11 líneas.'],
                    ['Días', '31 (el motor usa 30)', 'Detracción llena: $ 7.003,68 a cada uno.'],
                    ['1002 en el recibo', 'Estaba; ya no es obligatorio', 'Con o sin 1002, importe a detraer = $ 7.003,68.'],
                    ['Archivo', 'LSD_202607_00001.txt', 'Importar en ARCA y marcar presentada.'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Caso B — Mes con final y después mensual',
                'headers' => ['Situación', 'Qué hacer', 'Qué evita'],
                'rows' => [
                    ['Hay una final con recibos y todavía no tiene TXT', 'Generar primero la E. El circuito lista “faltan: …” en rojo.', 'Que ARCA rechace la mensual por orden.'],
                    ['La mensual se genera después', 'Mismo período, otra liquidación, otro nro. AFIP.', 'Pisar el archivo de la final.'],
                    ['Un empleado está en las dos', 'El motor no vuelve a detraer $ 7.003 en la segunda si ya se usó el tope del mes.', 'Doble detracción en el F.931.'],
                ],
            ],
            'items' => [
                'Caso C — Quincena de 15 días: detracción $ 3.501,84 en esa liquidación. Si después hay segunda quincena, el tope del mes sigue siendo $ 7.003,68 en total.',
                'Caso D — Alta de un concepto nuevo a mitad de mes: créelo, asígnele AFIP, exporte TXT de conceptos, impórtelo en ARCA, recién entonces genere la liquidación. Si no, ARCA no reconoce el código de empleador.',
                'Caso E — Jubilado (condición SIJP 2): el 04 lleva bases previsionales e importe a detraer en cero. Revise el dato en la ficha del empleado, no en el concepto.',
                'Caso F — ARCA rechazó el archivo: marque Rechazada, corrija (CUIT, concepto sin AFIP, CUIL, etc.), elimine la presentación o genere de nuevo. Si ya estaba presentada, use RE.',
                'Caso G — Quieren ver la detracción en el recibo: dejen el 1002 en el grupo. Fórmula detraccion(), tipo informativo. No resta del neto.',
            ],
        ],
        [
            'titulo' => '9. Si algo no cierra',
            'parrafos' => [
                'Antes de escribir a sistemas, recorra esta lista. La mayoría de los rechazos de ARCA son datos, no el archivo.',
            ],
            'tabla' => [
                'caption' => 'Síntoma y qué mirar',
                'headers' => ['Qué se ve', 'Causa habitual', 'Qué hacer'],
                'rows' => [
                    ['No aparece la liquidación en el combo', 'Sigue abierta, o la empresa / período no coinciden.', 'Cerrar la liquidación. El período es el mes y año de la liq.'],
                    ['Bloquea la mensual', 'Hay una E del mismo período con recibos y sin TXT.', 'Generar primero esa E, o verificar que no tenga recibos.'],
                    ['ARCA no reconoce un concepto', 'Falta el TXT de conceptos o el código AFIP.', 'Ver cobertura → editar el concepto → reexportar conceptos.'],
                    ['CUIT / CUIL inválido', 'Empresa sin nro. de inscripción, o CUIL del empleado incompleto.', 'Ficha empresa y ficha empleado. 11 dígitos.'],
                    ['Importe a detraer en cero', 'Jubilado, o remuneración 0, o parámetro en 0.', 'Condición SIJP, recibo, DETRACCION_LEY_27430.'],
                    ['Detracción duplicada en el mes', 'Dos TXT del mismo empleado sin tope (no debería pasar).', 'Avisar: el motor limita al monto mensual.'],
                    ['detraccion() da 0 al liquidar', 'Sin remunerativo en esa corrida, o jubilado.', 'Pruebe con un sueldo cargado. Si sigue en 0, avisen para revisar.'],
                    ['Tope que no recorta', 'TOPE_SIPA en 0.', 'Cargar vigencia con el valor ANSES del trimestre.'],
                    ['Excel rompió el TXT', 'Lo abrieron y guardaron.', 'Volver a descargar desde el ERP. No usar Excel.'],
                ],
            ],
            'parrafos2' => [
                'Este documento se actualiza con el módulo. La versión en pantalla (botón Manual) es la vigente. PDF y Word se bajan desde la misma página.',
            ],
        ],
    ],
];
