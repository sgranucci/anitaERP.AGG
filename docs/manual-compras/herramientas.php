<?php

/**
 * Definición de herramientas (botones, acciones, exportaciones) del módulo Compras.
 * Referenciado desde contenido.php del manual.
 */
$barraListado = 'Barra superior de la tarjeta (card-header)';
$columnaAcciones = 'Columna derecha de cada fila en la grilla';
$sobreGrilla = 'Barra de botones grandes sobre la grilla (btn-app)';

return [
    'comunes_listado' => [
        [
            'herramienta' => 'Manual',
            'ubicacion' => 'Esquina superior derecha (ícono libro)',
            'accion' => 'Abre este manual de usuario en una pestaña nueva.',
            'permiso' => 'Usuario autenticado con acceso al módulo',
        ],
        [
            'herramienta' => 'Nuevo registro',
            'ubicacion' => $barraListado,
            'accion' => 'Abre el formulario de alta del recurso.',
            'permiso' => 'Permiso crear-* del recurso',
        ],
        [
            'herramienta' => 'Búsqueda',
            'ubicacion' => 'Campo de texto + lupa a la derecha del título',
            'accion' => 'Filtra el listado por texto libre (número, nombre, etc.).',
            'permiso' => 'listar-*',
        ],
        [
            'herramienta' => 'PDF / Excel / CSV',
            'ubicacion' => $sobreGrilla,
            'accion' => 'Exporta el listado actual (respeta el filtro de búsqueda) al formato elegido.',
            'permiso' => 'listar-*',
        ],
        [
            'herramienta' => 'Ordenar columnas',
            'ubicacion' => 'Encabezados de la grilla',
            'accion' => 'Clic en el título de columna para ordenar ascendente/descendente (DataTables).',
            'permiso' => 'listar-*',
        ],
        [
            'herramienta' => 'Paginación',
            'ubicacion' => 'Pie de la tarjeta',
            'accion' => 'Navega entre páginas de resultados.',
            'permiso' => 'listar-*',
        ],
    ],
    'login' => [
        [
            'herramienta' => 'Usuario',
            'ubicacion' => 'Formulario central',
            'accion' => 'Ingrese su nombre de usuario del ERP.',
            'permiso' => '—',
        ],
        [
            'herramienta' => 'Contraseña',
            'ubicacion' => 'Formulario central',
            'accion' => 'Ingrese su contraseña. No comparta credenciales.',
            'permiso' => '—',
        ],
        [
            'herramienta' => 'Login',
            'ubicacion' => 'Botón principal',
            'accion' => 'Autentica y redirige al panel o al selector de rol.',
            'permiso' => '—',
        ],
        [
            'herramienta' => 'Selector de rol',
            'ubicacion' => 'Modal tras login (si aplica)',
            'accion' => 'Elija el rol con el que operará en la sesión; define menú y permisos.',
            'permiso' => 'Usuario con más de un rol activo',
        ],
    ],
    'proveedor_listado' => [
        [
            'herramienta' => 'Editar',
            'ubicacion' => $columnaAcciones . ' (ícono lápiz)',
            'accion' => 'Abre la ficha completa del proveedor.',
            'permiso' => 'editar-proveedor',
        ],
        [
            'herramienta' => 'Cuenta corriente',
            'ubicacion' => $columnaAcciones . ' (ícono carpeta)',
            'accion' => 'Consulta movimientos de cuenta corriente del proveedor.',
            'permiso' => 'listar-cuentacorriente-proveedor',
        ],
        [
            'herramienta' => 'Aplicar cuenta corriente',
            'ubicacion' => 'Menú Compras, ficha del proveedor y cuenta corriente',
            'accion' => 'Workbench para aplicar notas de crédito y pagos a cuenta contra facturas adeudadas (FIFO / pareo / parcial / desaplicar).',
            'permiso' => 'aplicar-cuentacorriente-proveedor',
        ],
        [
            'herramienta' => 'Eliminar',
            'ubicacion' => $columnaAcciones . ' (ícono X rojo)',
            'accion' => 'Borrado lógico; solicita confirmación.',
            'permiso' => 'borrar-proveedor',
        ],
    ],
    'proveedor_edicion' => [
        [
            'herramienta' => 'Solapas (Datos, Domicilio, Impositivo, etc.)',
            'ubicacion' => 'Bajo el título del formulario',
            'accion' => 'Navega entre bloques de información del proveedor.',
            'permiso' => 'editar-proveedor',
        ],
        [
            'herramienta' => 'Consulta ARCA / padrón',
            'ubicacion' => 'Botón en solapa impositiva',
            'accion' => 'Consulta constancia AFIP por CUIT e importa datos al formulario.',
            'permiso' => 'editar-proveedor',
        ],
        [
            'herramienta' => 'Cuenta corriente / Encuestas / Requisiciones / OC',
            'ubicacion' => 'Barra superior del formulario',
            'accion' => 'Accesos directos a listados filtrados por este proveedor.',
            'permiso' => 'Según permiso de cada destino',
        ],
        [
            'herramienta' => 'Actualizar',
            'ubicacion' => 'Pie del formulario',
            'accion' => 'Guarda los cambios en base de datos.',
            'permiso' => 'editar-proveedor',
        ],
    ],
    'requisicion_listado' => [
        [
            'herramienta' => 'Seguimiento aprobación',
            'ubicacion' => $barraListado . ' (botón amarillo)',
            'accion' => 'Abre el tablero de requisiciones pendientes de aprobación: responsable actual, días desde creación y alerta si supera el plazo (48 hs por defecto). Incluye botón para ver el árbol sin abrir la requisición.',
            'permiso' => 'seguimiento-aprobacion-requisicion',
        ],
        [
            'herramienta' => 'KPIs',
            'ubicacion' => $barraListado . ' (botón verde)',
            'accion' => 'Abre el tablero de KPIs de proceso (ciclo RQ→OC, gestión OC, circuito hasta COM, % OC abiertas) y productividad (OC y ahorro por comprador).',
            'permiso' => 'listar-kpi-compras',
        ],
        [
            'herramienta' => 'Ver árbol de aprobación',
            'ubicacion' => 'Tablero seguimiento (ícono sitemap)',
            'accion' => 'Modal con los movimientos del árbol (envío, nivel, estado, destinatario) sin entrar a editar la requisición.',
            'permiso' => 'seguimiento-aprobacion-requisicion',
        ],
        [
            'herramienta' => 'Editar',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Abre cabecera, líneas, presupuestos y archivos.',
            'permiso' => 'editar-requisicion',
        ],
        [
            'herramienta' => 'Árbol de aprobación',
            'ubicacion' => $columnaAcciones . ' (ícono sitemap, verde)',
            'accion' => 'Envía la requisición al circuito de aprobación (estado EN COMPRAS).',
            'permiso' => 'editar-requisicion',
        ],
        [
            'herramienta' => 'Imprimir PDF',
            'ubicacion' => $columnaAcciones . ' (ícono impresora)',
            'accion' => 'Genera PDF de la requisición en nueva pestaña.',
            'permiso' => 'listar-requisicion o editar-requisicion',
        ],
        [
            'herramienta' => 'Generar OC',
            'ubicacion' => $columnaAcciones . ' (ícono carrito)',
            'accion' => 'Asistente para crear una o más órdenes de compra (requisición APROBADA).',
            'permiso' => 'crear-ordencompra',
        ],
        [
            'herramienta' => 'Comprobantes asociados',
            'ubicacion' => $columnaAcciones . ' (ícono enlace)',
            'accion' => 'Modal con órdenes de compra vinculadas.',
            'permiso' => 'listar-requisicion o editar-requisicion',
        ],
        [
            'herramienta' => 'Eliminar',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Elimina la requisición (confirmación).',
            'permiso' => 'borrar-requisicion',
        ],
    ],
    'requisicion_edicion' => [
        [
            'herramienta' => 'Datos principales / Historia / Archivos / Árbol / Presupuestos',
            'ubicacion' => 'Botones de solapa bajo el título',
            'accion' => 'Cambia la vista del formulario sin salir de la pantalla.',
            'permiso' => 'editar-requisicion',
        ],
        [
            'herramienta' => 'PDF requisición',
            'ubicacion' => 'Barra superior',
            'accion' => 'Imprime la requisición completa.',
            'permiso' => 'listar-requisicion o editar-requisicion',
        ],
        [
            'herramienta' => 'Wizard múltiples OC',
            'ubicacion' => 'Barra superior (verde)',
            'accion' => 'Genera órdenes de compra desde requisición aprobada.',
            'permiso' => 'crear-ordencompra',
        ],
        [
            'herramienta' => 'Enviar al árbol',
            'ubicacion' => 'Barra superior',
            'accion' => 'Inicia aprobación cuando el estado es EN COMPRAS.',
            'permiso' => 'editar-requisicion',
        ],
        [
            'herramienta' => '+ Agregar renglón',
            'ubicacion' => 'Grilla de líneas',
            'accion' => 'Añade artículo, cantidad, precio, partida/CAPEX, fecha entrega.',
            'permiso' => 'editar-requisicion',
        ],
        [
            'herramienta' => 'Consulta partida / CAPEX',
            'ubicacion' => 'Por línea (íconos en grilla)',
            'accion' => 'Busca partida de gasto o CAPEX desde último presupuesto.',
            'permiso' => 'editar-requisicion',
        ],
        [
            'herramienta' => 'Nuevo presupuesto',
            'ubicacion' => 'Solapa Presupuestos → pie',
            'accion' => 'Alta de cotización de proveedor con precios por línea y adjuntos.',
            'permiso' => 'editar-requisicion',
        ],
    ],
    'listaprecio_listado' => [
        [
            'herramienta' => 'Editar',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Modifica cabecera y líneas de la lista.',
            'permiso' => 'editar-listaprecio-proveedor',
        ],
        [
            'herramienta' => 'Importar Excel',
            'ubicacion' => $columnaAcciones . ' (ícono Excel)',
            'accion' => 'Carga masiva de artículos y precios en la lista.',
            'permiso' => 'editar-listaprecio-proveedor y actualizar-listaprecio-proveedor',
        ],
        [
            'herramienta' => 'Cambiar estado',
            'ubicacion' => $columnaAcciones . ' (toggle)',
            'accion' => 'Alterna ACTIVA / INACTIVA.',
            'permiso' => 'actualizar-listaprecio-proveedor',
        ],
        [
            'herramienta' => 'Eliminar',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Baja lógica de la lista.',
            'permiso' => 'borrar-listaprecio-proveedor',
        ],
    ],
    'ordencompra_listado' => [
        [
            'herramienta' => 'KPIs',
            'ubicacion' => $barraListado . ' (botón verde)',
            'accion' => 'Tablero de KPIs de proceso y productividad de Compras (metas de ciclo, gestión, % abiertas, OC/ahorro por comprador).',
            'permiso' => 'listar-kpi-compras',
        ],
        [
            'herramienta' => 'Editar',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Formulario completo de la OC.',
            'permiso' => 'editar-ordencompra',
        ],
        [
            'herramienta' => 'Solo consulta',
            'ubicacion' => $columnaAcciones . ' (ojo)',
            'accion' => 'Vista de solo lectura.',
            'permiso' => 'listar-ordencompra',
        ],
        [
            'herramienta' => 'PDF vertical / PDF apaisado',
            'ubicacion' => $columnaAcciones . ' (impresora / flechas)',
            'accion' => 'Impresión en formato retrato o legal apaisado.',
            'permiso' => 'listar-ordencompra o editar-ordencompra',
        ],
        [
            'herramienta' => 'Ver requisición',
            'ubicacion' => $columnaAcciones . ' (enlace)',
            'accion' => 'Abre la requisición origen en nueva pestaña.',
            'permiso' => 'listar-requisicion o editar-requisicion',
        ],
        [
            'herramienta' => 'Cambiar estado',
            'ubicacion' => $columnaAcciones . ' (flechas cruzadas)',
            'accion' => 'Modal para nuevo estado y observación.',
            'permiso' => 'actualizar-ordencompra',
        ],
        [
            'herramienta' => 'Cambiar sector',
            'ubicacion' => $columnaAcciones . ' (carpeta)',
            'accion' => 'Reasigna sector de legajo de compra.',
            'permiso' => 'actualizar-ordencompra',
        ],
        [
            'herramienta' => 'Reactivar',
            'ubicacion' => $columnaAcciones . ' (deshacer)',
            'accion' => 'Pasa de SUSPENDIDA a PENDIENTE.',
            'permiso' => 'actualizar-ordencompra',
        ],
        [
            'herramienta' => 'Eliminar',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Borrado lógico de la OC.',
            'permiso' => 'borrar-ordencompra',
        ],
    ],
    'ordencompra_edicion' => [
        [
            'herramienta' => 'Buscar requisición',
            'ubicacion' => 'Cabecera',
            'accion' => 'Modal para cargar datos desde requisición aprobada.',
            'permiso' => 'crear-ordencompra / editar-ordencompra',
        ],
        [
            'herramienta' => 'Consulta proveedor / artículo',
            'ubicacion' => 'Cabecera y líneas',
            'accion' => 'Selectores modales de maestros.',
            'permiso' => 'editar-ordencompra',
        ],
        [
            'herramienta' => 'Origen del precio',
            'ubicacion' => 'Por línea',
            'accion' => 'Elige precio desde lista proveedor, presupuesto o requisición.',
            'permiso' => 'editar-ordencompra',
        ],
        [
            'herramienta' => 'Comprobantes y cuotas',
            'ubicacion' => 'Solapa / sección inferior',
            'accion' => 'Define comprobantes esperados; asistente por condición de pago o manual.',
            'permiso' => 'editar-ordencompra',
        ],
        [
            'herramienta' => 'Árbol de aprobación OC',
            'ubicacion' => 'Solapa del formulario',
            'accion' => 'Envío, historial y movimientos de aprobación.',
            'permiso' => 'editar-ordencompra',
        ],
    ],
    'contrato_bloque_oc' => [
        [
            'herramienta' => 'Contrato / OC abierta',
            'ubicacion' => 'Cabecera del bloque Contrato (casilla)',
            'accion' => 'Marca la OC como contrato y despliega los campos de vigencia, tope y avisos.',
            'permiso' => 'crear-ordencompra / editar-ordencompra',
        ],
        [
            'herramienta' => 'Vigencia desde / hasta',
            'ubicacion' => 'Bloque Contrato',
            'accion' => 'Período del contrato. La fecha hasta dispara los avisos de vencimiento.',
            'permiso' => 'crear-ordencompra / editar-ordencompra',
        ],
        [
            'herramienta' => 'Monto contratado + Moneda del tope',
            'ubicacion' => 'Bloque Contrato',
            'accion' => 'Tope de consumo y moneda en que se controla. Vacío = sin tope.',
            'permiso' => 'crear-ordencompra / editar-ordencompra',
        ],
        [
            'herramienta' => 'Se renueva automáticamente + Días de preaviso',
            'ubicacion' => 'Bloque Contrato',
            'accion' => 'Calcula la fecha límite para notificar la no renovación (fin de vigencia menos los días indicados).',
            'permiso' => 'crear-ordencompra / editar-ordencompra',
        ],
        [
            'herramienta' => 'Días de aviso',
            'ubicacion' => 'Bloque Contrato',
            'accion' => 'Umbrales propios del contrato separados por coma. Vacío usa el default del sistema.',
            'permiso' => 'crear-ordencompra / editar-ordencompra',
        ],
        [
            'herramienta' => 'Responsable',
            'ubicacion' => 'Bloque Contrato',
            'accion' => 'Usuario dueño del contrato; recibe siempre los avisos de sus contratos.',
            'permiso' => 'crear-ordencompra / editar-ordencompra',
        ],
        [
            'herramienta' => 'Recepción para facturar',
            'ubicacion' => 'Bloque Contrato',
            'accion' => 'Obligatoria (factura contra COM) o no requiere recepción (abonos, honorarios). Fija la ruta de la factura mientras el contrato esté vigente.',
            'permiso' => 'crear-ordencompra / editar-ordencompra',
        ],
        [
            'herramienta' => 'Cuenta contable de las facturas',
            'ubicacion' => 'Bloque Contrato (solo si no requiere recepción)',
            'accion' => 'De los artículos de la OC, o cuenta indicada en este contrato. Aplica al neto; IVA y percepciones siguen el concepto de IVA compra.',
            'permiso' => 'crear-ordencompra / editar-ordencompra',
        ],
        [
            'herramienta' => 'Cuenta a imputar',
            'ubicacion' => 'Bloque Contrato (si la imputación no es por artículos)',
            'accion' => 'Código + Enter o lupa para indicar la cuenta DEBE del neto de todas las facturas del contrato. Obligatoria. Debe ser de la misma empresa de la OC.',
            'permiso' => 'crear-ordencompra / editar-ordencompra',
        ],
        [
            'herramienta' => 'Estado actual',
            'ubicacion' => 'Pie del bloque Contrato',
            'accion' => 'Muestra consumido, porcentaje del tope, vencimiento y origen del consumo (recepción / factura).',
            'permiso' => 'listar-ordencompra',
        ],
    ],
    'contrato_factura' => [
        [
            'herramienta' => 'Con OC (número de 6 dígitos)',
            'ubicacion' => 'Compras → Comprobantes de proveedor → Cargar factura',
            'accion' => 'Abre la factura vinculada a la orden. Si hay contrato vigente, fija el modo de carga y la imputación del neto.',
            'permiso' => 'crear-comprobante-proveedor',
        ],
        [
            'herramienta' => 'Modo de carga (fijo por contrato)',
            'ubicacion' => 'Solapa Datos principales de la factura',
            'accion' => 'Con recepción obligatoria queda en factura contra COM; sin recepción queda en gasto sin recepción. No se puede cambiar mientras el contrato esté vigente.',
            'permiso' => 'crear-comprobante-proveedor / editar-comprobante-proveedor',
        ],
        [
            'herramienta' => 'Solapa Recepciones COM',
            'ubicacion' => 'Factura contra COM',
            'accion' => 'Solo aparece si el contrato exige recepción. Hay que elegir la COM con provisión antes de grabar.',
            'permiso' => 'crear-comprobante-proveedor / editar-comprobante-proveedor',
        ],
        [
            'herramienta' => 'Cuenta DEBE del neto',
            'ubicacion' => 'Solapa Conceptos de IVA (ruta sin recepción y cuenta del contrato)',
            'accion' => 'Queda precargada con la cuenta del contrato. Código + Enter o lupa para cambiarla en esa factura. Obligatoria en renglones de neto si el contrato no tiene cuenta.',
            'permiso' => 'crear-comprobante-proveedor / editar-comprobante-proveedor',
        ],
        [
            'herramienta' => 'Badges del contrato',
            'ubicacion' => 'Bloque de la OC en la factura',
            'accion' => 'Muestra si el contrato está vigente, si exige COM y de dónde sale la cuenta del neto.',
            'permiso' => 'crear-comprobante-proveedor / editar-comprobante-proveedor',
        ],
    ],
    'contrato_reporte' => [
        [
            'herramienta' => 'Consultar',
            'ubicacion' => 'Panel de filtros',
            'accion' => 'Genera el listado según empresa, tipo de alerta, horizonte de días, proveedor y responsable.',
            'permiso' => 'listar-reporte-contrato-vencimiento',
        ],
        [
            'herramienta' => 'Tipo de alerta',
            'ubicacion' => 'Panel de filtros',
            'accion' => 'Por vencer, preaviso pendiente, consumo en zona de alerta, vencidos o sin vigencia cargada.',
            'permiso' => 'listar-reporte-contrato-vencimiento',
        ],
        [
            'herramienta' => 'Solo sin responsable',
            'ubicacion' => 'Panel de filtros',
            'accion' => 'Aísla los contratos sin dueño asignado, que son los que suelen quedar sin seguimiento.',
            'permiso' => 'listar-reporte-contrato-vencimiento',
        ],
        [
            'herramienta' => 'PDF / Excel / CSV',
            'ubicacion' => $sobreGrilla,
            'accion' => 'Exporta el resultado completo del filtro, no solo la página visible.',
            'permiso' => 'listar-reporte-contrato-vencimiento',
        ],
        [
            'herramienta' => 'Número de OC (enlace)',
            'ubicacion' => 'Columna OC de la grilla',
            'accion' => 'Abre la orden de compra en pestaña nueva para revisar o renovar el contrato.',
            'permiso' => 'editar-ordencompra o listar-ordencompra',
        ],
        [
            'herramienta' => 'Resumen del filtro',
            'ubicacion' => 'Franja sobre la grilla',
            'accion' => 'Totales de contratos, vencidos, por vencer, tope, recibido, facturado, consumido y disponible.',
            'permiso' => 'listar-reporte-contrato-vencimiento',
        ],
    ],
    'tablas_maestras' => [
        [
            'herramienta' => 'Nuevo registro',
            'ubicacion' => $barraListado,
            'accion' => 'Alta de fila en la tabla maestra.',
            'permiso' => 'crear-* según tabla',
        ],
        [
            'herramienta' => 'Editar / Eliminar',
            'ubicacion' => $columnaAcciones,
            'accion' => 'Mantiene catálogos auxiliares del módulo.',
            'permiso' => 'editar-* / borrar-*',
        ],
    ],
];
