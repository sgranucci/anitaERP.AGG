<?php

/**
 * Manual de usuario — Módulo Sueldos (sanciones disciplinarias).
 * Audiencia: RR.HH. / liquidación, sin jerga técnica.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Módulo Sueldos · Sanciones disciplinarias',
    'version' => '1.0',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Introducción — Sueldos',
            'parrafos' => [
                'El módulo Sueldos de Anita ERP cubre el legajo del empleado, conceptos, novedades, ausencias, liquidación y reportes. Esta primera versión del manual se centra en el expediente disciplinario: tipos, motivos, carga en el empleado, impacto en el recibo y el listado gerencial.',
                'Menú: Tablas de Sueldos (tipos y motivos), ficha del empleado → solapa Sanciones, y Reportes de Sueldos → Sanciones de empleados. Las acciones visibles dependen de los permisos de su usuario.',
                'Desde el Centro de ayuda o el botón Manual de las pantallas del circuito puede volver a este documento.',
            ],
            'items' => [
                'El concepto de liquidación se define en el tipo de sanción, no en cada expediente.',
                'Importe no cobrado es el salario que no se paga (por ejemplo una suspensión), no una multa.',
                'Si el tipo genera novedad, no cargue además una ausencia de suspensión (tipo 41) por los mismos días.',
            ],
        ],
        [
            'titulo' => '2. Mapa de pantallas',
            'parrafos' => [
                'Estas son las pantallas del circuito de sanciones. El resto del módulo (conceptos, liquidación, ausencias, SIRADIG) se opera como hasta ahora.',
            ],
            'tabla' => [
                'caption' => 'Dónde se hace cada cosa',
                'headers' => ['Pantalla', 'Ruta', 'Para qué sirve'],
                'rows' => [
                    ['Tipos de sanción', 'sueldos/tipo-sancion', 'Catálogo: clase, días, concepto y tildes (novedad, activo).'],
                    ['Motivos de sanción', 'sueldos/motivo-sancion', 'Catálogo de causas (inasistencia, conducta, etc.).'],
                    ['Solapa Sanciones', 'Ficha del empleado', 'Cargar, editar, adjuntar, carta PDF y quitar un expediente.'],
                    ['Sanciones de empleados', 'sueldos/sancion-reporte', 'Listado filtrable con export PDF / Excel / CSV.'],
                ],
            ],
        ],
        [
            'titulo' => '3. Circuito de una sanción',
            'captura_id' => 'flujo_sancion',
            'parrafos' => [
                'Primero se configura el tipo (una vez). Después se carga el expediente en el empleado. Si el tipo tiene concepto y genera novedad, al guardar se arma o actualiza la novedad de liquidación.',
            ],
            'tabla' => [
                'caption' => 'Pasos recomendados',
                'headers' => ['Paso', 'Pantalla', 'Resultado'],
                'rows' => [
                    ['1. Definir tipo', 'Tipos de sanción', 'Nombre, clase, concepto y tildes'],
                    ['2. Definir motivo', 'Motivos de sanción', 'Causa para elegir en la carga'],
                    ['3. Cargar expediente', 'Empleado → Sanciones', 'Hecho, días, importe no cobrado, comentario'],
                    ['4. Notificar', 'Misma solapa → PDF', 'Carta de notificación'],
                    ['5. Liquidar', 'Automático si aplica', 'Novedad con días e importe'],
                    ['6. Consultar', 'Reporte de sanciones', 'Listado y exportes'],
                ],
            ],
        ],
        [
            'titulo' => '4. Tipos de sanción — concepto y tildes',
            'herramientas_grupos' => [
                ['titulo' => 'Listado', 'clave' => 'tipo_listado', 'incluir_listado' => true],
                ['titulo' => 'Alta / edición', 'clave' => 'tipo_form'],
            ],
            'parrafos' => [
                'Pantalla: Tablas de Sueldos → Tipos de sanción. Acá se decide si una sanción descuenta en el recibo o queda solo como expediente.',
                'El concepto no se elige al cargar la sanción del empleado: se elige en este ABM, en Concepto liquidación (lupa / F1 / código + Enter).',
            ],
            'tabla' => [
                'caption' => 'Qué hace cada tilde',
                'headers' => ['Tilde', 'Cuándo marcarlo', 'Qué pasa si no'],
                'rows' => [
                    ['Requiere días (suspensión)', 'Hay que indicar días o período (suspensión).', 'Típico en notificación o apercibimiento: no exige días.'],
                    ['Goza sueldo', 'El empleado cobra esos días.', 'Destildado = suspensión sin goce (lo habitual).'],
                    ['Genera novedad de liquidación', 'Debe impactar el recibo. Hace falta el concepto de arriba.', 'Queda solo el expediente; no se arma novedad.'],
                    ['Activo', 'Se puede elegir en cargas nuevas.', 'Destildado: no aparece en la lupa; el histórico no se borra.'],
                ],
            ],
            'items' => [
                'Vacío el concepto = este tipo no descuenta. Si tilda Genera novedad, elija el concepto.',
                'Los tipos importados de Anita vinieron sin concepto y sin tilde de novedad: complete el ABM si una suspensión debe descontar.',
                'Clase (llamado, apercibimiento, suspensión, otro) es clasificación; no reemplaza al concepto.',
            ],
        ],
        [
            'titulo' => '5. Motivos de sanción',
            'herramientas_grupos' => [
                ['titulo' => 'Listado y ABM', 'clave' => 'motivo_listado', 'incluir_listado' => true],
            ],
            'parrafos' => [
                'Pantalla: Tablas de Sueldos → Motivos de sanción. Es el catálogo de causas (por ejemplo inasistencia o conducta). En la carga se elige con lupa / F1, igual que el tipo.',
                'El tilde Activo funciona igual que en el tipo: destildado no se elige en cargas nuevas.',
            ],
        ],
        [
            'titulo' => '6. Carga en el empleado',
            'herramientas_grupos' => [
                ['titulo' => 'Solapa Sanciones', 'clave' => 'empleado_sancion'],
            ],
            'parrafos' => [
                'En la ficha del empleado, solapa Sanciones, se carga el expediente: tipo, motivo, fecha del hecho, período, días, importe no cobrado, notificación, comentario, descargo, resolución, estado y adjuntos.',
                'Importe no cobrado es un monto en pesos: el salario que no se paga por la sanción (casi siempre una suspensión: días × jornal). No es un porcentaje ni una multa sobre el sueldo. La LCT no permite descontar una penalidad extra.',
            ],
            'tabla' => [
                'caption' => 'Campos que más se consultan',
                'headers' => ['Campo', 'Qué cargar'],
                'rows' => [
                    ['Tipo / motivo', 'Lupa o F1; Enter valida el código.'],
                    ['Días / Desde–Hasta', 'Duración. Si el tipo computa hábiles, el sistema cuenta lunes a viernes.'],
                    ['Importe no cobrado', 'Pesos que no se pagan. 0 si no hay descuento (notificación, apercibimiento).'],
                    ['Comentario / causa', 'Texto del hecho (obligatorio).'],
                    ['Estado', 'Borrador → notificada → con descargo → firme. Impugnada / anulada no liquidan.'],
                ],
            ],
            'items' => [
                'Al pasar el mouse por Importe no cobrado se aclara: salario que no se paga, no una multa.',
                'Se puede bajar la carta de notificación en PDF desde la grilla.',
                'No duplique el mismo período como ausencia tipo 41 si el tipo ya genera novedad.',
            ],
        ],
        [
            'titulo' => '7. Novedad y liquidación',
            'parrafos' => [
                'Al guardar, el sistema mira el tipo. Crea o actualiza una novedad solo si se cumple todo esto:',
            ],
            'items' => [
                'El tipo tiene concepto y el tilde Genera novedad.',
                'El estado es notificada, con descargo o firme.',
                'Hay días o importe no cobrado distinto de cero.',
            ],
            'parrafos2' => [
                'La novedad usa el concepto del tipo: valor 1 = días, valor 2 = importe no cobrado. La fórmula del concepto decide cuál usa. Si cambia el tipo o anula el expediente, la novedad se anula o se actualiza sola (queda ligada al expediente).',
                'El histórico importado de Anita se grabó firme y sin novedad: es consulta, no descuento automático.',
            ],
        ],
        [
            'titulo' => '8. Reporte de sanciones',
            'herramientas_grupos' => [
                ['titulo' => 'Consulta y export', 'clave' => 'reporte'],
            ],
            'parrafos' => [
                'Pantalla: Reportes de Sueldos → Sanciones de empleados. Filtre por empresa, fechas, estado, legajo, tipo y motivo. Consulte y exporte PDF, Excel o CSV con los mismos criterios (no solo la página en pantalla).',
                'La columna Importe no cobrado es el mismo monto de la carga.',
            ],
        ],
        [
            'titulo' => '9. Histórico Anita',
            'parrafos' => [
                'Los tipos, motivos y expedientes viejos se pueden traer desde Anita (comando de sincronización, primero en modo análisis). Anita no trae el concepto de liquidación: hay que asignarlo en Tipos de sanción si quiere que las nuevas cargas descuenten.',
                'Los expedientes importados quedan firmes, sin novedad. Sirven para el legajo y el reporte, no para reliquidar el pasado.',
            ],
        ],
        [
            'titulo' => '10. Permisos',
            'parrafos' => [
                'Si falta un botón, suele ser permiso — no un error de la pantalla.',
            ],
            'tabla' => [
                'caption' => 'Permisos más usados',
                'headers' => ['Permiso', 'Para qué sirve'],
                'rows' => [
                    ['listar / crear / editar / actualizar / borrar tipo-sancion-sueldos', 'ABM de tipos'],
                    ['listar / crear / editar / actualizar / borrar motivo-sancion-sueldos', 'ABM de motivos'],
                    ['listar / crear / editar / actualizar / borrar sancion-empleado-sueldos', 'Solapa del empleado'],
                    ['listar-sancion-reporte-sueldos', 'Reporte y exportes'],
                    ['editar-empleado-sueldos', 'Entrar a la ficha y ver la solapa'],
                ],
            ],
        ],
        [
            'titulo' => '11. Preguntas frecuentes',
            'tabla' => [
                'caption' => 'Dudas habituales',
                'headers' => ['Pregunta', 'Respuesta'],
                'rows' => [
                    ['¿Dónde elijo el concepto?', 'En el tipo de sanción, no en la carga del empleado.'],
                    ['¿Qué es Importe no cobrado?', 'Salario que no se paga por la sanción. No es una multa.'],
                    ['Tildé novedad y no aparece en el recibo', 'Falta concepto, o el estado no liquida, o días e importe están en cero.'],
                    ['¿Puedo usar ausencia 41 y sanción a la vez?', 'No, si el tipo ya genera novedad: se duplicaría el descuento.'],
                    ['El tipo importado no descuenta', 'Edite el tipo: asigne concepto y tilde Genera novedad.'],
                    ['¿Qué estados liquidan?', 'Notificada, con descargo y firme. Borrador, impugnada y anulada no.'],
                ],
            ],
        ],
    ],
];
