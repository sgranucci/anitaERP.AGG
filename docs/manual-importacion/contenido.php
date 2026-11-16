<?php

/**
 * Manual de usuario — Importaciones / comercio exterior (carpeta, landed cost, nacionalización).
 * Audiencia: compras internacionales, contabilidad de costos, stock/recepción, compliance fiscal.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Importaciones / comercio exterior',
    'version' => '1.3',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Qué problema resuelve',
            'parrafos' => [
                'Una importación no es solo una orden de compra: es un expediente que junta OC, embarque, gastos de flete/seguro/aduana, documentos, despacho y, al final, el costo real de la mercadería puesta en depósito (landed cost). Sin una carpeta única, esos datos viven en mails, planillas y asientos sueltos.',
                'El módulo de Importaciones de Anita ERP centraliza cada operación en una carpeta (`importacion`). Desde ahí se asocian órdenes de compra, se cargan embarques y gastos, se calcula el prorrateo híbrido (inventariable vs gasto), se nacionaliza (lote + recepción COM) y se sincroniza el Libro IVA de importaciones (comprobante 066).',
                'Menú: Compras → Importaciones. Rutas típicas: `compras/importacion`, `compras/importacion/crear`, `compras/importacion/{id}/editar`, `compras/importacion/cashflow`. Las acciones visibles dependen de los permisos del usuario (listar / crear / actualizar / nacionalizar / cerrar costos).',
            ],
            'items' => [
                'Una carpeta = un expediente de importación con código propio (IMP-AAAA-NNNN si no lo carga a mano).',
                'Landed cost híbrido: parte de los gastos capitaliza el stock; otra parte va a resultado según el tipo de gasto.',
                'Nacionalizar genera o vincula el lote de despacho y deja trazabilidad hacia recepción/COM.',
                'Checklist documental y sync al Libro IVA cierran el circuito de compliance.',
            ],
        ],
        [
            'titulo' => '2. El circuito en ocho pasos',
            'parrafos' => [
                'El recorrido recomendado, desde que se abre la carpeta hasta que los costos quedan cerrados.',
            ],
            'tabla' => [
                'caption' => 'De la OC al depósito',
                'headers' => ['Paso', 'Quién', 'Qué pasa'],
                'rows' => [
                    ['1. Abrir carpeta', 'Compras / Importaciones', 'Alta con empresa, proveedor, moneda, Incoterm y estado Borrador o Abierta.'],
                    ['2. Asociar OC', 'Compras', 'Solapa Órdenes de compra: lupa/F1 o número + Enter. Pueden ser varias OC del mismo embarque.'],
                    ['3. Embarques', 'Operaciones', 'BL/AWB/CRT, contenedor, buque/vuelo, puertos y fechas de embarque/arribo.'],
                    ['4. Gastos estimados', 'Compras / Costos', 'Flete, seguro, thc, despachante, etc. (estimado). Sirven para simular y proyectar cashflow.'],
                    ['5. Documentos', 'Operaciones', 'Adjuntos (factura comercial, packing list, póliza, etc.) y checklist.'],
                    ['6. Gastos reales', 'Cuentas a pagar', 'Al asociar comprobantes o cargar reales, el motor recalcula estimado vs real.'],
                    ['7. Nacionalizar', 'Stock / Compras', 'Nro. despacho + país + fecha → lote y vínculo a recepción COM.'],
                    ['8. Cerrar costos', 'Contabilidad', 'Prorrateo final, coeficiente de embarque y estado Costos cerrados (sin más gastos).'],
                ],
            ],
        ],
        [
            'titulo' => '3. Conceptos básicos',
            'parrafos' => [
                'Estos términos aparecen en pantallas, badges y reportes.',
            ],
            'tabla' => [
                'caption' => 'Glosario',
                'headers' => ['Término', 'Significado para el operador'],
                'rows' => [
                    ['Carpeta', 'Expediente de importación (cabecera + solapas).'],
                    ['Landed cost', 'Costo de la mercadería puesta en el país (FOB + gastos imputables).'],
                    ['Inventariable', 'Gasto que capitaliza el valor de stock (entra al coeficiente).'],
                    ['Gasto (naturaleza contable)', 'Gasto que va a resultado, no al costo del artículo.'],
                    ['Estimado / Real', 'Presupuesto del gasto vs lo facturado/pagado.'],
                    ['Prorrateo', 'Reparto del pool de gastos inventariables sobre las partidas (valor, peso, volumen o cantidad).'],
                    ['Coeficiente de embarque', 'Factor landed / base FOB del embarque (útil para validar).'],
                    ['Nacionalización', 'Paso a stock local: despacho + lote (+ COM).'],
                    ['Incoterm', 'Condición comercial (FOB, CIF, EXW…); define qué gastos son del importador.'],
                    ['Libro IVA 066', 'Comprobante de importación para el libro de IVA compras.'],
                    ['Cashflow', 'Proyección de vencimientos de gastos de carpetas abiertas.'],
                    ['Simulador', 'Cálculo pre-OC de landed estimado sin grabar carpeta.'],
                ],
            ],
        ],
        [
            'titulo' => '4. Estados de la carpeta',
            'parrafos' => [
                'El estado resume dónde está la operación. No todas las transiciones están permitidas: el sistema valida el salto.',
            ],
            'tabla' => [
                'caption' => 'Estados',
                'headers' => ['Estado', 'Significa', 'Puede pasar a'],
                'rows' => [
                    ['Borrador', 'Cargada, aún no operativa.', 'Abierta / Anulada'],
                    ['Abierta', 'En gestión (OC, gastos estimados).', 'En tránsito / En puerto / Anulada'],
                    ['En tránsito', 'Embarcada, en viaje.', 'En puerto / Nacionalizada / Anulada'],
                    ['En puerto', 'Arribó; pendiente de despacho.', 'Nacionalizada / Anulada'],
                    ['Nacionalizada', 'Despacho/lote hecho; costos aún abiertos.', 'Costos cerrados'],
                    ['Costos cerrados', 'Prorrateo definitivo; no admite más gastos.', '—'],
                    ['Anulada', 'Expediente cancelado.', '—'],
                ],
            ],
            'items' => [
                'Nacionalizar solo aparece si el estado actual permite el salto a Nacionalizada.',
                'Cerrar costos exige estado Nacionalizada y permiso `cerrar-costos-importacion`.',
                'Costos cerrados y Anulada no son editables para gastos.',
            ],
        ],
        [
            'titulo' => '5. Listado y consultas (workbench)',
            'parrafos' => [
                'El index de Importaciones usa el workbench del ERP: selector de vistas, Diseñar vista (columnas, anchos, alineación, orden y agrupación), Consulta avanzada (QBE) con grupos AND/OR/NOT, búsqueda rápida y chips externos de estado y empresa (quedan en AND, por encima del QBE).',
                'Los cortes (Pack C) aparecen cuando agrupa: muestran conteo y suma de importes del universo filtrado. El export PDF / Excel / CSV respeta filtros, orden, agrupación y columnas visibles de la vista abierta.',
                'Use el botón Manual en la barra del listado (o en crear/editar) para abrir este documento. Cashflow está en la barra del workbench.',
            ],
            'items' => [
                'Chips de estado (Todos, Borrador, Abierta…) y de empresa quedan por encima del panel de filtros.',
                'La caja de texto busca en código, proveedor y observación; el panel abre criterios por campo.',
                'Exportar respeta el filtro activo (no solo la página visible).',
            ],
        ],
        [
            'titulo' => '6. Alta y edición de la carpeta',
            'parrafos' => [
                'El formulario usa solapas. Enter avanza al campo siguiente; no graba. Guardar / Actualizar es un clic en el pie.',
                'Proveedor, moneda e Incoterm se eligen con código + lupa/F1 (modal de consulta), igual que en el resto del ERP. No hay selects cargados con toda la tabla.',
            ],
            'tabla' => [
                'caption' => 'Solapas',
                'headers' => ['Solapa', 'Contenido'],
                'rows' => [
                    ['Cabecera', 'Empresa, código, proveedor, moneda, cotización, Incoterm, estado, fechas, observación.'],
                    ['Órdenes de compra', 'Grilla de OC asociadas; lupa o N° OC + Enter.'],
                    ['Embarques', 'Documentos de transporte y fechas; botón + Embarque.'],
                    ['Gastos', 'Tipo por código/lupa (F1); factura CP por modal (precarga importe/fecha/cotiz. y fuerza naturaleza Real); referencia.'],
                    ['Despachos', 'Lotes/despachos vinculados; lupa o N° despacho + Enter.'],
                    ['Documentos', 'Galería de adjuntos + renglones para subir archivos nuevos.'],
                    ['Checklist', 'Ítems documentales (solo en edición); marcar cumplido.'],
                    ['Landed cost', 'Totales, calcular/cerrar costos, preview de prorrateo, sync Libro IVA.'],
                ],
            ],
            'items' => [
                'Código vacío → el sistema asigna IMP-AAAA-NNNN al guardar.',
                'Al quitar todas las OC o lotes y actualizar, se sincroniza la asociación (queda vacía).',
                'Los adjuntos existentes se conservan con el hidden de la tarjeta; Quitar los saca al guardar.',
            ],
        ],
        [
            'titulo' => '7. Landed cost híbrido',
            'parrafos' => [
                'Cada tipo de gasto declara si es inventariable o de resultado. El motor (`ImportacionLandedCostService`) toma los gastos (estimado o real según la corrida), arma el pool inventariable y lo prorratea sobre las partidas de las OC asociadas (valor, cantidad, peso o volumen según configuración del tipo).',
                'Los gastos no inventariables no entran al coeficiente ni al costo unitario de stock: van a las cuentas automáticas de gastos de importación configuradas en Cuentas automáticas (`importacion.gastos_*`).',
            ],
            'items' => [
                'Calcular costos: genera un prorrateo (aplicado o pendiente según flags) y actualiza totales de la carpeta.',
                'Preview: muestra líneas SKU / base / importe / inventariable sin cerrar.',
                'Cerrar costos: deja el estado en Costos cerrados; confirme el diálogo — no se podrán cargar más gastos.',
                'El coeficiente de embarque aparece en la cabecera cuando hay cálculo.',
            ],
        ],
        [
            'titulo' => '8. Nacionalización y stock',
            'parrafos' => [
                'Cuando la mercadería está lista para ingresar, use el bloque Nacionalizar (permiso `nacionalizar-importacion`). Indique número de despacho, país (código + lupa/F1) y fecha de ingreso. Opcionalmente vincule una recepción COM con número + Enter o lupa (mismo patrón de consulta modal).',
                'El servicio crea o asocia el lote (`lote.importacion_id` / pivot de la carpeta) y deja la carpeta en estado Nacionalizada. Desde ahí el circuito de stock/recepción sigue con las herramientas habituales (COM, kardex, Ferli si aplica).',
            ],
            'items' => [
                'Sin OC asociadas con partidas, el prorrateo y la nacionalización pueden quedar incompletos: asocie OC primero.',
                'El N° de despacho es el que se ve en despachos aduaneros y en el lote.',
                'Después de nacionalizar puede seguir cargando gastos reales hasta cerrar costos.',
            ],
        ],
        [
            'titulo' => '9. Checklist y Libro IVA',
            'parrafos' => [
                'La solapa Checklist lista los documentos esperados (factura comercial, BL, póliza, packing list, etc.). Marque cumplido cuando el archivo esté cargado o verificado.',
                'En Landed cost, el bloque Libro IVA importaciones (cbte 066) permite sincronizar fecha, importe e IVA hacia el ABM de Libro IVA de importaciones. Use importes ya en moneda del libro.',
            ],
        ],
        [
            'titulo' => '10. Cashflow y simulador',
            'parrafos' => [
                'Cashflow / simulador (`compras/importacion/cashflow`) proyecta vencimientos de gastos de carpetas abiertas por empresa y rango de fechas. Sirve a tesorería para anticipar desembolsos de flete, seguro y aduana.',
                'El simulador pre-OC estima landed cost con tipos de gasto y partidas tentativas sin grabar una carpeta: útil antes de confirmar la orden.',
            ],
            'items' => [
                'La pantalla muestra también el diagnóstico del bridge Anita y nomenclaturas activas (si el entorno las usa).',
                'No reemplaza la proyección de pagos de Cuentas a pagar: es específica de gastos de importación.',
            ],
        ],
        [
            'titulo' => '11. Factura de proveedor ↔ gasto de importación',
            'parrafos' => [
                'Hay dos caminos para vincular una factura de proveedor (CP) con un gasto de la carpeta. Ambos crean o actualizan un gasto con naturaleza Real y refrescan el landed cost al guardar.',
                'Desde la carpeta (solapa Gastos): en cada renglón asocie la CP con código/lupa. Al elegirla se precargan importe, fecha y cotización y la naturaleza queda en Real.',
                'Desde el ABM de Comprobante de proveedor (todas las altas: Con OC, Sin OC, gasto, etc.): el panel Importación aparece siempre que el usuario tenga permiso de importación o de factura. Va colapsado por defecto para no molestar en AGG ni en Crown; se abre con el título. Si ya hay vínculos, el badge muestra la cantidad.',
                'Al expandir el panel puede elegir carpeta (código/lupa) y tipo de gasto. El tipo es obligatorio si eligió carpeta. Al guardar se crea o actualiza un gasto real en esa carpeta con el total de la factura. No hace falta OC: sirve para flete, seguro, despachante u otros gastos de comercio exterior.',
            ],
            'items' => [
                'Panel colapsado por defecto en crear y editar factura; no ocupa espacio hasta que lo abra.',
                'Si la factura ya está en una o más carpetas, el panel lista carpeta, tipo de gasto e importe, con enlace para abrir la carpeta en consulta.',
                'Una misma factura puede figurar en más de un gasto si el negocio lo permite.',
                'Si no toca el panel (campos de importación ausentes o vacíos), al guardar no se desvinculan gastos ya asociados.',
            ],
        ],
        [
            'titulo' => '12. Tipos de gasto y cuentas',
            'parrafos' => [
                'Configuración → (o Compras) Tipos de gasto de importación: código, nombre, inventariable sí/no, base de prorrateo, orden y activo. Sin tipos activos no se pueden cargar renglones de gasto en la carpeta.',
                'Las cuentas contables no van hardcodeadas: se resuelven por Cuentas automáticas (`CuentaAutomaticaResolver` / claves `importacion.gastos_*`). El operador las asigna en la pantalla de cuentas automáticas por empresa.',
            ],
        ],
        [
            'titulo' => '13. Roles y permisos',
            'parrafos' => [
                'Si falta un botón, revise permiso o estado — no asuma un error de pantalla.',
            ],
            'tabla' => [
                'caption' => 'Permisos principales',
                'headers' => ['Permiso (slug)', 'Para qué sirve'],
                'rows' => [
                    ['listar-importacion', 'Ver listado, export, abrir carpeta, descargar adjuntos, consultas OC/lote.'],
                    ['crear-importacion', 'Alta de carpeta.'],
                    ['actualizar-importacion', 'Editar cabecera, solapas, adjuntos, checklist marcar.'],
                    ['borrar-importacion', 'Eliminar carpeta (si el estado lo permite).'],
                    ['nacionalizar-importacion', 'Bloque Nacionalizar.'],
                    ['cerrar-costos-importacion', 'Cerrar costos definitivos.'],
                    ['listar-importacion-gasto-tipo', 'ABM tipos de gasto.'],
                ],
            ],
        ],
        [
            'titulo' => '14. Buenas prácticas',
            'parrafos' => [
                'Una carpeta por embarque operativo (o por despacho, si el negocio lo exige). Mezclar varios arribos distintos en la misma carpeta ensucia el coeficiente.',
                'Cargue estimados apenas tenga el booking; actualice a reales cuando lleguen las facturas. Compare estimado vs real antes de cerrar.',
                'Adjunte BL/factura/póliza en Documentos y márquelos en Checklist: el cierre fiscal y las auditorías lo piden.',
            ],
            'items' => [
                'Use la cotización de la carpeta para gastos en moneda extranjera; si viene vacía, el ERP toma la vigente del día del gasto.',
                'No duplique el mismo gasto en la carpeta y en un asiento manual “por las dudas”: el preview contable del landed cost es la referencia.',
                'Después de Costos cerrados, correcciones van por asiento de ajuste fuera de la carpeta (o reapertura solo con decisión de administración).',
            ],
        ],
        [
            'titulo' => '15. Dónde pedir ayuda',
            'parrafos' => [
                'Este manual está en el Centro de ayuda del ERP y en el botón Manual del listado de Importaciones. El asistente de IA puede citar extractos cuando pregunte por landed cost, nacionalización o checklist.',
                'Para regenerar PDF/Word tras cambios de contenido: `php docs/manual-importacion/generar.php`.',
            ],
        ],
    ],
];
