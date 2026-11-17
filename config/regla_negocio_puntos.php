<?php

/*
| Catálogo de puntos de decisión síncronos.
| El Service llama ReglaNegocioMotorService::decidir($clave, $ctx).
| Nuevos puntos = entrada aquí + cableado en el Service (F2+).
*/

return [
    'catalogo' => [
        'stock.tm.requiere_aprobacion' => [
            'etiqueta' => 'Stock · TM requiere aprobación',
            'dominio' => 'stock',
            'descripcion' => '¿La transferencia de mercadería exige aprobación antes de confirmar? Cableado en TransferenciaMercaderiaAprobacionSupport (fallback legacy si motor OFF / sin match).',
            'campos' => [
                'tipo' => ['etiqueta' => 'Abreviatura tipo (TRA, …)', 'tipo' => 'texto'],
                'tipo_id' => ['etiqueta' => 'Tipo transacción ID', 'tipo' => 'numero'],
                'entorno' => ['etiqueta' => 'Entorno (calzados_ferli, …)', 'tipo' => 'texto'],
                'empresa_codigo' => ['etiqueta' => 'Código EMPRESA', 'tipo' => 'texto'],
                'es_ferli' => ['etiqueta' => 'Es Ferli (bool)', 'tipo' => 'texto'],
                'modo' => ['etiqueta' => 'Modo aprobación config', 'tipo' => 'texto'],
                'aviso_opcional' => ['etiqueta' => 'Aviso opcional del tipo', 'tipo' => 'texto'],
                'requiere_aprobacion_tipo' => ['etiqueta' => 'Flag requiere_aprobacion del tipo', 'tipo' => 'texto'],
                'decision_aviso_usuario' => ['etiqueta' => 'Decisión modal aviso (bool|null)', 'tipo' => 'texto'],
            ],
        ],
        'compras.com.reserva_factura' => [
            'etiqueta' => 'Compras · Reserva COM en factura',
            'dominio' => 'compras',
            'descripcion' => 'Tope COM por factura operativa (max_com_por_factura). Cableado en ComprobanteProveedorReservaComLegajoSupport.',
            'campos' => [
                'com_count' => ['etiqueta' => 'Cantidad COM (contexto opcional)', 'tipo' => 'numero'],
                'punto_param' => ['etiqueta' => 'Parámetro solicitado', 'tipo' => 'texto'],
                'proveedor_id' => ['etiqueta' => 'Proveedor ID', 'tipo' => 'numero'],
                'empresa_id' => ['etiqueta' => 'Empresa ID', 'tipo' => 'numero'],
            ],
        ],
        'contable.canon.alicuota' => [
            'etiqueta' => 'Contable · Alícuota canon por CUIT',
            'dominio' => 'contable',
            'descripcion' => 'Resolver régimen/alícuotas canon entidades. Cableado en CanonEntidadesReglasSupport::resolver().',
            'campos' => [
                'cuit' => ['etiqueta' => 'CUIT normalizado', 'tipo' => 'texto'],
                'cuit_formateado' => ['etiqueta' => 'CUIT formateado', 'tipo' => 'texto'],
                'nombre' => ['etiqueta' => 'Razón social', 'tipo' => 'texto'],
                'regla_legacy' => ['etiqueta' => 'Regla legacy', 'tipo' => 'texto'],
            ],
        ],
        'caja.programa_pago.cheques_param' => [
            'etiqueta' => 'Caja · Params cheques programa de pagos',
            'dominio' => 'caja',
            'descripcion' => 'Override anticipo_maximo / faltante_tolerancia_pct. Cableado en ProgramaPagoAsignarChequesConfigSupport.',
            'campos' => [
                'anticipo_maximo_legacy' => ['etiqueta' => 'Anticipo máx. (parametro_sistema)', 'tipo' => 'numero'],
                'faltante_tolerancia_pct_legacy' => ['etiqueta' => 'Tolerancia faltante % legacy', 'tipo' => 'numero'],
                'punto_param' => ['etiqueta' => 'Parámetro solicitado', 'tipo' => 'texto'],
            ],
        ],
        'configuracion.arbol.umbral_doble_aprobacion' => [
            'etiqueta' => 'Configuración · Umbral doble aprobación (árbol)',
            'dominio' => 'configuracion',
            'descripcion' => 'Umbral “nivel alto” para matching por piso. Cableado en ArbolAprobacionUmbralSupport (default 5.000.000).',
            'campos' => [
                'umbral_legacy' => ['etiqueta' => 'Umbral legacy', 'tipo' => 'numero'],
                'punto_param' => ['etiqueta' => 'Parámetro solicitado', 'tipo' => 'texto'],
            ],
        ],
        'ventas.afip.circuito' => [
            'etiqueta' => 'Ventas · Circuito AFIP export/local',
            'dominio' => 'ventas',
            'descripcion' => 'Override circuito. Cableado en FacturacionCircuitoAfipSupport::resolverCircuito. set_dato circuito=exportacion|local. Fiscal: activar con cuidado.',
            'campos' => [
                'letra_cliente' => ['etiqueta' => 'Letra cliente', 'tipo' => 'texto'],
                'codigo_documento' => ['etiqueta' => 'Código documento', 'tipo' => 'texto'],
                'tipo_abrev' => ['etiqueta' => 'Abreviatura tipo', 'tipo' => 'texto'],
                'tipo_codigo' => ['etiqueta' => 'Código tipo', 'tipo' => 'texto'],
                'pv_modo' => ['etiqueta' => 'PV modofacturacion', 'tipo' => 'texto'],
                'pv_webservice' => ['etiqueta' => 'PV webservice', 'tipo' => 'texto'],
                'circuito_legacy' => ['etiqueta' => 'Circuito legacy', 'tipo' => 'texto'],
            ],
        ],
        'demo.punto.piloto' => [
            'etiqueta' => 'Demo · Punto piloto (sin cableado de dominio)',
            'dominio' => 'configuracion',
            'descripcion' => 'Para simular el motor en F1 sin tocar Services de negocio.',
            'campos' => [
                'monto' => ['etiqueta' => 'Monto', 'tipo' => 'numero'],
                'tipo' => ['etiqueta' => 'Tipo', 'tipo' => 'texto'],
            ],
        ],
    ],
];
