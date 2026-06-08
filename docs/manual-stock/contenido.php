<?php

/**
 * Contenido del manual de usuario — Anita ERP / Recuento de inventario (Stock).
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Recuento de inventario (Stock)',
    'version' => '1.1',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Introducción',
            'parrafos' => [
                'El módulo Recuento de inventario permite documentar conteos físicos en un depósito, compararlos con el saldo registrado en Anita ERP y, al cerrar, generar movimientos de stock de ajuste (sobrantes y faltantes).',
                'Un recuento es un documento con cabecera (fecha, depósito, empresa, comentario) y líneas (artículo, saldo del sistema al momento de la carga, cantidad contada). El sistema calcula la diferencia por línea y, según el modo de cierre elegido, determina qué ajuste aplicar en stock.',
                'El acceso principal es Stock → Recuento de inventario (ruta stock/recuento). Las acciones visibles dependen de los permisos del rol (listar, crear, editar, cerrar, anular, etc.).',
            ],
            'items' => [
                'No modifica stock hasta que se ejecuta un cierre (parcial o total).',
                'Tras el alta, el recuento queda en estado PENDIENTE y puede editarse hasta cerrarse o anularse.',
                'Los ajustes de cierre se registran como movimiento de stock con tipos RCAJP (sobrante) y RCAJN (faltante); la anulación de cierre usa RCAJR (reverso).',
            ],
        ],
        [
            'titulo' => '2. Permisos y navegación',
            'parrafos' => [
                'El menú lateral muestra Recuento de inventario si el rol tiene acceso al ítem de menú stock/recuento. Cada operación exige un permiso específico por código (slug).',
                'El usuario debe tener depósitos autorizados según la configuración de seguridad de stock; el sistema valida que el depósito del recuento sea operable para quien lo crea o edita.',
            ],
            'tabla' => [
                'caption' => 'Permisos principales del recuento',
                'headers' => ['Permiso (slug)', 'Descripción'],
                'rows' => [
                    ['listar-recuento', 'Ver listado y exportar'],
                    ['crear-recuento', 'Alta de recuento'],
                    ['editar-recuento / actualizar-recuento', 'Modificar cabecera y líneas'],
                    ['ver-recuento', 'Consultar detalle, historial y opciones de cierre'],
                    ['borrar-recuento', 'Eliminar recuento en estado PENDIENTE'],
                    ['suspender-recuento / reactivar-recuento', 'Pausar o reanudar el trabajo de conteo'],
                    ['anular-recuento', 'Anular documento sin impacto en stock'],
                    ['cerrar-recuento-parcial / cerrar-recuento-total', 'Generar ajustes de stock al cerrar'],
                    ['anular-cierre-recuento', 'Revertir movimiento de cierre y reabrir el recuento'],
                    ['imprimir-recuento', 'PDF del documento'],
                    ['importar-recuento', 'Carga masiva desde Excel/CSV'],
                    ['recuento-aleatorio', 'Sortear artículos para conteo muestral'],
                ],
            ],
        ],
        [
            'titulo' => '3. Listado de recuentos',
            'herramientas_grupos' => [
                ['titulo' => 'Barra superior y filtros', 'clave' => 'recuento_listado', 'incluir_listado' => true],
            ],
            'parrafos' => [
                'Pantalla principal del módulo: muestra todos los recuentos con código, fecha, depósito, empresa, usuario, tipo, estado y cantidad de líneas.',
                'Dispone de filtros inteligentes (panel colapsable) y búsqueda rápida en la barra superior. La búsqueda tolera errores de tipeo cuando el panel está cerrado.',
                'Desde el listado puede exportar a PDF, Excel o CSV respetando los filtros activos.',
            ],
            'items' => [
                'Ver detalle: ícono ojo — abre la ficha de consulta con historial y opciones de cierre.',
                'Editar: ícono lápiz — solo si el recuento está en PENDIENTE o SUSPENDIDO y el usuario tiene permiso.',
                'Eliminar: ícono cruz roja — solo en estado PENDIENTE.',
            ],
        ],
        [
            'titulo' => '4. Alta y edición del recuento',
            'herramientas_grupos' => [
                ['titulo' => 'Formulario de alta', 'clave' => 'recuento_form_cabecera'],
                ['titulo' => 'Grilla de líneas de conteo', 'clave' => 'recuento_form_items'],
            ],
            'parrafos' => [
                'Al crear un recuento (botón Nuevo registro) se completa la cabecera: empresa (según sesión), depósito (consulta modal), fecha del recuento y comentario opcional.',
                'La fecha del recuento es un dato de negocio importante: define el período al que corresponde el conteo y, según el modo de cierre, puede usarse como fecha del movimiento de ajuste.',
                'En la grilla de líneas se agregan artículos mediante consulta (lupa), código SKU o herramientas masivas (aleatorio, Excel). Al elegir cada artículo, el sistema consulta el saldo vigente del depósito y lo muestra en la columna Saldo dep.; ese valor queda guardado en la línea como snapshot (saldo_sistema).',
                'La columna Contado registra la cantidad física contada. Diferencia = Contado − Saldo dep. (referencia visual al cargar; el ajuste real al cerrar depende del modo elegido en la pantalla Ver).',
                'Tras guardar un alta, el sistema redirige a la edición para continuar cargando líneas o archivos adjuntos.',
                'Un mismo artículo no puede cargarse dos veces: al elegirlo por SKU, modal de consulta o importación, el sistema lo detecta al instante, muestra un aviso, resalta la línea ya existente y enfoca la cantidad contada para corregirla. En base de datos también existe la restricción única recuento + artículo.',
            ],
            'items' => [
                'Agregar fila: botón + al pie de la grilla.',
                'Eliminar fila: ícono papelera en cada línea.',
                'Artículo duplicado: si intenta cargar un SKU ya presente, la fila nueva se descarta (o no se agrega), aparece notificación amarilla y el cursor va a la línea original.',
                'Consulta artículo (lupa): modal de búsqueda de artículos filtrado por empresa.',
                'Movimientos (lista): abre en pestaña nueva el listado paginado de movimientos del artículo en el depósito del recuento.',
                'Enlace editar artículo: abre la ficha del artículo en otra pestaña (si tiene permiso).',
            ],
        ],
        [
            'titulo' => '5. Ver detalle del recuento',
            'herramientas_grupos' => [
                ['titulo' => 'Pantalla de detalle', 'clave' => 'recuento_ver'],
            ],
            'parrafos' => [
                'La vista Ver concentra la información de consulta: datos de cabecera, líneas con saldo/contado/diferencia, historial de estados, archivos adjuntos y — si el recuento está pendiente o suspendido — el panel de cierre.',
                'Si el recuento ya fue cerrado, se muestran los identificadores del movimiento de cierre, del movimiento de anulación (si hubo) y el modo de cierre utilizado.',
                'La nota bajo la grilla aclara que la columna Diferencia a ajustar usa el saldo guardado al cargar cada línea; el ajuste efectivo al cerrar puede diferir si se elige cerrar a fecha del recuento.',
            ],
        ],
        [
            'titulo' => '6. Cierre de inventario: modos y filosofía',
            'captura_id' => 'recuento_cierre',
            'parrafos' => [
                'El cierre es el momento en que el recuento deja de ser un borrador y genera movimientos de stock. Antes de confirmar debe elegirse el modo de ajuste: define contra qué saldo se calcula la diferencia y con qué fecha se registran los movimientos.',
                'Filosofía general: el conteo físico (cantidad contada) es la verdad operativa que el usuario declara; el sistema compara ese valor con un saldo de referencia y genera entradas o salidas para igualar. La pregunta clave es: ¿referencia histórica (fecha del recuento) o referencia actual (hoy)?',
            ],
            'tabla' => [
                'caption' => 'Comparación de modos de cierre',
                'headers' => ['Aspecto', 'A fecha del recuento', 'Al saldo actual'],
                'rows' => [
                    ['Saldo de referencia', 'Suma de movimientos con fecha ≤ fecha del recuento', 'Saldo vigente hoy en el depósito'],
                    ['Fórmula del ajuste', 'delta = contado − saldoAFecha', 'delta = contado − saldoVigente'],
                    ['Fecha del movimiento', 'Fecha del recuento', 'Fecha de hoy'],
                    ['Cuándo usarlo', 'Inventario de cierre de período (ej. al 31/05) con movimientos posteriores al conteo', 'Conteo de lo que hay físicamente ahora; igualar sistema al presente'],
                    ['Efecto en stock posterior', 'El stock vigente queda coherente con “conteo correcto en esa fecha + movimientos posteriores”', 'Ignora la reconstrucción histórica; ajusta directamente al conteo actual'],
                ],
            ],
            'items' => [
                'Ejemplo modo fecha: recuento al 31/05 con 10 unidades contadas; saldo a esa fecha era 8; hubo una salida de 2 el 02/06. Cierre a fecha del recuento ajusta +2 con fecha 31/05. El saldo hoy reflejará 10 − 2 = 8 si no hubo más movimientos.',
                'Ejemplo modo actual: mismo escenario pero cierre al saldo actual compara contra 6 (8−2); el ajuste sería +4 con fecha de hoy.',
                'Selección por defecto: si la fecha del recuento es anterior a hoy, se preselecciona A fecha del recuento; si es hoy, Al saldo actual.',
                'El modo elegido queda registrado en el campo modo_cierre del documento y visible en el detalle tras el cierre.',
            ],
        ],
        [
            'titulo' => '7. Herramientas de carga masiva',
            'herramientas_grupos' => [
                ['titulo' => 'Recuento aleatorio', 'clave' => 'recuento_aleatorio'],
                ['titulo' => 'Importar Excel', 'clave' => 'recuento_importar'],
            ],
            'parrafos' => [
                'Recuento aleatorio: sortea N artículos del depósito para conteos muestrales. Prioriza artículos con depósito de entrega igual al del recuento; si no hay, usa artículos con saldo o movimientos en ese depósito. Requiere permiso recuento-aleatorio.',
                'Importar Excel: permite subir planilla con columnas configurables (SKU, cantidad contada, detalle opcional). Tras la importación las líneas quedan en sesión y deben guardarse desde el formulario de edición. También disponible modal rápido en la grilla. Si el archivo repite un SKU, la importación se rechaza con mensaje explícito.',
                'Tipo del documento: MANUAL (carga normal), ALEATORIO (generado por sorteo) o IMPORTADO (carga masiva).',
            ],
        ],
        [
            'titulo' => '8. Consulta de movimientos de stock',
            'herramientas_grupos' => [
                ['titulo' => 'Listado de movimientos', 'clave' => 'recuento_movimientos'],
            ],
            'parrafos' => [
                'Desde cada línea del formulario (ícono lista) o desde consultas relacionadas se abre un listado paginado de movimientos del artículo en el depósito del recuento.',
                'Muestra fecha, tipo de transacción, concepto, entrada y salida (según signo), saldo acumulado y usuario. Permite exportar a PDF, Excel o CSV.',
                'Esta consulta ayuda a entender discrepancias antes del cierre y a decidir el modo de ajuste adecuado cuando hubo movimientos entre la fecha del recuento y la fecha de cierre.',
            ],
        ],
        [
            'titulo' => '9. Estados y ciclo de vida',
            'captura_id' => 'circuito_estados',
            'parrafos' => [
                'Cada cambio de estado queda auditado en el historial (fecha, usuario, observación).',
            ],
            'tabla' => [
                'caption' => 'Estados del recuento',
                'headers' => ['Estado', 'Significado', 'Acciones típicas'],
                'rows' => [
                    ['PENDIENTE', 'Documento en curso, editable', 'Editar, suspender, anular, cerrar parcial/total, eliminar'],
                    ['SUSPENDIDO', 'Conteo pausado', 'Reactivar, anular, cerrar parcial/total'],
                    ['CERRADO_PARCIAL', 'Ajustó solo líneas contadas con diferencia', 'Anular cierre (revierte movimiento), imprimir PDF'],
                    ['CERRADO_TOTAL', 'Ajustó líneas contadas y artículos con saldo sin contar (contado = 0)', 'Anular cierre, imprimir PDF'],
                    ['ANULADO', 'Documento cancelado sin impacto en stock', 'Solo consulta'],
                ],
            ],
            'items' => [
                'Cierre parcial: genera ajustes solo para las líneas cargadas donde contado ≠ saldo de referencia (según modo). Artículos del depósito no listados no se modifican.',
                'Cierre total: además de las líneas cargadas, considera todos los artículos con saldo en el depósito; los no contados se tratan como contado = 0.',
                'Anular cierre: crea movimiento reverso (RCAJR), limpia modo_cierre y vuelve a PENDIENTE.',
                'Anular recuento: solo desde PENDIENTE o SUSPENDIDO; no revierte stock porque aún no hubo cierre.',
            ],
        ],
        [
            'titulo' => '10. Impresión, archivos y exportaciones',
            'herramientas_grupos' => [
                ['titulo' => 'Exportaciones e impresión', 'clave' => 'recuento_export'],
            ],
            'parrafos' => [
                'PDF del recuento: desde Ver o Editar (permiso imprimir-recuento). Incluye cabecera, líneas y totales de diferencia.',
                'Export del listado index: PDF legal apaisado, Excel o CSV con los filtros aplicados.',
                'Archivos adjuntos: en edición puede agregar documentación de soporte (planillas firmadas, fotos). Se conservan al actualizar si se marca conservar en cada archivo existente.',
            ],
        ],
    ],
];
