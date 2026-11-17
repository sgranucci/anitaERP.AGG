<?php

/**
 * Guía rápida operativa — liquidación de sueldos y jornales.
 * Complementa el manual completo (docs/manual-sueldos).
 */

return [
    'titulo' => 'Guía rápida',
    'subtitulo' => 'Anita ERP — Liquidación de sueldos y jornales',
    'empresa' => env('EMPRESA', 'AGG'),
    'version' => '1.1',
    'secciones' => [
        [
            'titulo' => '1. Para qué sirve esta guía',
            'parrafos' => [
                'Recorrido corto del cierre de un período: legajo → novedades (manual o masivas) → calcular corrida → revisar recibos → cerrar. El detalle de conceptos, elegibilidad, LSD y reportes está en el Manual de Sueldos y jornales.',
                'Menú: Módulo Sueldos y Jornales. Lo que ve cada usuario depende de sus permisos.',
            ],
            'tabla' => [
                'caption' => 'Orden habitual del mes',
                'headers' => ['Paso', 'Pantalla', 'Resultado'],
                'rows' => [
                    ['1', 'Empleados', 'Legajos activos, categoría, grupos, flag confidencial'],
                    ['2', 'Novedades / Importar', 'Horas, días, importes del período'],
                    ['3', 'Corridas de liquidación', 'Crear corrida → Calcular'],
                    ['4', 'Resultado de la corrida', 'Revisar recibos (incluye confidencial si tiene permiso)'],
                    ['5', 'Cerrar corrida', 'Congela para LSD y asiento'],
                    ['—', 'Si hubo egresos', 'Baja en el legajo + corrida tipo Liquidación final (capítulo 7)'],
                ],
            ],
        ],
        [
            'titulo' => '2. Revisar el legajo',
            'parrafos' => [
                'Pantalla: Personal → Empleados. Workbench con vistas, consulta avanzada y chips de estado (Activos, Provisorios, Bajas).',
                'Antes de liquidar: categoría, obra social, sindicato, grupos de conceptos y, si aplica, tilde Confidencial. El motor liquida la confidencial en la misma corrida; sin permiso ver-confidencial no se ven esos recibos ni sus totales.',
            ],
            'items' => [
                'Altas provisorias: autorizar antes de que entren a la corrida de activos.',
                'Baja laboral: solapa Historia (no elimina el legajo ni arma sola la definitiva; ver capítulo 7).',
                'Solapa Liquidación: set efectivo y “Por qué no entraron”.',
            ],
        ],
        [
            'titulo' => '3. Cargar novedades',
            'parrafos' => [
                'Pantalla: Liquidación → Novedades de liquidación (también solapa Novedades del empleado).',
                'Una novedad vigente suma el concepto al set (después pasa elegibilidad) o aporta cantidad/importe a la fórmula. Valor 1 suele ser cantidad (horas/días); Valor 2 suele ser importe.',
            ],
            'tabla' => [
                'caption' => 'Alta manual — ejemplo horas extras',
                'headers' => ['Campo', 'Qué poner'],
                'rows' => [
                    ['Empleado', 'Legajo (lupa / F1)'],
                    ['Concepto', 'Código activo (ej. horas extras)'],
                    ['Período o desde–hasta', 'La quincena o mes que se liquida'],
                    ['Cantidad / Importe', 'Según lo que lea la fórmula del concepto'],
                    ['Estado', 'Distinto de anulada'],
                ],
            ],
            'items' => [
                'No vuelva a cargar a mano una novedad que ya generó una sanción.',
                'Fuera de vigencia no entra a esa corrida.',
            ],
        ],
        [
            'titulo' => '4. Carga masiva de novedades',
            'parrafos' => [
                'Desde el listado de novedades → Importar (o ruta Liquidación → Novedades → Importar). Sirve para Excel/CSV de muchas horas, días o importes de una sola vez.',
            ],
            'items' => [
                'Baje la plantilla desde el botón Plantilla.',
                'Elegí si el archivo va Por título de encabezado o Por número de columna (sin título), como en otras importaciones del ERP.',
                'Suba el archivo y usá Vista previa: si los nombres no coinciden (o no hay títulos), mapee Legajo y Concepto a las columnas.',
                'Revise el preview (filas verdes/rojas) antes de confirmar.',
                'Con fecha desde, la novedad puede repetirse en cada corrida mientras esté vigente.',
                'Después de importar, recién ahí Calcule la corrida.',
            ],
            'tabla' => [
                'caption' => 'Checklist importación',
                'headers' => ['Control', 'OK si…'],
                'rows' => [
                    ['Empresa / legajos', 'Existen en el ERP'],
                    ['Códigos de concepto', 'Activos y con el mismo código que el Excel'],
                    ['Período', 'Cubre la corrida que va a calcular'],
                    ['Preview sin bloqueantes', 'Puede confirmar el lote'],
                ],
            ],
        ],
        [
            'titulo' => '5. Armar y calcular la corrida',
            'parrafos' => [
                'Pantalla: Liquidación → Corridas de liquidación. Crear: empresa, período, tipo (mensual, quincena, SAC, vacaciones, final, etc.) y alcance de recibos.',
                'Calcular regenera los recibos con el motor ERP (nómina normal y legajos confidenciales en la misma corrida). Estados editables: borrador, calculada, revisada.',
            ],
            'tabla' => [
                'caption' => 'Tipos que más se usan',
                'headers' => ['Tipo', 'Uso'],
                'rows' => [
                    ['Mensual', 'Haberes del mes'],
                    ['1ra / 2da quincena', 'Jornales'],
                    ['SAC / Vacaciones / Final', 'Especiales (antes que la mensual para LSD)'],
                ],
            ],
            'items' => [
                'Paridad Anita (auxconf) en el resultado es solo migración/contraste, no el armado operativo.',
                'Si no hay conceptos activos, Calcular avisa y no arma recibos.',
                'La corrida tipo Liquidación final es la definitiva del egreso: ver capítulo 7 (dar de baja no la genera sola).',
            ],
        ],
        [
            'titulo' => '6. Revisar recibos y cerrar',
            'parrafos' => [
                'Ver recibos abre el resultado de la corrida: legajo, importes, detalle por concepto. PDF de un recibo o de toda la corrida según permiso.',
                'Cuando esté correcta: Cerrar (candado). Congela para Libro de Sueldos Digital y asiento. Reabrir solo con permiso.',
            ],
            'items' => [
                'Totales en pantalla respetan el permiso de nómina confidencial.',
                'Contabilizar / solicitud de pago: después del cierre, según el circuito de la empresa.',
                'LSD: especiales (E) —vacaciones, SAC, final— antes que la mensual; un TXT = una liquidación cerrada.',
            ],
        ],
        [
            'titulo' => '7. Baja del empleado y liquidación final',
            'parrafos' => [
                'Dar de baja y liquidar la definitiva son dos pasos distintos. La baja no arma sola la corrida final ni borra el legajo.',
            ],
            'tabla' => [
                'caption' => 'Qué hace cada acción',
                'headers' => ['Acción', 'Qué es', '¿Arma recibos?'],
                'rows' => [
                    ['Dar de baja', 'Cambia el estado del legajo a Baja y cierra la historia de ingresos/egresos', 'No'],
                    ['Corrida tipo Liquidación final', 'Liquida a los egresados del período', 'Sí'],
                    ['Eliminar legajo', 'Borrado físico (anulación); no es la baja laboral', 'No'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Paso a paso del egreso',
                'headers' => ['Paso', 'Dónde', 'Qué hacer'],
                'rows' => [
                    ['1', 'Novedades', 'Cargar lo del egreso (días, vacaciones no gozadas, etc., según conceptos)'],
                    ['2', 'Empleado → Historia', 'Dar de baja: fecha de egreso, motivo y comentario. Permiso baja-empleado-sueldos'],
                    ['3', 'Corridas → Crear', 'Tipo Liquidación final, período = mes del egreso (opcional: filtrar por motivo)'],
                    ['4', 'Calcular → Revisar → Cerrar', 'El motor toma solo legajos en Baja (o con fecha de egreso) de ese período'],
                    ['5', 'Mensual del mes', 'Después de la final (para LSD: especial E antes que la mensual)'],
                ],
            ],
            'items' => [
                'En la baja, el legajo sigue existiendo: aparece en el chip Bajas y se puede reincorporar.',
                'Las corridas mensuales/quincenas solo liquidan activos; por eso el egreso necesita la corrida final.',
                'No confundir con Eliminar en el listado (anulación física del legajo).',
            ],
        ],
        [
            'titulo' => '8. Dónde profundizar',
            'parrafos' => [
                'Manual completo: Sueldos → Manual (o Centro de ayuda → Sueldos y jornales). Ahí están elegibilidad, grupos, sanciones, LSD y reportes definibles.',
            ],
            'tabla' => [
                'caption' => 'Permisos mínimos del circuito',
                'headers' => ['Acción', 'Permiso típico'],
                'rows' => [
                    ['Ver / editar legajo', 'listar / editar-empleado-sueldos'],
                    ['Dar de baja / reincorporar', 'baja-empleado-sueldos'],
                    ['Novedades e importar', 'novedad-sueldos (+ importar si aplica)'],
                    ['Calcular / cerrar corrida', 'editar-liquidacion-sueldos'],
                    ['Ver recibos confidenciales', 'ver-confidencial-recibo-sueldos'],
                    ['Paridad Anita confidencial', 'importar-liquidacion-confidencial-sueldos'],
                ],
            ],
        ],
    ],
];
