<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente;

/**
 * Arma grupos QBE a partir de la pregunta NL (período, estado, campo, importe).
 * No ejecuta SQL: solo estructura consumible por el explorador.
 */
final class ConsultaInteligenteNlQbeSupport
{
    /**
     * @return list<array{campo: string, op: string, valor: string, valor_hasta: string, label?: string}>
     */
    public static function criteriosDesdeTexto(string $entidad, string $tNormalizado, ?string $periodo = null): array
    {
        $criterios = [];

        if ($periodo !== null) {
            $campoFecha = self::campoFecha($entidad);
            if ($campoFecha !== null) {
                $criterios[] = self::criterio($campoFecha, $periodo, '', '', 'Período');
            }
        }

        $estado = self::detectarEstado($entidad, $tNormalizado);
        if ($estado !== null) {
            $criterios[] = self::criterio($estado['campo'], $estado['op'], $estado['valor'], '', $estado['label']);
        }

        $importe = self::detectarImporte($entidad, $tNormalizado);
        if ($importe !== null) {
            $criterios[] = self::criterio($importe['campo'], $importe['op'], $importe['valor'], $importe['valor_hasta'], $importe['label']);
        }

        $campoTexto = self::detectarCampoTexto($entidad, $tNormalizado);
        if ($campoTexto !== null) {
            $criterios[] = self::criterio(
                $campoTexto['campo'],
                $campoTexto['op'],
                $campoTexto['valor'],
                '',
                $campoTexto['label']
            );
        }

        return $criterios;
    }

    /**
     * @param  list<array{campo: string, op: string, valor: string, valor_hasta: string, label?: string}>  $criterios
     * @return array{entre_grupos: string, grupos: list<array{logic: string, not: int, criterios: list<array<string, string>>}>}
     */
    public static function aEstructuraQbe(array $criterios): array
    {
        $limpios = [];
        foreach ($criterios as $c) {
            $limpios[] = [
                'campo' => (string) ($c['campo'] ?? ''),
                'op' => (string) ($c['op'] ?? 'contiene'),
                'valor' => (string) ($c['valor'] ?? ''),
                'valor_hasta' => (string) ($c['valor_hasta'] ?? ''),
            ];
        }

        return [
            'entre_grupos' => 'and',
            'grupos' => [[
                'logic' => 'and',
                'not' => 0,
                'criterios' => $limpios,
            ]],
        ];
    }

    /**
     * @param  list<array{campo: string, op: string, valor: string, valor_hasta?: string, label?: string}>  $criterios
     * @return list<string>
     */
    public static function legible(array $criterios, string $entidad): array
    {
        $etiquetas = [];
        if (EntidadSemanticaCatalogo::existe($entidad)) {
            $campos = EntidadSemanticaCatalogo::porClave($entidad)->camposQbe();
            foreach ($campos as $key => $meta) {
                $etiquetas[$key] = (string) ($meta['label'] ?? $key);
            }
        }

        $ops = [
            'hoy' => 'hoy',
            'ayer' => 'ayer',
            'esta_semana' => 'esta semana',
            'este_mes' => 'este mes',
            'mes_anterior' => 'mes anterior',
            'este_anio' => 'este año',
            'contiene' => 'contiene',
            'igual' => 'igual a',
            'empieza' => 'empieza con',
            'mayor' => 'mayor que',
            'menor' => 'menor que',
            'mayor_igual' => '≥',
            'menor_igual' => '≤',
            'entre' => 'entre',
            'desde' => 'desde',
            'hasta' => 'hasta',
        ];

        $out = [];
        foreach ($criterios as $c) {
            $campo = (string) ($c['campo'] ?? '');
            $op = (string) ($c['op'] ?? '');
            $valor = (string) ($c['valor'] ?? '');
            $hasta = (string) ($c['valor_hasta'] ?? '');
            $label = (string) ($c['label'] ?? ($etiquetas[$campo] ?? $campo));
            $opTxt = $ops[$op] ?? $op;
            if (in_array($op, ['hoy', 'ayer', 'esta_semana', 'este_mes', 'mes_anterior', 'este_anio'], true)) {
                $out[] = $label.': '.$opTxt;
            } elseif ($op === 'entre' && ($valor !== '' || $hasta !== '')) {
                $out[] = $label.': entre '.$valor.($hasta !== '' ? ' y '.$hasta : '');
            } elseif ($valor !== '') {
                $out[] = $label.' '.$opTxt.' «'.$valor.'»';
            } else {
                $out[] = $label.': '.$opTxt;
            }
        }

        return $out;
    }

    public static function campoFecha(string $entidad): ?string
    {
        return match ($entidad) {
            'cheque' => 'fechapago',
            'comprobante_proveedor' => 'fechacomprobante',
            'pagoproveedor', 'ingresoegreso', 'ordencompra', 'requisicion', 'solicitudpago',
            'ticket', 'recepcion_proveedor', 'asiento', 'remito', 'factura', 'pedido', 'movimientostock', 'transferencia_mercaderia', 'capex' => 'fecha',
            'importacion' => 'fecha_apertura',
            'empleado_sueldos' => 'fecha_ingreso',
            default => null,
        };
    }

