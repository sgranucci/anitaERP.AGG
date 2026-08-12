<?php

/**
 * Manual de usuario — Reportes contables definibles.
 * Audiencia: Contaduría / gerencia / analistas sin experiencia técnica.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Reportes contables definibles',
    'version' => '1.0',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Introducción',
            'captura_id' => 'mapa_modulo',
            'parrafos' => [
                'Este manual describe el módulo de Reportes contables definibles de Anita ERP: cómo diseñar un Balance, un Estado de resultados u otro informe gerencial, ejecutarlo sobre la contabilidad del ERP, exportarlo, publicarlo, enviarlo por mail y controlarlo contra Anita Informix.',
                'Menú: Contable → Reportes contables definibles (URL /contable/reporte-definible). Las acciones visibles dependen de los permisos de su usuario.',
                'La idea es la misma que Financial Statement Version (FSV) de sistemas premium o el infomae de Anita: se define una sola vez el árbol de rubros y cuentas, y después se corre el número con los filtros del momento (empresas, período, layout, moneda).',
            ],
            'items' => [
                'El catálogo lista todos los informes: puede crear uno manual, copiarlo, traerlo de Anita o partir de una plantilla.',
                'Diseñar no cambia los números ya publicados: la publicación congela el resultado presentado.',
                'La ejecución normal lee saldos del ERP. Anita Informix entra en la importación de estructura y en el control de Paridad Anita.',
            ],
        ],
        [
            'titulo' => '2. Conceptos básicos',
            'captura_id' => 'glosario_flujo',
            'parrafos' => [
                'Antes de operar conviene tener claros estos términos. Aparecen en el catálogo, el diseñador, la ejecución y la paridad.',
            ],
            'tabla' => [
                'caption' => 'Glosario rápido',
                'headers' => ['Término', 'Significado para el operador'],
                'rows' => [
                    ['Informe / reporte definible', 'Definición de un estado contable (código, nombre, títulos, estructura de rubros).'],
                    ['Rubro', 'Línea del informe: suma de cuentas, total de hijos, fórmula o texto.'],
                    ['Código de línea (R001…)', 'Identificador de la línea; se usa en fórmulas y en alertas de ecuación.'],
                    ['Set de cuentas', 'Grupo reutilizable de cuentas/rangos que se puede colgar de varios rubros.'],
                    ['Layout', 'Diseño de columnas del informe (Actual, YTD, Plan, variación, valuación, etc.).'],
                    ['Snapshot / saldos_mes', 'Tabla mensual de saldos del ERP. Es lo que imprime el informe en períodos cerrados.'],
                    ['Publicación', 'Copia congelada de una corrida (números + filtros + notas). Se reimprime idéntica.'],
                    ['Versión de definición', 'Snapshot de la estructura (rubros/cuentas), distinto de publicar el número.'],
                    ['Paridad Anita', 'Comparación Informe vs Asientos ERP vs Anita (ctamov + subdiario).'],
                    ['Drill-down', 'Explicar una celda: rubro → cuentas → asientos → documento origen.'],
                    ['Suscripción / distribución', 'Envío automático del informe por mail en el día y hora programados.'],
                    ['Nota al pie', 'Aclaración anclada a una línea (o general), con historial de versiones.'],
                ],
            ],
        ],
        [
            'titulo' => '3. Fuente de verdad: ERP y Anita',
            'captura_id' => 'fuente_verdad',
            'parrafos' => [
                'Hasta el 31/12/2025 (configurable con MAYOR_PLANO_CUENTA_FUENTE_ERP_HASTA) la contabilidad subida al ERP es la verdad operativa: el informe se calcula sobre anitaERP. A partir de 2026, mientras no se complete la carga, la verdad para controlar es Anita Informix.',
                'Eso no cambia cómo se ejecuta el informe en el día a día (siempre lee el ERP). Cambia cómo se interpreta el control de Paridad Anita: el badge “Fuente de verdad del período” indica anitaERP o Anita según la fecha hasta de la corrida.',
            ],
            'tabla' => [
                'caption' => 'Cómo leer una diferencia en Paridad',
                'headers' => ['Período', 'Badge', 'Si hay diferencia…'],
                'rows' => [
                    ['Hasta el corte (ej. 2025)', 'anitaERP', 'Primero revise el motor ERP (Dif. motor / snapshot). Anita puede no conservar el movimiento.'],
                    ['Después del corte (ej. 2026)', 'Anita', 'Si Asientos ERP ≠ Anita, faltan asientos subidos al ERP o hay desvío de importación.'],
                ],
            ],
            'items' => [
                'Empresas en alcance operativo de este despliegue: 1, 2 y 3.',
                'El comando de paridad y la pantalla usan el mismo corte: no complica con controles cruzados adicionales.',
            ],
        ],
        [
            'titulo' => '4. Catálogo de informes',
            'captura_id' => 'catalogo',
            'herramientas_grupos' => [
                ['titulo' => 'Cabecera y alta', 'clave' => 'catalogo_cabecera'],
                ['titulo' => 'Fila del listado', 'clave' => 'catalogo_fila'],
            ],
            'parrafos' => [
                'Pantalla: Contable → Reportes contables definibles. Es el punto de entrada: filtrar, crear, importar desde Anita, abrir el diseñador o ejecutar.',
                'La tabla muestra Código, Nombre, Títulos, Tipo (Balance / Resultado / Otro), Origen (Anita / Manual / Plantilla), Estado (Publicado / Borrador), cantidad de rubros y Activo.',
            ],
            'items' => [
                'Nuevo informe: alta manual → queda en el diseñador para armar la estructura.',
                'Desde plantilla: crea una copia editable de las plantillas sembradas (Balance 9001 / EERR 9002).',
                'Traer de Anita: importa definiciones infomae / infomov / infocta (rango Desde nro. / Hasta nro.; vacío = todos).',
                'Sets de cuentas: abre el ABM de grupos reutilizables de cuentas.',
            ],
        ],
        [
            'titulo' => '5. Diseñar la estructura',
            'captura_id' => 'disenar_estructura',
            'herramientas_grupos' => [
                ['titulo' => 'Solapa Estructura', 'clave' => 'disenar_estructura'],
                ['titulo' => 'Cobertura del plan', 'clave' => 'disenar_cobertura'],
                ['titulo' => 'Cabecera, acceso y versiones', 'clave' => 'disenar_gobernanza'],
            ],
            'parrafos' => [
                'Al pulsar Diseñar estructura se abre el diseñador con solapas. La principal es Estructura: árbol de rubros a la izquierda y detalle del rubro seleccionado a la derecha.',
                'Cada rubro tiene tipo, código de línea, estilo (negrita / subrayado / ocultar si cero) y, si es de cuentas, las cuentas o rangos asignados (con signo +/− y origen Real/Plan).',
            ],
            'tabla' => [
                'caption' => 'Tipos de rubro',
                'headers' => ['Tipo', 'Qué hace'],
                'rows' => [
                    ['Suma de cuentas', 'Acumula los saldos de las cuentas (o del set) asignadas al rubro.'],
                    ['Total de hijos', 'Suma automática de los rubros hijos en el árbol.'],
                    ['Fórmula entre rubros', 'Evalúa una expresión con códigos Rnnn (ej. R001-R002).'],
                    ['Texto / título', 'Solo etiqueta; no lleva importe.'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Sintaxis de fórmulas (rubros y alertas de ecuación)',
                'headers' => ['Elemento', 'Regla'],
                'rows' => [
                    ['Referencias', 'Códigos de línea R + dígitos (R001, R050).'],
                    ['Operadores', '+ − * / y paréntesis.'],
                    ['Ejemplo EERR', 'Resultado bruto = R001-R002.'],
                    ['Ejemplo ecuación', 'Activo = Pasivo + PN → R001-(R050+R080) debe dar ~0.'],
                ],
            ],
            'items' => [
                'Puede colgar un Set de cuentas en el rubro además de cuentas sueltas.',
                'Lado de presentación Natural / Debe / Haber (invierte signo) adapta cómo se muestra el saldo.',
                'La solapa Cobertura plan muestra % asignado, huérfanas y duplicadas; con “+ Al rubro seleccionado” manda las huérfanas al rubro activo.',
            ],
        ],
        [
            'titulo' => '6. Layouts de columnas',
            'captura_id' => 'layouts',
            'herramientas_grupos' => [
                ['titulo' => 'Solapa Layouts', 'clave' => 'layouts'],
            ],
            'parrafos' => [
                'Un layout define las columnas del informe, separado de la estructura de rubros. Puede usar presets de sistema (ACTUAL, YTD, ACT_PLAN_VAR, ACT_YTD_AA, FULL_GERENCIAL) o crear layouts propios del informe.',
                'Si no elige layout al ejecutar, el sistema usa el modo legacy (columnas por período / comparativo / centros de costo).',
            ],
            'tabla' => [
                'caption' => 'Tipos de columna',
                'headers' => ['Tipo', 'Qué calcula'],
                'rows' => [
                    ['Actual (período)', 'Ventana de fechas de la ejecución.'],
                    ['YTD ejercicio', 'Desde el 01/01 del año de la fecha hasta → fecha hasta.'],
                    ['Año anterior', 'Misma ventana corrida un año atrás.'],
                    ['Período ±N meses', 'Desplaza la ventana según offset_meses.'],
                    ['Valuación', 'Misma ventana con histórico / ajustado / moneda (FX).'],
                    ['Plan (partidas)', 'Presupuesto desde partidas de gasto.'],
                    ['Variación Actual−Plan', 'Diferencia absoluta entre dos columnas.'],
                    ['Variación %', '(base−ref)/ref×100.'],
                    ['% sobre columna', 'Porcentaje de una columna sobre otra.'],
                    ['Fórmula de columnas', 'Expresión sobre keys de columna (a+b/c), no códigos R.'],
                ],
            ],
            'items' => [
                'Multi-valuación: ponga varias columnas Valuación (histórico, ajustado, USD) lado a lado en el mismo estado.',
                'Si falta cotización para un día, el sistema usa la cotización vigente (última real > 0) y avisa cuántos movimientos se convirtieron así.',
            ],
        ],
        [
            'titulo' => '7. Consolidación intercompany',
            'captura_id' => 'consolidacion',
            'herramientas_grupos' => [
                ['titulo' => 'Solapa Consolidación IC', 'clave' => 'consolidacion'],
            ],
            'parrafos' => [
                'En la ejecución marque dos o más empresas y active la consolidación. El motor aplica el % de participación vigente y las reglas de eliminación IC definidas en la solapa Consolidación IC.',
                'Sin filas de participación, cada empresa entra al 100 %. Las eliminaciones pueden aplicar a todas las empresas o a una pareja (Empresa A / Empresa B) sobre un rango de cuentas.',
            ],
            'items' => [
                'TC consolidación: Cotización del asiento o Cotización de cierre (fecha hasta).',
                'Las participaciones admiten vigencia desde/hasta (meses).',
            ],
        ],
        [
            'titulo' => '8. Ejecutar un informe',
            'captura_id' => 'ejecutar',
            'herramientas_grupos' => [
                ['titulo' => 'Filtros de ejecución', 'clave' => 'ejecutar_filtros'],
                ['titulo' => 'Después de Consultar', 'clave' => 'ejecutar_resultado'],
            ],
            'parrafos' => [
                'Desde el catálogo (play) o el botón Ejecutar del diseñador. Elija el informe, los filtros y pulse Consultar. Aparece el overlay “Calculando informe…” y luego la grilla con códigos, conceptos e importes.',
                'Puede guardar la combinación de filtros como Variante (nombre + OK) para recuperarla después.',
            ],
            'tabla' => [
                'caption' => 'Aperturas y fuentes de saldo',
                'headers' => ['Opción', 'Efecto'],
                'rows' => [
                    ['Por períodos', 'Usa el snapshot mensual (saldos_mes) vía Sumas y Saldos, salvo que el layout fuerce asientos.'],
                    ['Entre fechas (asientos)', 'Lee asiento_movimiento en el rango.'],
                    ['Base: Movimiento del período', 'Solo movimientos del mes / ventana.'],
                    ['Base: Saldo del ejercicio', 'Acumula desde el inicio del ejercicio.'],
                    ['Asientos: Sin cierre ni inflación', 'Excluye asientos de cierre e inflación (típico de estados).'],
                ],
            ],
            'items' => [
                'Export: Pdf / Excel / Csv con los mismos filtros (montos formateados según preferencia del sistema).',
                'Avisos amarillos: alertas, cobertura rota, cotizaciones faltantes o vigentes de otra fecha.',
                'Si hay una publicación previa con los mismos filtros, verá si la corrida de hoy coincide o no con la huella publicada.',
            ],
        ],
        [
            'titulo' => '9. Drill-down y mayor',
            'captura_id' => 'drill',
            'parrafos' => [
                'En la columna Detalle, la lupa del rubro abre el modal “Detalle de la celda”: lista las cuentas que componen el rubro con sus importes. Desde una cuenta puede bajar a los asientos (tope 300) y al documento origen.',
                'El drill siempre lee asientos, no el snapshot: sirve para explicar el número aunque el informe se haya impreso desde saldos_mes.',
            ],
            'items' => [
                'La columna Mayor abre el mayor plano de la cuenta en otra pestaña (si tiene permiso).',
                'Para abrir el asiento completo necesita permiso de asientos (editar o listar).',
            ],
        ],
        [
            'titulo' => '10. Publicar el número presentado',
            'captura_id' => 'publicacion',
            'herramientas_grupos' => [
                ['titulo' => 'Publicar y consultar', 'clave' => 'publicacion'],
            ],
            'parrafos' => [
                'Cuando Contaduría presenta un estado, pulse Publicar, asigne un nombre y opcionalmente una observación. El sistema congela columnas, filas, notas y un hash (huella).',
                'Reimprimir desde Publicados siempre muestra los mismos números, aunque después cambien la definición o los asientos. Editar la definición avisa cuántas publicaciones quedarían “desactualizadas” respecto de una corrida nueva, pero no altera lo ya publicado.',
            ],
            'items' => [
                'Distinga Publicar resultado (números) de Publicar versión en la solapa Versiones (estructura).',
                'Las notas viajan con la publicación pero no entran al hash: reescribir un texto no marca “no coincide” si los importes son iguales.',
            ],
        ],
        [
            'titulo' => '11. Distribución automática',
            'captura_id' => 'distribucion',
            'herramientas_grupos' => [
                ['titulo' => 'Solapa Distribución', 'clave' => 'distribucion'],
            ],
            'parrafos' => [
                'En Diseñar → Distribución programe envíos: nombre, periodicidad (Mensual / Semanal / Diaria), día, hora, período relativo (Mes anterior / Mes en curso / Fijo), formato (PDF / Excel / PDF+Excel), destinatarios y mensaje.',
                'El comando horario contable:distribuir-reportes-definibles (si CONTABLE_REPORTE_DEFINIBLE_DISTRIBUCION=true) dispara los vencidos. El botón Probar manda el mail ya, sin esperar el día.',
            ],
            'tabla' => [
                'caption' => 'Ejemplo de envío mensual a Dirección',
                'headers' => ['Campo', 'Valor sugerido'],
                'rows' => [
                    ['Cada cuánto', 'Mensual'],
                    ['Día del mes', '5'],
                    ['Hora', '07:00'],
                    ['Período de cada envío', 'Mes anterior al envío'],
                    ['Adjunto', 'PDF + Excel'],
                    ['Publicar al enviarlo', 'Sí (queda reimprimible idéntico)'],
                ],
            ],
            'items' => [
                'Los filtros del envío son los de la pantalla de ejecución al guardar; si necesita otros, ejecute con esos filtros y vuelva a capturarlos.',
                'Solo si avisos: el mail sale únicamente cuando la corrida trae alertas/advertencias.',
            ],
        ],
        [
            'titulo' => '12. Notas al pie',
            'captura_id' => 'notas',
            'herramientas_grupos' => [
                ['titulo' => 'Solapa Notas al pie', 'clave' => 'notas'],
            ],
            'parrafos' => [
                'Las notas aclaran el estado: criterio de valuación, litigio, ajuste por inflación, etc. Se cuelgan de una línea del informe o son generales. Al ejecutar, el sistema numera las llamadas en el orden en que aparecen las líneas vigentes.',
                'Cada edición versiona: el texto anterior queda en el historial. La vigencia AAAAMM desde/hasta hace que una nota solo salga en ciertos cierres (vacío = siempre).',
            ],
            'items' => [
                'Aparecen en pantalla (superíndice + bloque al pie), PDF, Excel y en el cuerpo del mail de distribución.',
                'Al copiar un informe se copian las notas vigentes buscando la línea por código.',
            ],
        ],
        [
            'titulo' => '13. Paridad Anita',
            'captura_id' => 'paridad',
            'herramientas_grupos' => [
                ['titulo' => 'Pantalla Paridad Anita', 'clave' => 'paridad'],
            ],
            'parrafos' => [
                'Desde la ejecución, Paridad Anita abre el control de tres brazos con los mismos filtros: Informe (impreso, suele venir del snapshot), Asientos ERP (recalculado) y Anita (ctamov + subdiario).',
                'Dif. motor = Informe − Asientos ERP. Dif. Anita = Asientos ERP − Anita. Use Solo diferencias y la tolerancia (default 0,05) para acotar la lectura. Exporta Pdf / Excel / Csv.',
            ],
            'tabla' => [
                'caption' => 'Cómo actuar según la columna que se mueve',
                'headers' => ['Qué no cuadra', 'Acción típica'],
                'rows' => [
                    ['Dif. motor', 'Revisar snapshot: contable:verificar-saldos-cuenta-mes o reconstruir-saldos-cuenta-mes.'],
                    ['Dif. Anita (período ERP)', 'Confirmar si Anita aún tiene el movimiento; la verdad es el ERP.'],
                    ['Dif. Anita (período Anita)', 'Completar importación de asientos al ERP.'],
                    ['Cuentas fuera de plan', 'Dar de alta la cuenta en el plan ERP o sacar el código del informe.'],
                ],
            ],
        ],
        [
            'titulo' => '14. Alertas y validaciones',
            'captura_id' => 'alertas',
            'herramientas_grupos' => [
                ['titulo' => 'Solapa Alertas', 'clave' => 'alertas'],
            ],
            'parrafos' => [
                'Las alertas se evalúan al ejecutar y aparecen como avisos. Tipos: Var % absoluta ≥ umbral (sobre rubros), Cobertura plan rota, y Ecuación contable = 0 (con tolerancia; umbral 0 usa 0,01).',
                'En Versiones, Validar definición chequea la estructura sin ejecutar: fórmulas rotas, layouts sin columna de dato, ecuaciones inválidas, etc.',
            ],
            'items' => [
                'Ejemplo de ecuación: etiqueta “Activo = Pasivo + PN”, expresión R001-(R050+R080).',
                'Las alertas de variación % no se disparan sobre filas de cuenta individual, solo sobre rubros.',
            ],
        ],
        [
            'titulo' => '15. Sets de cuentas',
            'parrafos' => [
                'Desde el catálogo, Sets de cuentas abre el ABM de grupos reutilizables. Un set tiene código, nombre y lista de cuentas o rangos (origen Real/Plan, signo).',
                'En el diseñador, el rubro puede elegir un set: al ejecutar, esas cuentas se suman a las cuentas propias del rubro. Así se mantiene un solo mantenimiento para cuentas que se repiten en varios informes.',
            ],
        ],
        [
            'titulo' => '16. Ejemplos prácticos',
            'captura_id' => 'ejemplos',
            'parrafos' => [
                'Los siguientes circuitos cubren el uso habitual de Contaduría y Gerencia.',
            ],
            'tabla' => [
                'caption' => 'Casos de uso',
                'headers' => ['Caso', 'Pasos resumidos'],
                'rows' => [
                    ['Balance mensual', 'Plantilla 9001 o import Anita → layout ACTUAL o ACT_YTD_AA → Ejecutar empresas 1–3 consolidado → Publicar → opcional Distribution día 5.'],
                    ['Estado de resultados', 'Plantilla 9002 → fórmulas de resultado bruto/operativo → layout ACT_PLAN_VAR → alertas de var %.'],
                    ['Ecuación patrimonial', 'Alerta Ecuación R001-(R050+R080) con etiqueta Activo = Pasivo + PN.'],
                    ['Multi-valuación', 'Layout con columnas Valuación histórico + ajustado + moneda USD; revisar avisos de cotización vigente.'],
                    ['Control vs Anita', 'Ejecutar nov/2025 empresa 1 → Paridad Anita → badge anitaERP → Dif. motor = 0 esperado tras rebuild de saldos.'],
                ],
            ],
        ],
        [
            'titulo' => '17. Permisos, comandos y configuración',
            'parrafos' => [
                'Permisos del menú: listar, crear, editar, actualizar, eliminar, ejecutar e importar reporte-definible. Ejecutar suele aceptar también listar. Publicar requiere ejecutar. Drill a asiento pide permiso de asientos.',
            ],
            'tabla' => [
                'caption' => 'Comandos Artisan útiles',
                'headers' => ['Comando', 'Para qué'],
                'rows' => [
                    ['contable:importar-reportes-definibles', 'Trae definiciones Anita (infomae*).'],
                    ['contable:sembrar-plantillas-reporte-definible', 'Crea plantillas Balance/EERR.'],
                    ['contable:smoke-reporte-definible {id}', 'Prueba rápida del motor.'],
                    ['contable:paridad-reporte-definible {id}', 'Paridad por consola.'],
                    ['contable:distribuir-reportes-definibles', 'Envíos programados (horario).'],
                    ['contable:verificar-saldos-cuenta-mes', 'Integridad del snapshot (diario).'],
                    ['contable:reconstruir-saldos-cuenta-mes', 'Rebuild del snapshot desde asientos.'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Variables de entorno relevantes',
                'headers' => ['Variable', 'Efecto'],
                'rows' => [
                    ['MAYOR_PLANO_CUENTA_FUENTE_ERP_HASTA', 'Corte verdad ERP vs Anita (default 2025-12-31).'],
                    ['CONTABLE_REPORTE_DEFINIBLE_DISTRIBUCION', 'Habilita el job de mails automáticos.'],
                    ['CONTABLE_SALDOS_CUENTA_MES_OBSERVER', 'Mantiene el snapshot al grabar asientos.'],
                    ['CONTABLE_SALDOS_INTEGRIDAD_*', 'Horario, mail y ventana del control nightly.'],
                    ['EXPORT_FORMATO_NUMERO', 'auto / ar / intl para montos en Excel/CSV.'],
                ],
            ],
        ],
        [
            'titulo' => '18. Preguntas frecuentes',
            'parrafos' => [
                '¿Por qué el informe no coincide con Anita en 2025? Porque la verdad de 2025 es el ERP. Use Paridad y mire Dif. motor; si el snapshot está desfasado, reconstruya saldos.',
                '¿Por qué en 2026 Anita tiene más movimientos que el ERP? Todavía no se subió el resto de la contabilidad. La verdad del control es Anita hasta completar la carga.',
                '¿Puedo cambiar la definición después de publicar? Sí. Lo publicado no cambia; la próxima corrida puede avisar que ya no coincide con la huella.',
                '¿Los montos del Excel salen sin formato? Deben salir con máscara de miles/decimales (preferencia EXPORT_FORMATO_NUMERO). Si abre un archivo viejo generado antes del ajuste, vuelva a exportar.',
                '¿Quién recibe el mail automático? Los destinatarios de la suscripción. Use Probar para verificar con un mail real antes de dejarlo en producción.',
                '¿Las notas se renumeran solas? Sí: al ejecutar, solo cuentan las notas vigentes de líneas visibles, en el orden del informe.',
            ],
        ],
        [
            'titulo' => '19. Buenas prácticas',
            'items' => [
                'Diseñe con códigos de línea estables (R001…): las fórmulas, alertas y notas dependen de ellos.',
                'Valide la definición antes de presentar; cubra el plan (sin huérfanas) si el informe es formal.',
                'Publique el resultado que se presenta al directorio; no confíe solo en “volver a ejecutar”.',
                'Programe la distribución el día 5 con período “mes anterior” y publique al enviar.',
                'Corra Paridad Anita en empresas 1–3 al cerrar el mes; actúe según el badge de fuente de verdad.',
                'Si Dif. motor aparece de golpe, revise imports masivos de Anita y el job de integridad de saldos_mes.',
            ],
        ],
    ],
];
