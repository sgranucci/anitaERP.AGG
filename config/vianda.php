<?php

/**
 * Viandas (comedor interno): costo desde lista 5000+mes; venta desde lista de la terminal.
 * Recálculo de costos catálogo: mismo job que gastronomía (gastronomia:actualizar-costo-mensual-catalogo).
 */
return [
    /**
     * Base listas de costo mensual (5000 + mes). Ej. julio → 5007.
     * Por defecto usa la misma base que gastronomía (GASTRONOMIA_INFORME_GERENTE_COSTO_LISTA_BASE).
     */
    'costo_lista_base' => (int) env(
        'VIANDA_COSTO_LISTA_BASE',
        env('GASTRONOMIA_INFORME_GERENTE_COSTO_LISTA_BASE', 5000)
    ),

    /** Un pedido activo por empleado de vianda y fecha de jornada (consumos anulados no cuentan). */
    'un_pedido_por_dia' => filter_var(env('VIANDA_UN_PEDIDO_POR_DIA', true), FILTER_VALIDATE_BOOLEAN),

    /**
     * Aviso por mail si se marcha una vianda sin centro de costo (no bloquea la operación).
     * Destinatarios: logins ERP o emails, por empresa (1=Biyemas, 2=Kandiko, 3=Rebisco).
     * Override JSON opcional: {"1":"ddominguez","2":"mmoskaluc","3":"wchavez,pmaruf"}
     */
    'aviso_sin_centrocosto' => [
        'habilitado' => filter_var(env('VIANDA_AVISO_SIN_CC_HABILITADO', true), FILTER_VALIDATE_BOOLEAN),
        'destinatarios_por_empresa' => (static function (): array {
            $json = trim((string) env('VIANDA_AVISO_SIN_CC_DESTINATARIOS', ''));
            if ($json !== '') {
                $decoded = json_decode($json, true);
                if (is_array($decoded)) {
                    $out = [];
                    foreach ($decoded as $empresaId => $valor) {
                        $id = (int) $empresaId;
                        if ($id <= 0) {
                            continue;
                        }
                        $out[$id] = is_array($valor)
                            ? array_values(array_filter(array_map('strval', $valor)))
                            : (string) $valor;
                    }

                    return $out;
                }
            }

            return [
                1 => ['ddominguez'],
                2 => ['mmoskaluc'],
                3 => ['wchavez', 'pmaruf'],
            ];
        })(),
    ],
];
