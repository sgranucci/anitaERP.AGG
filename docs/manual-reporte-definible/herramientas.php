<?php

/**
 * Herramientas del manual — Reportes contables definibles.
 */
$catalogo = 'Catálogo Reportes contables definibles';
$disenar = 'Diseñador del informe';
$ejecutar = 'Pantalla Ejecutar reporte definible';
$paridad = 'Pantalla Paridad Anita';

return [
    'catalogo_cabecera' => [
        [
            'herramienta' => 'Filtros / Nuevo informe',
            'ubicacion' => $catalogo.' — toolbar',
            'accion' => 'Abre el panel de filtros o crea un informe manual (pasa al diseñador).',
            'permiso' => 'listar-reporte-definible / crear-reporte-definible',
        ],
        [
            'herramienta' => 'Sets de cuentas',
            'ubicacion' => $catalogo.' — cabecera',
            'accion' => 'Abre el ABM de conjuntos reutilizables de cuentas.',
            'permiso' => 'editar-reporte-definible',
        ],
        [
            'herramienta' => 'Desde plantilla… + Crear',
            'ubicacion' => $catalogo.' — cabecera',
            'accion' => 'Copia una plantilla sembrada (Balance / EERR) como informe editable.',
            'permiso' => 'crear-reporte-definible',
        ],
        [
            'herramienta' => 'Traer de Anita',
            'ubicacion' => $catalogo.' — bloque Importar Anita',
            'accion' => 'Importa definiciones Anita (rango Desde/Hasta nro.; vacío = todos).',
            'permiso' => 'importar-reporte-definible',
        ],
        [
            'herramienta' => 'Export PDF / Excel / CSV',
            'ubicacion' => $catalogo.' — toolbar export',
            'accion' => 'Exporta el listado del catálogo con los filtros activos.',
            'permiso' => 'listar-reporte-definible',
        ],
    ],
    'catalogo_fila' => [
        [
            'herramienta' => 'Ejecutar (play)',
            'ubicacion' => 'Columna Acciones',
            'accion' => 'Abre la pantalla de ejecución del informe.',
            'permiso' => 'ejecutar-reporte-definible (o listar)',
        ],
        [
            'herramienta' => 'Diseñar estructura (lápiz)',
            'ubicacion' => 'Columna Acciones',
            'accion' => 'Abre el diseñador (solapas Estructura, Layouts, etc.).',
            'permiso' => 'editar-reporte-definible',
        ],
        [
            'herramienta' => 'Copiar',
            'ubicacion' => 'Columna Acciones',
            'accion' => 'Duplica el informe (estructura, layouts y notas vigentes).',
            'permiso' => 'crear-reporte-definible',
        ],
        [
            'herramienta' => 'Eliminar',
            'ubicacion' => 'Columna Acciones',
            'accion' => 'Borra el informe si el ACL lo permite.',
            'permiso' => 'eliminar-reporte-definible',
        ],
    ],
    'disenar_estructura' => [
        [
            'herramienta' => 'Árbol + Rubro',
            'ubicacion' => $disenar.' — solapa Estructura',
            'accion' => 'Alta de rubros (Nombre, Tipo). Selección para editar detalle y cuentas.',
            'permiso' => 'actualizar-reporte-definible',
        ],
        [
            'herramienta' => 'Guardar rubro',
            'ubicacion' => 'Panel Rubro seleccionado',
            'accion' => 'Persiste código de línea, fórmula, set, lado, estilos.',
            'permiso' => 'actualizar-reporte-definible',
        ],
        [
            'herramienta' => 'Cuentas del rubro',
            'ubicacion' => 'Panel inferior de Estructura',
            'accion' => 'Agrega cuenta/rango (desde–hasta), signo +/−, origen R/P.',
            'permiso' => 'actualizar-reporte-definible',
        ],
        [
            'herramienta' => 'Ejecutar / Catálogo',
            'ubicacion' => 'Toolbar del diseñador',
            'accion' => 'Salta a ejecutar o vuelve al listado.',
            'permiso' => 'ejecutar / listar',
        ],
    ],
    'disenar_cobertura' => [
        [
            'herramienta' => '% Cobertura / huérfanas',
            'ubicacion' => 'Solapa Cobertura plan',
            'accion' => 'Muestra cuentas asignadas, sin asignar y duplicadas.',
            'permiso' => 'editar-reporte-definible',
        ],
        [
            'herramienta' => '+ Al rubro seleccionado',
            'ubicacion' => 'Solapa Cobertura plan',
            'accion' => 'Agrega las huérfanas de la muestra al rubro activo en Estructura.',
            'permiso' => 'actualizar-reporte-definible',
        ],
    ],
    'disenar_gobernanza' => [
        [
            'herramienta' => 'Actualizar cabecera',
            'ubicacion' => 'Solapa Cabecera',
            'accion' => 'Nombre, títulos, tipo, vigencia, estado Publicado/Borrador, activo.',
            'permiso' => 'actualizar-reporte-definible',
        ],
        [
            'herramienta' => 'Guardar accesos',
            'ubicacion' => 'Solapa Acceso',
            'accion' => 'Whitelist de usuario IDs. Vacío = todos con permiso de menú.',
            'permiso' => 'actualizar-reporte-definible',
        ],
        [
            'herramienta' => 'Validar definición',
            'ubicacion' => 'Solapa Versiones',
            'accion' => 'Chequeo estático de fórmulas, layouts y ecuaciones.',
            'permiso' => 'editar-reporte-definible',
        ],
        [
            'herramienta' => 'Publicar versión / Restaurar / Diff',
            'ubicacion' => 'Solapa Versiones',
            'accion' => 'Congela o restaura la estructura; Diff compara con la actual.',
            'permiso' => 'actualizar-reporte-definible',
        ],
    ],
    'layouts' => [
        [
            'herramienta' => 'Presets de sistema / Crear layout',
            'ubicacion' => 'Solapa Layouts',
            'accion' => 'Clona un preset o crea un layout del informe.',
            'permiso' => 'actualizar-reporte-definible',
        ],
        [
            'herramienta' => '+ Columna',
            'ubicacion' => 'Detalle del layout',
            'accion' => 'Agrega columna con tipo y meta (offset, valuación, moneda, expr…).',
            'permiso' => 'actualizar-reporte-definible',
        ],
        [
            'herramienta' => 'Default',
            'ubicacion' => 'Detalle del layout',
            'accion' => 'Marca el layout por defecto al ejecutar.',
            'permiso' => 'actualizar-reporte-definible',
        ],
    ],
    'consolidacion' => [
        [
            'herramienta' => 'Guardar %',
            'ubicacion' => 'Participación %',
            'accion' => 'Define % por empresa con vigencia desde/hasta.',
            'permiso' => 'actualizar-reporte-definible',
        ],
        [
            'herramienta' => 'Eliminaciones IC',
            'ubicacion' => 'Bloque Eliminaciones',
            'accion' => 'Rango de cuentas a eliminar: todas las empresas o pareja A/B.',
            'permiso' => 'actualizar-reporte-definible',
        ],
    ],
    'ejecutar_filtros' => [
        [
            'herramienta' => 'Informe / Variante',
            'ubicacion' => $ejecutar.' — filtros',
            'accion' => 'Elige el informe; guarda o recupera un set de filtros nombrado.',
            'permiso' => 'ejecutar-reporte-definible',
        ],
        [
            'herramienta' => 'Apertura / Layout / Período',
            'ubicacion' => $ejecutar.' — filtros',
            'accion' => 'Por períodos o entre fechas; layout de columnas; mes/año o fechas.',
            'permiso' => 'ejecutar-reporte-definible',
        ],
        [
            'herramienta' => 'Empresas + consolidar',
            'ubicacion' => $ejecutar.' — filtros',
            'accion' => 'Selecciona empresas asignadas; consolida con % e IC.',
            'permiso' => 'ejecutar-reporte-definible',
        ],
        [
            'herramienta' => 'Consultar',
            'ubicacion' => $ejecutar,
            'accion' => 'Calcula el informe (overlay Calculando informe…).',
            'permiso' => 'ejecutar-reporte-definible',
        ],
    ],
    'ejecutar_resultado' => [
        [
            'herramienta' => 'Pdf / Excel / Csv',
            'ubicacion' => 'Toolbar post-consulta',
            'accion' => 'Exporta el resultado con los mismos filtros y montos formateados.',
            'permiso' => 'ejecutar-reporte-definible',
        ],
        [
            'herramienta' => 'Paridad Anita',
            'ubicacion' => 'Toolbar post-consulta',
            'accion' => 'Abre el control de tres brazos con los filtros actuales.',
            'permiso' => 'ejecutar-reporte-definible',
        ],
        [
            'herramienta' => 'Publicar / Publicados',
            'ubicacion' => 'Toolbar post-consulta',
            'accion' => 'Congela el resultado o lista publicaciones anteriores.',
            'permiso' => 'ejecutar-reporte-definible',
        ],
        [
            'herramienta' => 'Detalle (lupa)',
            'ubicacion' => 'Columna Detalle de la grilla',
            'accion' => 'Drill rubro → cuentas → asientos → documento origen.',
            'permiso' => 'ejecutar (+ asientos para abrir asiento)',
        ],
    ],
    'publicacion' => [
        [
            'herramienta' => 'Publicar este resultado',
            'ubicacion' => 'Modal Publicar',
            'accion' => 'Nombre + observación; guarda hash, filtros, filas y notas.',
            'permiso' => 'ejecutar-reporte-definible',
        ],
        [
            'herramienta' => 'Reimprimir / PDF',
            'ubicacion' => 'Listado Publicados / vista publicación',
            'accion' => 'Muestra el documento congelado sin recalcular.',
            'permiso' => 'ejecutar / listar',
        ],
    ],
    'distribucion' => [
        [
            'herramienta' => 'Guardar envío',
            'ubicacion' => $disenar.' — solapa Distribución',
            'accion' => 'Programa periodicidad, destinatarios, formato y opciones.',
            'permiso' => 'actualizar-reporte-definible',
        ],
        [
            'herramienta' => 'Probar',
            'ubicacion' => 'Tabla de envíos',
            'accion' => 'Envía el mail ahora con la configuración guardada.',
            'permiso' => 'actualizar-reporte-definible',
        ],
        [
            'herramienta' => 'Editar / Eliminar',
            'ubicacion' => 'Tabla de envíos',
            'accion' => 'Modifica o borra la suscripción.',
            'permiso' => 'actualizar-reporte-definible',
        ],
    ],
    'notas' => [
        [
            'herramienta' => 'Guardar nota',
            'ubicacion' => $disenar.' — solapa Notas al pie',
            'accion' => 'Alta o edición (versiona el texto). Vigencia AAAAMM opcional.',
            'permiso' => 'actualizar-reporte-definible',
        ],
        [
            'herramienta' => 'Historial',
            'ubicacion' => 'Tabla de notas',
            'accion' => 'Muestra versiones anteriores (quién / cuándo / texto).',
            'permiso' => 'editar-reporte-definible',
        ],
        [
            'herramienta' => 'Eliminar',
            'ubicacion' => 'Tabla de notas',
            'accion' => 'Borra la cadena completa de versiones.',
            'permiso' => 'actualizar-reporte-definible',
        ],
    ],
    'paridad' => [
        [
            'herramienta' => 'Tolerancia / Solo diferencias',
            'ubicacion' => $paridad.' — filtros',
            'accion' => 'Recalcula mostrando solo rubros fuera de tolerancia.',
            'permiso' => 'ejecutar-reporte-definible',
        ],
        [
            'herramienta' => 'Export Pdf / Excel / Csv',
            'ubicacion' => $paridad,
            'accion' => 'Exporta el control de paridad con logos y montos formateados.',
            'permiso' => 'ejecutar-reporte-definible',
        ],
        [
            'herramienta' => 'Fuente de verdad',
            'ubicacion' => 'Badge superior',
            'accion' => 'Indica si el período se interpreta con verdad ERP o Anita.',
            'permiso' => 'ejecutar-reporte-definible',
        ],
    ],
    'alertas' => [
        [
            'herramienta' => 'Agregar alerta',
            'ubicacion' => $disenar.' — solapa Alertas',
            'accion' => 'Tipo Var % / Cobertura / Ecuación + umbral / expresión / etiqueta.',
            'permiso' => 'actualizar-reporte-definible',
        ],
        [
            'herramienta' => 'Eliminar alerta',
            'ubicacion' => 'Tabla de alertas',
            'accion' => 'Quita la alerta del informe.',
            'permiso' => 'actualizar-reporte-definible',
        ],
    ],
];