    /**
     * @return array{campo: string, op: string, valor: string, label: string}|null
     */
    private static function detectarEstado(string $entidad, string $t): ?array
    {
        $mapa = match ($entidad) {
            'pedido' => [
                'campo' => 'estadopedido',
                'pendiente' => ['pendiente', 'pendientes', 'abierto', 'abiertos', 'sin facturar'],
                'facturado' => ['facturado', 'facturados'],
                'anulado' => ['anulado', 'anulados', 'cancelado', 'cancelados'],
            ],
            'remito' => [
                'campo' => 'estadoremito',
                'pendiente' => ['pendiente', 'pendientes', 'abierto', 'abiertos'],
                'entregado' => ['entregado', 'entregados', 'cerrado', 'cerrados'],
                'anulado' => ['anulado', 'anulados'],
            ],
            'cheque' => null,
            'factura' => null,
            'ticket' => [
                'campo' => 'estado',
                'pendiente' => ['pendiente', 'pendientes', 'abierto', 'abiertos'],
                'cerrado' => ['cerrado', 'cerrados', 'resuelto', 'resueltos'],
            ],
            default => null,
        };

        if ($mapa === null) {
            return null;
        }

        $campo = $mapa['campo'];
        unset($mapa['campo']);
        foreach ($mapa as $valor => $needles) {
            foreach ($needles as $n) {
                if (str_contains($t, $n)) {
                    return [
                        'campo' => $campo,
                        'op' => 'contiene',
                        'valor' => (string) $valor,
                        'label' => 'Estado',
                    ];
                }
            }
        }

        return null;
    }

    /**
     * @return array{campo: string, op: string, valor: string, valor_hasta: string, label: string}|null
     */
    private static function detectarImporte(string $entidad, string $t): ?array
    {
        $campo = match ($entidad) {
            'cheque', 'pagoproveedor', 'ingresoegreso', 'cobranza' => 'monto',
            'factura', 'comprobante_proveedor' => 'total',
            default => null,
        };
        if ($campo === null) {
            return null;
        }

        // mayor/más de / > 100000  |  menor/menos de / < 5000  |  entre 1000 y 5000
        if (preg_match('/\bentre\s+(\d[\d\.]*)\s+y\s+(\d[\d\.]*)\b/u', $t, $m)) {
            return [
                'campo' => $campo,
                'op' => 'entre',
                'valor' => self::numero($m[1]),
                'valor_hasta' => self::numero($m[2]),
                'label' => 'Importe',
            ];
        }
        if (preg_match('/\b(?:mayor(?:es)?\s+a|mas\s+de|más\s+de|arriba\s+de|>)\s*\$?\s*(\d[\d\.]*)\b/u', $t, $m)) {
            return [
                'campo' => $campo,
                'op' => 'mayor',
                'valor' => self::numero($m[1]),
                'valor_hasta' => '',
                'label' => 'Importe',
            ];
        }
        if (preg_match('/\b(?:menor(?:es)?\s+a|menos\s+de|abajo\s+de|<)\s*\$?\s*(\d[\d\.]*)\b/u', $t, $m)) {
            return [
                'campo' => $campo,
                'op' => 'menor',
                'valor' => self::numero($m[1]),
                'valor_hasta' => '',
                'label' => 'Importe',
            ];
        }

        return null;
    }

    /**
     * "cliente Acme", "proveedor Biyemas", "código 1234"
     *
     * @return array{campo: string, op: string, valor: string, label: string}|null
     */
    private static function detectarCampoTexto(string $entidad, string $t): ?array
    {
        $patrones = [
            'cliente' => ['cliente', 'deudor'],
            'proveedor' => ['proveedor'],
            'codigo' => ['codigo', 'código', 'nro', 'numero', 'número'],
            'numerocomprobante' => ['comprobante', 'factura nro', 'fc '],
        ];

        $campoPreferido = match ($entidad) {
            'cliente', 'cobranza', 'factura', 'remito', 'pedido', 'cheque' => 'cliente',
            'proveedor', 'comprobante_proveedor', 'pagoproveedor', 'ordencompra',
            'requisicion', 'importacion', 'solicitudpago', 'recepcion_proveedor' => 'proveedor',
            default => null,
        };

        foreach ($patrones as $campo => $prefijos) {
            if ($campoPreferido !== null && $campo !== $campoPreferido && $campo !== 'codigo' && $campo !== 'numerocomprobante') {
                continue;
            }
            if (! self::entidadTieneCampo($entidad, $campo)) {
                continue;
            }
            foreach ($prefijos as $pref) {
                if (preg_match('/\b'.preg_quote($pref, '/').'\s+([a-z0-9][\w\-\. ]{1,40})/u', $t, $m)) {
                    $valor = trim($m[1]);
                    $valor = preg_replace('/\b(este|mes|hoy|ayer|semana|anio|año|anterior|pasado|pendiente|facturado)\b/u', '', $valor) ?? $valor;
                    $valor = trim(preg_replace('/\s+/', ' ', $valor) ?? $valor);
                    if (mb_strlen($valor) < 2) {
                        continue;
                    }

                    return [
                        'campo' => $campo === 'cliente' && ! self::entidadTieneCampo($entidad, 'cliente')
                            ? 'nombrecliente'
                            : $campo,
                        'op' => 'contiene',
                        'valor' => $valor,
                        'label' => ucfirst($campo),
                    ];
                }
            }
        }

        return null;
    }

    private static function entidadTieneCampo(string $entidad, string $campo): bool
    {
        if (! EntidadSemanticaCatalogo::existe($entidad)) {
            return false;
        }
        $campos = EntidadSemanticaCatalogo::porClave($entidad)->camposQbe();

        return isset($campos[$campo]);
    }

    private static function numero(string $raw): string
    {
        return str_replace('.', '', $raw);
    }

    /**
     * @return array{campo: string, op: string, valor: string, valor_hasta: string, label: string}
     */
    private static function criterio(string $campo, string $op, string $valor, string $hasta, string $label): array
    {
        return [
            'campo' => $campo,
            'op' => $op,
            'valor' => $valor,
            'valor_hasta' => $hasta,
            'label' => $label,
        ];
    }
}
