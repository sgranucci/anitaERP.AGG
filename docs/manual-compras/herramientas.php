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
