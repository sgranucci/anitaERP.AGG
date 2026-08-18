<?php

/**
 * Manual de usuario — Stock gastronómico, fórmulas e insumos.
 * Audiencia: encargado de producción / depósito / back-office gastronomía.
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Stock gastronómico, fórmulas e insumos',
    'version' => '1.2',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Introducción y roles',
            'captura_id' => 'flujo_stock_gastro',
            'parrafos' => [
                'Este manual explica cómo Anita ERP descuenta stock cuando el salón factura platos y bebidas, cómo se arman las fórmulas de producción y cómo consultar los movimientos de insumos. Está pensado para encargados de producción, depósito y personal de back-office gastronomía: no hace falta saber programación.',
                'El circuito une tres piezas: la configuración de cada terminal de facturación (depósitos de venta e insumos), el maestro de fórmulas en Stock, y el descuento automático al emitir una factura desde el POS gastronomía.',
                'Menú principal: Ventas → Tablas → Config. terminales gastronomía (depósitos); Stock → Fórmulas de artículos (recetas); Ventas → Gastronomía → reportes de insumos y artículos vendidos.',
            ],
            'tabla' => [
                'caption' => 'Roles habituales',
                'headers' => ['Rol', 'Qué hace en este circuito'],
                'rows' => [
                    ['Encargado de producción / surtido', 'Arma y mantiene fórmulas, revisa costos por última compra, vincula artículos facturables con recetas.'],
                    ['Depósito / compras', 'Recibe insumos (COM), transfiere entre depósitos, consulta kardex y saldos en depósito de insumos.'],
                    ['Administrador gastronomía', 'Configura depósito venta e insumos por PC de facturación; asigna permisos.'],
                    ['Cajero / mozo (POS)', 'Factura consumos; el sistema descuenta solo si la terminal está bien configurada y el artículo tiene fórmula cuando corresponde.'],
                    ['Gerencia / control', 'Consulta reportes de insumos por tipo de artículo y artículos vendidos con detalle de movimientos.'],
                ],
            ],
            'items' => [
                'Sin depósitos configurados en la terminal, la facturación puede emitir comprobante pero no generará movimientos de stock.',
                'Un artículo facturable puede tener fórmula; los insumos de la fórmula son artículos distintos que se descuentan del depósito de insumos.',
                'Las notas de crédito revierten los movimientos de la factura origen cuando el tipo de transacción de ventas tiene operación de stock.',
            ],
        ],
        [
            'titulo' => '2. Conceptos: artículos, fórmulas y depósitos',
            'captura_id' => 'flujo_stock_gastro',
            'parrafos' => [
                'En gastronomía conviene separar mentalmente el plato que aparece en la carta y se factura, de los insumos que se consumen al prepararlo. Anita ERP modela esa diferencia con tipos de artículo, fórmulas y dos depósitos por terminal.',
            ],
            'tabla' => [
                'caption' => 'Glosario operativo',
                'headers' => ['Término', 'Significado para el operador'],
                'rows' => [
                    ['Artículo facturable', 'Ítem de carta / lista de precios (ej. hamburguesa completa, combo). Se vende en el POS y puede tener campo articulo.formula apuntando a su receta.'],
                    ['Insumo', 'Materia prima o componente (carne, pan, queso, gaseosa en lata). Suele ser tipo de artículo distinto al facturable; entra por compras y vive en depósito de insumos.'],
                    ['Fórmula (formula_articulo)', 'Receta: cabecera con código y cantidad unidad, más líneas hijas (insumos, subfórmulas u opcionales).'],
                    ['Depósito venta', 'Donde se descuenta el ítem facturado tal como figura en la cuenta (producto terminado o bebida entera si así está modelado).'],
                    ['Depósito insumos', 'Donde se descuentan los componentes expandidos desde la fórmula al facturar.'],
                    ['Referencia artículo de compra', 'Artículo de compra cuyo SKU alternativo apunta al insumo (skualternativo). Permite recepcionar bajo un código y consumir bajo otro.'],
                    ['Opcional de fórmula', 'Línea marcada como opcional con orden de opción; el POS elige una variante por grupo (ej. tipo de pan) y solo esa línea entra al consumo.'],
                    ['Subfórmula', 'Línea hija que referencia otra formula_articulo en lugar de un insumo directo; el sistema la expande recursivamente.'],
                    ['venta_emision_id', 'Identificador de la línea de la factura; vincula movimientos del ítem y de sus insumos para trazabilidad.'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Dos depósitos por terminal',
                'headers' => ['Depósito', 'Qué descuenta al facturar', 'Ejemplo'],
                'rows' => [
                    ['Depósito venta', 'Salida del artículo de la línea de cuenta (SKU vendido).', 'Combo promocional ya armado, bebida envasada vendida como unidad.'],
                    ['Depósito insumos', 'Salida de cada insumo de la fórmula × cantidad vendida (con opcionales elegidos).', 'Carne, pan, condimentos de una hamburguesa.'],
                ],
            ],
            'items' => [
                'cantidadunidad en la cabecera de fórmula define cuántas unidades de venta representa la receta base (por defecto 1).',
                'factorcosto (FC) en cada hijo ajusta la cantidad a descontar y el costo teórico (0 = no suma costo ni cantidad).',
                'Si el tipo de transacción de ventas no tiene operación de stock (sin operación), facturar no mueve depósitos aunque exista fórmula.',
            ],
        ],
        [
            'titulo' => '3. Configuración PV gastronomía — depósitos venta e insumos',
            'herramientas_grupos' => [
                ['titulo' => 'Listado y formulario', 'clave' => 'config_pv_gastronomia', 'incluir_listado' => false],
            ],
            'captura_id' => 'config_depositos',
            'parrafos' => [
                'Pantalla: Ventas → Tablas → Config. terminales gastronomía. Ruta: ventas/configuracion-puntoventa-gastronomia.',
                'Cada registro identifica una PC o terminal de facturación (identificador_pc, empresa, puntos de venta CAE/CAEA, lista de precios, tipos de transacción). Para stock gastronómico lo crítico son los campos Depósito de artículos facturados y Depósito de descuento de insumos.',
                'Depósito venta: se elige con modal de consulta depósito (código + lupa F1). Debe ser un depósito autorizado para el usuario que configura y pertenecer a la empresa del registro.',
                'Depósito insumos: mismo criterio; suele ser el depósito de materia prima / bodega de cocina. No tiene que coincidir con el de venta.',
                'Al guardar, el POS que resuelve esa configuración usará esos dos depósitos en cada factura emitida (proceso de facturación, cierre Waitry, etc.).',
            ],
            'tabla' => [
                'caption' => 'Campos relacionados con stock',
                'headers' => ['Campo', 'Uso'],
                'rows' => [
                    ['deposito_venta_id', 'Salida del SKU facturado (ítem de carta).'],
                    ['deposito_insumos_id', 'Salida de insumos calculados desde la fórmula del artículo.'],
                    ['tipotransaccion_id / tipos en cfg', 'Tipo de factura y NC; debe tener operacionstock salida para generar movimientos.'],
                    ['empresa_id', 'Filtra depósitos y artículos operativos de la terminal.'],
                ],
            ],
            'items' => [
                'Si falta alguno de los dos depósitos, el preflight de emisión puede bloquear la factura con mensaje explícito.',
                'Varias terminales del mismo local pueden compartir los mismos depósitos o usar bodegas distintas según el negocio.',
                'Cambiar depósitos en caliente afecta solo facturas nuevas; las ya emitidas conservan el depósito grabado en articulo_movimiento.',
            ],
        ],
        [
            'titulo' => '4. Armado de fórmulas (stock/formula-articulo)',
            'herramientas_grupos' => [
                ['titulo' => 'Listado de fórmulas', 'clave' => 'formula_listado', 'incluir_listado' => true],
                ['titulo' => 'Cabecera y grilla de hijos', 'clave' => 'formula_form'],
            ],
            'captura_id' => 'formula_edicion',
            'parrafos' => [
                'Menú: Stock → Fórmulas de artículos. Ruta: stock/formula-articulo.',
                'Cabecera: código (coincide con legacy Anita), descripción, cantidad unidad, estado, empresa cuando aplica, archivos adjuntos. cantidadunidad indica cuántas unidades de venta cubre la receta (normalmente 1).',
                'Grilla de hijos: cada línea es un insumo (modal artículo), una subfórmula (modal búsqueda de fórmulas) o una variante opcional. Columnas principales: cantidad, factor costo (FC), costo última compra (solo lectura, calculado), opcional sí/no, orden opcional.',
                'Subfórmulas: en lugar de artículo_id se elige formula_hija_id. Al facturar, el sistema expande la subfórmula completa antes de descontar insumos.',
                'Costo última compra: botón o carga automática vía costos-ultima-compra; muestra el costo de referencia por insumo según última compra ERP/Anita. Sirve para revisar márgenes, no reemplaza el precio de venta.',
                'Artículos de compra por insumo: icono/modal en la línea abre la lista de SKUs de compra cuyo SKU alternativo apunta al insumo (referencia cruzada compra → insumo).',
                'Vincular artículos por código: acción masiva en el listado. Empareja fórmula con artículo cuyo SKU coincide (código Anita → V0000, ej. 365 → V0365) y actualiza articulo.formula.',
                'Sincronizar desde Anita: trae/actualiza fórmulas desde el maestro legacy. Si el navegador corta por tiempo, ejecutar en servidor: php artisan formula-articulo:sincronizar-anita.',
            ],
            'tabla' => [
                'caption' => 'Columnas de la grilla de hijos',
                'headers' => ['Columna', 'Qué cargar'],
                'rows' => [
                    ['Artículo / Subfórmula', 'Insumo directo (SKU) o receta hija; no mezclar ambos en la misma línea.'],
                    ['Cantidad', 'Cantidad de insumo por cantidadunidad de la cabecera.'],
                    ['FC (factor costo)', 'Multiplicador; 0 excluye la línea del costo y del consumo.'],
                    ['Costo últ. compra', 'Informativo; se refresca desde recepciones/compras.'],
                    ['Opcional', 'Sí = entra solo si el POS elige esa variante en el grupo.'],
                    ['Ord. opcional', 'Agrupa opcionales excluyentes (1, 2, 3…).'],
                ],
            ],
            'items' => [
                'Consultar fórmula desde Stock → Artículos: solapa Fórmula o botón consulta si el artículo tiene articulo.formula cargado.',
                'Export PDF/Excel/CSV del listado respeta filtros activos (patrón listado paginado).',
                'Estados de fórmula: respete bajas/suspensiones; artículos inactivos no deben figurar en recetas operativas.',
                'Recalcular transferencias (permiso recalcular-transferencias-formula-articulo): herramienta avanzada cuando cambian recetas y hay transferencias pendientes.',
            ],
        ],
        [
            'titulo' => '5. Artículos de compra por proveedor y conversión de unidades',
            'captura_id' => 'articulo_proveedor',
            'parrafos' => [
                'Cada artículo ERP tiene una solapa Proveedores en Stock → Artículos → Editar. Allí se registra cómo lo identifica cada proveedor: proveedor, nombre comercial, código de barras, código de artículo del proveedor, unidad de medida de compra, coeficiente de conversión, activo y preferido. La lista de precios vigente se muestra como consulta.',
                'La unidad del artículo (por ejemplo kg, litro o unidad) es la unidad en la que Anita ERP mantiene el stock. La UM compra es la presentación que cotiza y entrega el proveedor (caja, bolsa, bidón, pack). El coeficiente indica cuántas unidades de stock contiene una unidad de compra.',
                'Fórmula general: cantidad stock = cantidad compra × coeficiente. Precio unitario stock = precio por unidad de compra ÷ coeficiente. De este modo el importe no cambia: cantidad compra × precio compra = cantidad stock × precio stock.',
                'Si la UM compra coincide con la UM del artículo, el sistema fuerza coeficiente 1. No cargue 100 solo porque la descripción diga “X100”: si la caja/paquete es la propia unidad de stock, el precio ya corresponde a esa unidad.',
            ],
            'tabla' => [
                'caption' => 'Ejemplos de conversión compra → stock',
                'headers' => ['Compra recibida', 'UM stock', 'Coef.', 'Entrada de stock', 'Costo unitario stock'],
                'rows' => [
                    ['2 cajas a $ 12.000 c/u (12 botellas/caja)', 'unidad', '12', '24 unidades', '$ 1.000 por botella'],
                    ['3 bolsas a $ 18.000 c/u (5 kg/bolsa)', 'kg', '5', '15 kg', '$ 3.600 por kg'],
                    ['8 cajas a $ 9.500 c/u; UM artículo = caja', 'caja', '1', '8 cajas', '$ 9.500 por caja'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Qué campo interviene en cada paso',
                'headers' => ['Campo / marca', 'Función'],
                'rows' => [
                    ['Código artículo proveedor', 'Permite reconocer la línea de OC, remito u OCR aunque el proveedor no use el SKU ERP. Debe ser único para ese proveedor.'],
                    ['Activo', 'Solo las referencias activas se ofrecen en compras y resoluciones operativas.'],
                    ['Preferido', 'Prioriza una referencia cuando el artículo tiene más de una opción; si hay varios códigos válidos, el sistema puede pedir elegir.'],
                    ['UM compra + coeficiente', 'Convierte la cantidad y el precio de la OC/COM a la unidad con la que se lleva el stock.'],
                    ['SKU alternativo del artículo de compra', 'Vincula el artículo comprado con el insumo/granel que consume la fórmula cuando el destino es un depósito tipo Fórmulas.'],
                    ['Coeficiente del artículo', 'En depósito Fórmulas prevalece sobre el coeficiente del proveedor y convierte la presentación comprada al insumo de stock.'],
                ],
            ],
            'items' => [
                'Flujo normal: la OC conserva la referencia artículo-proveedor elegida; al crear la COM se recuperan su código, UM compra y coeficiente. La pantalla muestra cantidad compra, cantidad stock y las dos unidades antes de confirmar.',
                'Al confirmar la COM, el movimiento entra con la cantidad stock convertida y el costo unitario stock. El kardex y la fórmula trabajan desde ese momento en la UM stock, no en cajas o packs del proveedor.',
                'Depósito común: usa el coeficiente activo del artículo-proveedor. Depósito tipo Fórmulas: exige SKU alternativo válido, mueve el insumo vinculado y usa el coeficiente del artículo de compra.',
                'Ejemplo gastronómico: se compra C001234 “Mozzarella horma 4 kg”, con skualternativo I000045. Al recibir 3 hormas en depósito Fórmulas y coeficiente 4, entran 12 kg de I000045; las recetas consumen I000045 en kg.',
                'Una recepción confirmada también puede aprender o completar la referencia del proveedor a partir de OC/OCR (nombre, código, código de barras, UM y coeficiente). Revise el maestro antes de aceptar una conversión nueva.',
                'Si falta la referencia, el coeficiente es inválido o no se puede resolver el insumo de un depósito Fórmulas, no fuerce coeficiente 1: corrija primero Stock → Artículos → Proveedores o el SKU alternativo.',
                'Si el mismo proveedor tiene varios códigos para un artículo, marque bien el Preferido y mantenga coeficientes coherentes: al reprocesar la COM el sistema puede resolver por artículo+proveedor priorizando el preferido.',
                'El stock de la recepción vive en el ERP (kardex). La sincronización Anita de COM no replica, por defecto, el detalle a stkmov.',
                'Permisos: editar-compras-articulos permite consultar la solapa; actualizar-compras-articulos permite agregar, modificar o quitar referencias.',
            ],
        ],
        [
            'titulo' => '6. Descuento de stock al facturar en el POS',
            'captura_id' => 'consumo_factura',
            'parrafos' => [
                'Al emitir una factura gastronomía (F5, F8 u otros flujos de emisión), el servicio GastronomiaFormulaConsumoService registra movimientos en articulo_movimiento si el tipo de transacción de ventas tiene operacionstock de entrada o salida.',
                'Por cada línea de la cuenta facturada: (1) salida del artículo vendido en deposito_venta; (2) si articulo.formula está cargado, expansión de la receta (incluidas subfórmulas y opcionales elegidos en opcionales_json) y salida de cada insumo en deposito_insumos.',
                'Cada movimiento guarda venta_id y venta_emision_id para poder rastrear desde reportes de artículos vendidos → movimientos qué insumos demandó esa línea.',
                'Cantidades: el tipo de venta firma el signo (salida = cantidad negativa). Los insumos usan cantidad absoluta × factor de línea × cantidad vendida.',
                'Precio/costo en insumos: se intenta tomar última compra del insumo (precio y costo histórico); el ítem facturado lleva precio neto de la línea de venta.',
                'Nota de crédito: revertirMovimientosStockDesdeFactura copia movimientos de la factura origen con el tipo NC configurado (operacionstock inversa), respetando depósito y sufijo de concepto de insumo. Si el tipo NC no afecta stock, no hay reverso.',
            ],
            'tabla' => [
                'caption' => 'Secuencia por línea facturada',
                'headers' => ['Paso', 'Depósito', 'Qué descuenta'],
                'rows' => [
                    ['1', 'Depósito venta', 'Artículo de la línea (SKU carta).'],
                    ['2', 'Depósito insumos', 'Cada insumo de la fórmula expandida (si hay receta).'],
                    ['NC', 'Mismo criterio', 'Movimiento inverso por cada movimiento origen vinculado a la factura.'],
                ],
            ],
            'items' => [
                'Opcionales de fórmula en POS: solo aplican si GASTRONOMIA formula opcionales está habilitado; el mapa viene en opcionales_json de la línea.',
                'Artículo sin fórmula: solo se descuenta el SKU vendido del depósito venta (ej. bebida envasada).',
                'Facturación sin afectar stock: tipo de transacción con operacionstock sin operación — no llama al servicio de consumo.',
                'Cierre Waitry / emisión diferida: registrarMovimientosIngredientesDesdeVentaEmitida repite la lógica usando venta_emisiones cuando no hay cuenta gastronómica intermedia.',
            ],
        ],
        [
            'titulo' => '7. Tipos de movimientos de stock',
            'captura_id' => 'tipos_movimiento',
            'parrafos' => [
                'Todo movimiento en articulo_movimiento tiene un tipotransaccion_stock (Stock → Tipos de transacción stock) o proviene de un tipo de venta que genera stock. La columna cantidad ya viene firmada al grabar: positiva = entrada, negativa = salida.',
            ],
            'tabla' => [
                'caption' => 'Familias principales',
                'headers' => ['Familia', 'Origen', 'Signo / efecto'],
                'rows' => [
                    ['tipotransaccion_stock — Entrada (E)', 'Movimientos manuales, recepciones, ajustes positivos', 'Signo + en cantidad; suma saldo.'],
                    ['tipotransaccion_stock — Salida (S)', 'Movimientos manuales, consumos, ajustes negativos', 'Signo − en cantidad; resta saldo.'],
                    ['tipotransaccion_stock — Transferencia (T)', 'Transferencia mercadería / mov. transferencia', 'Salida en origen; entrada en destino (documento transferencia).'],
                    ['operacionstock ventas — Salida (S)', 'Factura gastronomía (ítem + insumos)', 'Generado por GastronomiaFormulaConsumoService.'],
                    ['operacionstock ventas — Entrada (E)', 'Nota de crédito que revierte factura', 'Cantidad opuesta a la factura origen.'],
                    ['operacionstock ventas — Sin operación (O/N)', 'Tipos que no mueven stock', 'No crea articulo_movimiento.'],
                    ['COM — Recepción proveedor', 'Confirmar recepción COM', 'Entrada en depósito de la recepción; alimenta insumos.'],
                    ['TRA / TRCONT — Transferencias', 'Stock → Transferencia o mov. transferencia', 'Mueve entre depósitos; TRCONT además contabiliza.'],
                    ['RCAJP / RCAJN / RCAJR — Recuento', 'Confirmar recuento inventario', 'Ajuste positivo (RCAJP), negativo (RCAJN) o reverso (RCAJR).'],
                    ['Gastronomía — consumo', 'Factura POS', 'Concepto ítem; insumos llevan sufijo de concepto insumo en el texto.'],
                ],
            ],
            'items' => [
                'Consulte movimientos unificados en Stock → Movimientos de Stock (incluye transferencias y facturación con venta_id).',
                'Icono kardex en líneas de movimiento: historial del artículo en el depósito.',
                'tipotransaccion_stock y operacionstock de ventas son independientes: ventas usa Tipotransaccion de Ventas; recepciones y movimientos manuales usan tipos stock.',
                'Recuento: no mezclar signo manual — el sistema firma cantidad según tipo RCAJP/RCAJN al confirmar.',
            ],
        ],
        [
            'titulo' => '8. Reportes de insumos y artículos vendidos',
            'herramientas_grupos' => [
                ['titulo' => 'Reporte insumos por tipo artículo', 'clave' => 'insumos_reporte'],
                ['titulo' => 'Artículos vendidos', 'clave' => 'articulos_vendidos'],
            ],
            'captura_id' => 'insumos_reporte',
            'parrafos' => [
                'Reporte «Insumos vendidos» / insumos por tipo de artículo — Ruta: ventas/gastronomia/insumos-tipoarticulo-reporte. Permiso: listar-insumos-tipoarticulo-gastronomia.',
                'Atención: pese al nombre, este reporte NO lee ingredientes expandidos de la fórmula ni articulo_movimiento. Agrupa artículos facturados (venta_emision) por tipoarticulo_id, empresa y fecha de jornada. Sirve para control por familia de venta (alimentos, bebidas, cigarrillos, etc.), no para stock teórico de cocina.',
                'Artículos vendidos — Ruta: ventas/gastronomia/articulos-vendidos. Permiso: listar-articulos-vendidos-gastronomia.',
                'Listado analítico de unidades vendidas por SKU. Desde cada fila puede abrir facturas y los movimientos de stock reales (ítem + insumos por venta_emision_id): esa es la vía correcta para auditar consumo de ingredientes.',
            ],
            'tabla' => [
                'caption' => 'Comparación rápida',
                'headers' => ['Reporte', 'Pregunta que responde', 'Export'],
                'rows' => [
                    ['Insumos por tipo artículo', '¿Cuánto facturé de artículos de un tipo (no ingredientes de receta)?', 'listar-gastronomia-insumos-tipoarticulo/{formato}'],
                    ['Artículos vendidos → movimientos', '¿Qué vendí y qué salidas de stock (ítem + fórmula) generó?', 'listar-gastronomia-articulos-vendidos/{formato}'],
                ],
            ],
            'items' => [
                'Ambos usan fechas jornada (fecha de turno), no solo fecha calendario de factura.',
                'Consultar con consultar=1 tras completar filtros; export respeta criterios activos.',
                'Cierre Waitry: las comandas facturadas bajan stock por fórmula; las comandas de efectivo no facturadas del tramo pueden generar movimiento AJCON (ajuste por consumo) directo al depósito de insumos, sin asiento de costo.',
                'Enlaces azules abren ABM en nueva pestaña (origen=modal_consulta) si tiene permiso.',
            ],
        ],
        [
            'titulo' => '9. Permisos y buenas prácticas',
            'parrafos' => [
                'Si no ve un botón descrito en este manual, su rol probablemente no tiene el permiso. Solicítelo al administrador de usuarios.',
            ],
            'tabla' => [
                'caption' => 'Permisos principales',
                'headers' => ['Permiso', 'Para qué sirve'],
                'rows' => [
                    ['listar-formula-articulo', 'Ver listado y export de fórmulas; consulta modal.'],
                    ['crear-formula-articulo / editar-formula-articulo / actualizar-formula-articulo', 'Alta, edición, sync Anita, vincular por código.'],
                    ['borrar-formula-articulo', 'Eliminar fórmulas (acción irreversible).'],
                    ['recalcular-transferencias-formula-articulo', 'Recalcular transferencias afectadas por cambio de receta.'],
                    ['listar-configuracion-puntoventa-gastronomia', 'Ver terminales y depósitos configurados.'],
                    ['crear-configuracion-puntoventa-gastronomia / editar-* / actualizar-*', 'Modificar depósito venta e insumos por PC.'],
                    ['facturar-gastronomia', 'Emitir facturas que disparan consumo (POS).'],
                    ['listar-insumos-tipoarticulo-gastronomia', 'Reporte insumos por tipo artículo.'],
                    ['listar-articulos-vendidos-gastronomia', 'Reporte artículos vendidos y API movimientos.'],
                    ['listar-movimientos-de-stock', 'Kardex y trazabilidad de consumos.'],
                ],
            ],
            'tabla2' => [
                'caption' => 'Buenas prácticas',
                'headers' => ['Tema', 'Recomendación'],
                'rows' => [
                    ['Recetas', 'Mantenga articulo.formula apuntando a la fórmula activa; use Vincular por código tras sync Anita.'],
                    ['Depósitos', 'Revise cfg PV antes de abrir jornada; un depósito mal asignado descuenta stock incorrecto todo el turno.'],
                    ['Opcionales', 'Agrupe variantes excluyentes con el mismo orden opcional; pruebe una venta real y consulte movimientos.'],
                    ['Compras ↔ insumo', 'Complete skualternativo en artículos de compra; evite duplicar insumos por error de codificación.'],
                    ['Inventario', 'Concilie depósito insumos con recuentos (RCAJP/RCAJN) y reporte insumos periódicamente.'],
                    ['NC', 'Configure tipo NC con operacionstock entrada para devolver stock al anular ventas.'],
                ],
            ],
        ],
        [
            'titulo' => '10. Preguntas frecuentes (FAQ)',
            'tabla' => [
                'caption' => 'Problemas habituales',
                'headers' => ['Situación', 'Qué revisar'],
                'rows' => [
                    ['Facturé y no bajó stock', 'Cfg PV: depósitos venta/insumos; tipo transacción con operacionstock; artículo activo.'],
                    ['Solo bajó el plato, no los insumos', 'articulo.formula vacío o fórmula sin hijos; opcionales mal elegidos; FC = 0 en todas las líneas.'],
                    ['Bajó insumo incorrecto', 'Receta desactualizada; subfórmula equivocada; opcional del POS mal mapeado.'],
                    ['Depósito sin saldo al facturar', 'Saldo insuficiente en depósito insumos o venta; recepciones COM pendientes; kardex para detectar faltante.'],
                    ['NC no devolvió materia prima', 'Tipo NC sin operacionstock entrada; NC parcial sin líneas correspondientes.'],
                    ['Costo última compra en cero', 'Insumo sin recepciones COM previas; referencia compra → insumo inexistente.'],
                    ['Sync Anita incompleta', 'Ejecutar php artisan formula-articulo:sincronizar-anita en servidor; luego Vincular por código.'],
                    ['Reporte insumos vacío', 'Filtro tipo artículo o fechas jornada; confirmar que hubo ventas con movimientos en el período.'],
                    ['Artículos vendidos sin movimientos', 'Ventas anteriores a configuración de depósitos; tipo sin operacionstock; artículo sin fórmula y sin stock en depósito venta.'],
                ],
            ],
            'items' => [
                'Flujo de diagnóstico: (1) movimiento en Stock → Movimientos; (2) venta_emision_id y venta_id; (3) fórmula del artículo; (4) depósitos de la PC que facturó.',
                'Manual complementario POS y jornada: Ventas → Gastronomía — manual Módulo Gastronomía (facturación, jornada, cierres).',
                'Manual depósito general: Recepción proveedores y movimientos de stock (COM, TRA, recuento).',
            ],
        ],
    ],
];
