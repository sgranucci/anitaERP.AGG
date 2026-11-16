<?php

/**
 * Manual de usuario — Consulta Inteligente (NL, explorador, tablero, envíos).
 */
return [
    'titulo' => 'Manual de Usuario',
    'subtitulo' => 'Anita ERP — Consulta Inteligente',
    'version' => '1.0',
    'fecha' => null,
    'empresa' => null,
    'url_base' => null,
    'secciones' => [
        [
            'titulo' => '1. Qué es y para quién',
            'parrafos' => [
                'Consulta Inteligente es el módulo semántico de Dirección: explorás entidades del ERP (cheques, facturas, OC, TM, Capex, etc.) con el mismo QBE del workbench, sin armar un informe a medida.',
                'Está pensado para gerencia, analistas y roles con permiso de listar en cada módulo origen. Si no ves una entidad, pedí el permiso de listar correspondiente.',
            ],
            'items' => [
                'Pregunta en lenguaje natural → arma el QBE y te muestra un preview.',
                'Explorador: filtros, vistas, export PDF/Excel/CSV y drill al comprobante.',
                'Tablero: atajos, KPIs (vs mes anterior) y gráficos con clic al explorador.',
                'Favoritos, historial, refinamiento multi-turno y envío Excel programado.',
            ],
        ],
        [
            'titulo' => '2. Mapa de pantallas',
            'tabla' => [
                'caption' => 'Pantallas',
                'headers' => ['Pantalla', 'Menú / URL', 'Para qué'],
                'rows' => [
                    ['Catálogo', 'direccion/consulta-inteligente', 'Pregunta NL, favoritos, alertas y tarjetas de entidades.'],
                    ['Explorar', '…/explorar/{entidad}', 'QBE, grilla, pin al tablero, programar Excel, snapshot.'],
                    ['Tablero', '…/tablero', 'KPIs, gráficos y atajos propios + compartidos.'],
                    ['Cruce / Join', '…/cruzar · …/unir', 'Varias entidades o unir filas relacionadas.'],
                    ['Snapshots', '…/snapshots', 'Guardar y comparar consultas pesadas.'],
                    ['Diccionario NL', '…/diccionario', 'Sinónimos editables (permiso configurar).'],
                    ['Alertas', '…/configuracion', 'Umbrales de chips operativos.'],
                ],
            ],
        ],
        [
            'titulo' => '3. Pregunta en lenguaje natural',
            'parrafos' => [
                'Escribí una frase (ej. «cheques para depositar», «pedidos pendientes este mes», «transferencias de mercadería»). El sistema usa diccionario local y, si está activo, LLM (Groq → Gemini) solo cuando no hay match local.',
            ],
            'items' => [
                'Armar QBE: muestra preview con criterios legibles; Abrir en explorador aplica la consulta.',
                'Abrir directo: salta el preview.',
                'Refinar última: combina la frase nueva con la cadena de turnos (máx. 5). Deshacer / Limpiar gestionan el stack.',
                'Favoritos: guardá la pregunta; Recientes sale de tu auditoría NL.',
                'Privacidad: no escribas CUIT, DNI, mails ni claves; el ERP enmascara varios patrones antes de enviar al LLM.',
            ],
        ],
        [
            'titulo' => '4. Explorador QBE',
            'parrafos' => [
                'Misma lógica de consulta avanzada que los índices workbench: grupos Y/O, NOT, fechas, importes y texto.',
            ],
            'items' => [
                'Consultar aplica los criterios; la paginación conserva los filtros.',
                'Diseñar vista / guardar vista: columnas y orden (por entidad).',
                'Export Pdf / Excel / Csv con los filtros activos (no solo la página visible).',
                'Cruces: atajos a entidades relacionadas (ej. remito → cliente, TM → mov. stock).',
            ],
        ],
        [
            'titulo' => '5. Tablero: pin, KPI y gráficos',
            'items' => [
                'Fijar: Atajo, KPI (conteo), Barras / Línea / Torta (si la entidad tiene fecha).',
                'Medida: Conteo o Suma (monto/total/importe según la entidad).',
                'Compartir con otros: visible en el tablero de colegas (solo lectura; empresa opcional).',
                'Clic en una barra/punto: abre el explorador filtrado por ese mes.',
                'KPI muestra variación % vs mes anterior cuando aplica.',
            ],
        ],
        [
            'titulo' => '6. Envío Excel programado',
            'parrafos' => [
                'Desde el explorador programás un Excel diario o semanal (lunes) al mail indicado. El cron direccion:enviar-consultas-inteligentes corre a las 07:10.',
            ],
            'items' => [
                'Se guarda en listado_envio_programado con recurso consulta.inteligente.{entidad}.',
                'Máximo 2000 filas por envío; si hay más, el mail indica recorte.',
                'En pantalla ves el último envío exitoso en el badge del programado.',
            ],
        ],
        [
            'titulo' => '7. Snapshots y cruces',
            'items' => [
                'Snapshot: congela hasta N filas de una consulta pesada para revisar o comparar después.',
                'Comparar snapshots: diferencias de IDs entre dos capturas.',
                'Cruce: varias entidades en paneles con el mismo período/texto.',
                'Join: une filas relacionadas según el catálogo de joins del módulo.',
            ],
        ],
        [
            'titulo' => '8. Configuración y permisos',
            'items' => [
                'listar-consulta-inteligente (o el permiso del módulo): catálogo, explorar, tablero, NL.',
                'configurar-consulta-inteligente: diccionario NL y umbrales de alertas.',
                'auditar-consulta-inteligente: ver auditoría de otros usuarios.',
                'LLM: variables CI_LLM_* en .env (cadena Groq → Gemini). Sin clave = solo diccionario local.',
            ],
        ],
        [
            'titulo' => '9. Qué no hace (límites a propósito)',
            'parrafos' => [
                'Consulta Inteligente compite con el estilo ThoughtSpot / Power BI Copilot / MyLogic en la consulta gobernada dentro del ERP. Tres cosas del “premium BI cloud” quedan fuera a propósito; no son deuda pendiente.',
                'Detalle técnico para el futuro: docs/arquitectura/consulta-inteligente-no-objetivos.md',
            ],
            'items' => [
                'Agente que escribe: no crea medidas ni reportes libres ni graba comprobantes; solo consulta con QBE verificable.',
                'Embed / MCP: no se embebe en otras apps ni se publica como herramienta MCP externa; vive en anitaERP con login y permisos listar-*.',
                'Warehouse: no es un lago (Snowflake/Fabric); lee el MySQL operativo del ERP con la misma lógica de los listados.',
            ],
        ],
    ],
];
