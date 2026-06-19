<?php

/**
 * Manual de usuario — Canjes Marketing (Ventas → Gastronomía → Canjes).
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Canjes Marketing',
    'version' => '1.0',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Introducción',
            'parrafos' => [
                'El módulo Canjes (menú Ventas → Gastronomía → Canjes) está pensado para el equipo de Marketing: registrar entregas de productos a clientes VIP con descuento prefijado y emitir la factura fiscal correspondiente, sin usar el facturador principal de salón.',
                'Este manual cubre las tres pantallas del submenú Canjes: Clientes VIP, Facturador canjes marketing y Listado canjes marketing.',
                'La operación diaria de sala (mesas, cobranza, cierres de turno, canjes Wigos de premios/fidelidad) sigue documentada en el manual de Gastronomía. Aquí se explica solo el circuito de canjes para beneficiarios VIP.',
            ],
        ],
        [
            'titulo' => '2. Gastronomía vs Canjes Marketing',
            'captura_id' => 'flujo',
            'parrafos' => [
                'Antes de operar, conviene distinguir qué gestiona cada área:',
            ],
            'tabla' => [
                'caption' => 'Responsabilidades',
                'headers' => ['Proceso', 'Quién lo opera', 'Dónde en el menú'],
                'rows' => [
                    ['Apertura / cierre de jornada', 'Encargado gastronomía', 'Ventas → Gastronomía → Jornada'],
                    ['Habilitación de turno por PC', 'Caja / gastronomía', 'Ventas → Gastronomía → Habilitación de turno'],
                    ['Facturación de mesas y cobranza', 'Salón / caja', 'Ventas → Gastronomía → Proceso de facturación'],
                    ['Canjes Wigos (cupón premio, tarjeta fidelidad)', 'Salón / caja', 'Facturador gastronomía (íconos regalo/tarjeta)'],
                    ['Padrón VIP y canjes marketing', 'Marketing', 'Ventas → Gastronomía → Canjes'],
                ],
            ],
            'items' => [
                'Para facturar un canje marketing debe existir jornada abierta en la empresa del punto de venta. Marketing no abre jornada desde este módulo.',
                'El facturador de canjes no exige habilitación de turno por PC (por defecto); solo login de mozo al ingresar.',
                'La factura se emite a Consumidor final; el cliente VIP es el beneficiario del descuento, no el receptor fiscal.',
            ],
        ],
        [
            'titulo' => '3. Requisitos previos',
            'parrafos' => [
                'Verifique estos puntos antes de usar el facturador en sala o feria:',
            ],
            'tabla' => [
                'caption' => 'Checklist',
                'headers' => ['Requisito', 'Detalle'],
                'rows' => [
                    ['Permiso', 'usar-facturador-canje-marketing (facturador); listar-cliente-vip-gastronomia (ABM VIP); listar-canje-marketing-gastronomia (reporte)'],
                    ['PV gastronomía', 'La PC debe tener configuración en Configuración PV gastronomía (identificador de terminal)'],
                    ['Jornada', 'Abierta para la empresa del PV (gestiona gastronomía)'],
                    ['Descuento', 'Código configurado (típ. 40) en descuentos gastronomía — GASTRONOMIA_CANJE_MARKETING_DESCUENTO_CODIGO'],
                    ['Mozos', 'Operadores dados de alta para la empresa del PV'],
                ],
            ],
        ],
        [
            'titulo' => '4. Clientes VIP — padrón y búsqueda',
            'captura_id' => 'cliente_vip_listado',
            'parrafos' => [
                'Ruta: ventas/gastronomia/canjes/cliente-vip. Aquí se mantiene el padrón de beneficiarios.',
            ],
            'items' => [
                'Búsqueda rápida (barra superior): busca en todos los campos; tolera errores de tipeo (≥ 6 caracteres en textos).',
                'Panel Filtros: empresa, modo (cualquier campo / campo determinado), operador (contiene, igual, etc.), valor.',
                'Campos filtrables: ID, Número Anita, documento, apellido, nombre, nickname, localidad, empresa.',
                'Nuevo registro: documento, apellido, nombre (obligatorios), nickname y localidad opcionales. El número Anita se asigna al crear.',
                'Sincronizar Anita (si está habilitado): importa VIP desde legacy Informix.',
                'Exportación PDF / Excel / CSV desde la barra de exportación (respeta filtros).',
            ],
            'tabla' => [
                'caption' => 'Permisos ABM VIP',
                'headers' => ['Permiso', 'Acción'],
                'rows' => [
                    ['listar-cliente-vip-gastronomia', 'Ver listado'],
                    ['crear-cliente-vip-gastronomia', 'Alta'],
                    ['editar-cliente-vip-gastronomia', 'Modificar'],
                    ['borrar-cliente-vip-gastronomia', 'Eliminar'],
                ],
            ],
        ],
        [
            'titulo' => '5. Facturador canjes marketing',
            'captura_id' => 'facturador_login',
            'parrafos' => [
                'Ruta: ventas/gastronomia/canjes/proceso-facturacion. Comandera simplificada para entregar productos con descuento prefijado y factura de cortesía (sin cobranza).',
            ],
            'flujo' => "Login mozo → Cuenta activa → Cargar SKU(s) → Identificar VIP\n         → F8 Facturar con descuento → Comprobante + registro\n         → Vuelve al login para el siguiente canje",
            'items' => [
                'Al abrir la pantalla aparece el modal Ingreso mozo (código, lupa, clave). Enter en código valida; Enter en clave confirma.',
                'Se reutiliza la cuenta abierta del mismo mozo en la misma PC si existe.',
                'Descuento de cabecera se aplica automáticamente (código marketing).',
                'Tras facturar correctamente, la pantalla vuelve al login de mozo.',
            ],
        ],
        [
            'titulo' => '6. Login mozo, cuentas y carga de artículos',
            'captura_id' => 'facturador_pantalla',
            'parrafos' => [
                'Una vez autenticado el mozo, la pantalla principal muestra la barra de cuenta, el panel de cuentas abiertas en la terminal, la zona de descuento/VIP y la carga de SKU.',
            ],
            'tabla' => [
                'caption' => 'Herramientas de la pantalla',
                'headers' => ['Elemento', 'Uso'],
                'rows' => [
                    ['Cambiar mozo', 'Nuevo login de operador'],
                    ['Cuentas abiertas', 'Seleccionar otra cuenta; Nueva cuenta; Cerrar cuenta; Cerrar todas'],
                    ['SKU + Enter', 'Agrega cantidad 1 (catálogo prefijo V + dígitos si está configurado)'],
                    ['F1 / lupa', 'Consulta de artículos'],
                    ['+ / Agregar', 'Modal de cantidad (y opcionales si aplican)'],
                    ['Tab en SKU', 'Resuelve artículo y enfoca Agregar'],
                    ['Grilla ítems', '+/− cantidad; comentario cocina; eliminar línea'],
                    ['Facturar desc. / F8', 'Abre modal de facturación con descuento'],
                ],
            ],
        ],
        [
            'titulo' => '7. Identificar cliente VIP (beneficiario)',
            'parrafos' => [
                'El VIP es **obligatorio** antes de facturar. Cuatro formas de cargarlo:',
            ],
            'tabla' => [
                'caption' => 'Métodos de búsqueda VIP',
                'headers' => ['Método', 'Pasos'],
                'rows' => [
                    ['Código Anita', 'Campo Cód. → Enter. Busca por numeroid, luego documento o ID interno.'],
                    ['DNI', 'Campo DNI → Enter. Busca por nrodocumento.'],
                    ['Lupa (modal)', 'Buscar por apellido, nombre, documento o código; botón Elegir.'],
                    ['Tarjeta Wigos', 'Si está habilitado: leer tarjeta → validar → Aplicar. Crea VIP si no existe.'],
                ],
            ],
            'items' => [
                'En el modal F8, Enter en Cód. o DNI resuelve el VIP y puede facturar directamente si los datos son correctos.',
                'Si el VIP no existe, darlo de alta en Clientes VIP o usar Wigos (creación automática).',
            ],
        ],
        [
            'titulo' => '8. Facturación con F8',
            'parrafos' => [
                'En canjes marketing solo se factura con descuento (equivalente a F8 del POS gastronomía). No hay F5 ni pantalla de cobranza.',
            ],
            'items' => [
                'Requisitos: al menos un ítem; cliente VIP cargado; jornada abierta; PV fiscal operativo.',
                'F8 abre el modal «Facturar canje marketing» con el panel de descuento y VIP.',
                'Revise descuento prefijado e indique/confirme el VIP → Facturar.',
                'El sistema valida en servidor, emite factura a Consumidor final, registra la entrega en histórico y muestra progreso (ARCA, impresión térmica si aplica).',
                'Total típico con descuento 100 %: $0,01 (cortesía, sin cobranza).',
            ],
            'tabla' => [
                'caption' => 'Atajos de teclado',
                'headers' => ['Tecla', 'Acción'],
                'rows' => [
                    ['F1', 'Consulta artículos (desde zona SKU)'],
                    ['Enter', 'En SKU: agregar cant. 1; en VIP: buscar; en login: avanzar'],
                    ['+', 'Modal cantidad'],
                    ['F8', 'Facturar con descuento'],
                    ['Tab', 'SKU → foco en Agregar'],
                ],
            ],
        ],
        [
            'titulo' => '9. Diferencia con canjes del POS gastronomía',
            'tabla' => [
                'caption' => 'Canjes marketing vs Wigos en salón',
                'headers' => ['Aspecto', 'Canjes marketing', 'POS gastronomía (Wigos)'],
                'rows' => [
                    ['Beneficiario', 'Cliente VIP del padrón', 'Cupón / tarjeta fidelidad Wigos'],
                    ['Descuento', 'Código marketing (ej. 40)', 'Códigos premio/fidelidad (ej. 10)'],
                    ['Facturación', 'Solo F8 / con descuento', 'F8 obligatorio premio/fidelidad; F5 ticket tarjeta CTG'],
                    ['Operador', 'Login mozo dedicado', 'Turno habilitado + mozo de mesa'],
                ],
            ],
        ],
        [
            'titulo' => '10. Listado canjes marketing',
            'captura_id' => 'listado_marketing',
            'parrafos' => [
                'Ruta: ventas/gastronomia/canjes/listado-marketing. Reporte de entregas facturadas para control y análisis.',
            ],
            'items' => [
                'Filtros: empresa, rango de fechas (default mes actual), salas/ubicaciones (multiselect; vacío = todas).',
                'Columnas: fecha, empresa, VIP, mozo, producto, cantidad, CMV (lista configurada), precio venta, sala.',
                'Totales del filtro completo en cabecera (no solo la página visible).',
                'Enlaces a VIP, mozo, artículo y comprobante (según permisos).',
                'Export PDF (legal apaisado), Excel y CSV.',
            ],
        ],
        [
            'titulo' => '11. Errores frecuentes',
            'tabla' => [
                'caption' => 'Problema → solución',
                'headers' => ['Situación', 'Qué hacer'],
                'rows' => [
                    ['Sin PV para esta PC', 'Configurar terminal en Configuración PV gastronomía'],
                    ['Jornada cerrada', 'Pedir a gastronomía abrir jornada'],
                    ['Cliente VIP no encontrado', 'Alta en ABM, modal lupa o tarjeta Wigos'],
                    ['Cargue al menos un artículo', 'Escanear SKU antes de F8'],
                    ['Clave mozo incorrecta', 'Verificar con encargado'],
                    ['Error ARCA', 'Reintentar; escalar a sistemas si persiste'],
                ],
            ],
        ],
        [
            'titulo' => '12. Checklist operativo diario',
            'items' => [
                'Confirmar jornada abierta con gastronomía.',
                'Abrir facturador canjes marketing en la PC configurada.',
                'Login mozo → cargar producto → identificar VIP → F8.',
                'Entregar producto al beneficiario.',
                'Al cierre: cerrar cuentas abiertas sin facturar si quedaron pendientes.',
                'Supervisión: listado marketing con filtros del día.',
            ],
        ],
    ],
];
