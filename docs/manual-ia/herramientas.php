<?php

/**
 * Herramientas del manual — Plataforma IA.
 */
$fab = 'Panel flotante IA (FAB) en el layout';
$gob = 'Configuración → Gobernanza IA';
$cola = 'Configuración → Cola agentes IA (HITL)';
$precarga = 'Compras → Precarga comprobante proveedor';
$env = '.env / config/ai.php (administrador técnico)';

return [
    'document_ai' => [
        [
            'herramienta' => 'Analizar PDF (modal)',
            'ubicacion' => $precarga.' → PDF+IA',
            'accion' => 'Sube el PDF, obtiene preview tipado (OC, conceptos, CAE) y permite confirmar precarga.',
            'permiso' => 'crear-precarga-proveedores',
        ],
        [
            'herramienta' => 'Aplicar automático',
            'ubicacion' => 'Modal PDF+IA (si score ≥ umbral)',
            'accion' => 'Destaca confirmación cuando la política marca ai_auto_aplicable; el operador confirma con un clic.',
            'permiso' => 'crear-precarga-proveedores',
        ],
        [
            'herramienta' => 'Portal proveedores',
            'ubicacion' => 'Compras → Portal proveedores',
            'accion' => 'Canal interno: Facturas (PDF+IA), Pagos (grilla/consulta/PDF) y Retenciones (certificados).',
            'permiso' => 'listar/cargar-portal-proveedores',
        ],
        [
            'herramienta' => 'Ingesta mail',
            'ubicacion' => 'Schedule + label IMAP',
            'accion' => 'Lee no leídos con PDF + filtro candidato; encola ProcesarFacturaMailJob.',
            'permiso' => 'Canal sistema (PRECARGA_MAIL_*)',
        ],
        [
            'herramienta' => 'Batch carpeta',
            'ubicacion' => 'Facturas_scan/entrada_ia',
            'accion' => 'Procesa PDFs fríos/calientes hacia precarga + archivo.',
            'permiso' => 'Canal sistema (PRECARGA_BATCH_IA_*)',
        ],
    ],
    'panel_fab' => [
        [
            'herramienta' => 'FAB / abrir panel',
            'ubicacion' => $fab,
            'accion' => 'Abre el copiloto de consultas (chips + NL).',
            'permiso' => 'ejecutar-consulta-ia',
        ],
        [
            'herramienta' => 'Chips de intent',
            'ubicacion' => $fab,
            'accion' => 'Lista solo intents permitidos para el rol (oculta mayor si falta consulta-ia-contable).',
            'permiso' => 'ejecutar-consulta-ia + permiso de módulo',
        ],
        [
            'herramienta' => 'Pregunta en lenguaje natural',
            'ubicacion' => $fab,
            'accion' => 'Router reglas → LLM clasificador → grounding ERP / RAG.',
            'permiso' => 'ejecutar-consulta-ia',
        ],
        [
            'herramienta' => 'Exportar Excel',
            'ubicacion' => $fab,
            'accion' => 'Exporta la tabla del intent actual (mismo permiso que consultar).',
            'permiso' => 'ejecutar-consulta-ia + intent',
        ],
    ],
    'gobernanza' => [
        [
            'herramienta' => 'Consultar KPIs',
            'ubicacion' => $gob,
            'accion' => 'Filtra por fechas/skill/acción y muestra tasas de aceptación.',
            'permiso' => 'listar-ai-decisiones',
        ],
        [
            'herramienta' => 'Salud operativa',
            'ubicacion' => $gob.' (tarjeta superior)',
            'accion' => 'Flags AI, mail, batch, eventos pendientes, RAG, MCP.',
            'permiso' => 'listar-ai-decisiones',
        ],
        [
            'herramienta' => 'Export PDF/Excel/CSV',
            'ubicacion' => $gob,
            'accion' => 'Exporta el detalle de ai_decision con filtros activos.',
            'permiso' => 'listar-ai-decisiones',
        ],
        [
            'herramienta' => 'Manual IA',
            'ubicacion' => $gob.' / Centro de ayuda',
            'accion' => 'Abre este manual.',
            'permiso' => 'Usuario autenticado',
        ],
    ],
    'hitl' => [
        [
            'herramienta' => 'Filtrar cola',
            'ubicacion' => $cola,
            'accion' => 'Estado / severidad / nombre de evento.',
            'permiso' => 'listar-ai-decisiones',
        ],
        [
            'herramienta' => 'Visto',
            'ubicacion' => 'Fila del evento',
            'accion' => 'Marca que un humano ya miró el plan (estado visto).',
            'permiso' => 'listar-ai-decisiones',
        ],
        [
            'herramienta' => 'Descartar',
            'ubicacion' => 'Fila del evento',
            'accion' => 'Cierra el evento como no accionable / falso positivo.',
            'permiso' => 'listar-ai-decisiones',
        ],
        [
            'herramienta' => 'Resuelto',
            'ubicacion' => 'Fila del evento',
            'accion' => 'Cierra el evento tras actuar en el ERP (el plan no ejecuta solo).',
            'permiso' => 'listar-ai-decisiones',
        ],
        [
            'herramienta' => 'Abrir entidad',
            'ubicacion' => 'Fila del evento',
            'accion' => 'Deep-link a conciliación / jornada gastronomía cuando aplica.',
            'permiso' => 'Según pantalla destino',
        ],
        [
            'herramienta' => 'AI_AGENTE_EVENTO_*',
            'ubicacion' => $env,
            'accion' => 'Habilita puente, whitelist de eventos y tamaño de payload.',
            'permiso' => 'Administrador técnico',
        ],
    ],
];
