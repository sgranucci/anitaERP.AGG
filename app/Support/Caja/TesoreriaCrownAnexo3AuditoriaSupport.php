<?php

namespace App\Support\Caja;

use App\Models\Caja\TesoreriaCrownAsignacion;
use App\Models\Caja\TesoreriaCrownComprobante;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Models\Caja\TesoreriaCrownMesa;

/**
 * Detalle de auditoría de una celda del Anexo III (l-anexo3new.c).
 * Lista los comprobantes/mesas que componen el importe, con renglones.
 */
final class TesoreriaCrownAnexo3AuditoriaSupport
{
    /** @var array<string, string> */
    public const COLUMNAS = [
        'cierre_x' => 'Cierre X',
        'egresos' => 'Egresos',
        'efectivo' => 'Efectivo',
        'tarjeta_credito' => 'T. crédito',
        'tarjeta_debito' => 'T. débito',
        'total_qr' => 'QR',
        'recargo_fin' => 'Recargo',
        'ctacte' => 'Cta.cte.',
        'ingresos' => 'Ingresos',
        'hab_fichas' => 'Hab. fichas',
        'rend_fichas' => 'Rend. fichas',
        'fichas_casino' => 'Fichas Casino',
        'pago_cashless' => 'Pago cashless',
        'vta_cashless' => 'Vta. cashless',
        'pago_voucher' => 'Pago voucher',
        'venta_voucher' => 'Venta voucher',
        'pago_online' => 'Pagos online',
        'venta_online' => 'Ventas online',
        'online' => 'Online',
        'apuestas' => 'Apuestas Dep.',
        'resultado' => 'Resultado',
        'diferencia' => 'Dif. cajero',
        'pie_tarjetas' => 'Total tarjetas + QR',
    ];

    /**
     * @return array{
     *     titulo: string,
     *     columna: string,
     *     etiqueta_columna: string,
     *     total: float,
     *     formula: ?string,
     *     como_auditar: ?string,
     *     avisos: list<string>,
     *     movimientos: list<array<string, mixed>>,
     *     cierre_url: ?string
     * }
     */
    public static function deRequest(TesoreriaCrownJornada $jornada, string $columna, string $scope, ?int $asignacionId, ?string $tipoGrupo): array
    {
        if (! isset(self::COLUMNAS[$columna])) {
            abort(422, 'Columna de anexo III no válida.');
        }

        $armado = TesoreriaCrownResumenCajeroCalculo::deJornada($jornada);
        $etiqueta = self::COLUMNAS[$columna];

        if ($scope === 'pie_tarjetas' || $columna === 'pie_tarjetas') {
            return self::pieTarjetas($armado, $jornada);
        }

        if ($scope === 'propina') {
            return self::propina($armado, $jornada, $columna, $etiqueta);
        }

        if ($scope === 'fila') {
            if ($asignacionId === null || $asignacionId < 1) {
                abort(422, 'Falta la asignación.');
            }

            return self::fila($armado, $jornada, $asignacionId, $columna, $etiqueta);
        }

        if ($scope === 'grupo') {
            return self::agregado($armado, $jornada, $columna, $etiqueta, $tipoGrupo);
        }

        if ($scope === 'total') {
            return self::agregado($armado, $jornada, $columna, $etiqueta, null);
        }

        abort(422, 'Alcance de auditoría no válido.');
    }

    /**
     * @param  array{filas: list<array<string, mixed>>, grupos: list<array<string, mixed>>, totales: array<string, float>, total_tarjetas: float}  $armado
     * @return array<string, mixed>
     */
    private static function fila(array $armado, TesoreriaCrownJornada $jornada, int $asignacionId, string $columna, string $etiqueta): array
    {
        $fila = null;
        foreach ($armado['filas'] as $candidato) {
            if ((int) ($candidato['asignacion_id'] ?? 0) === $asignacionId && empty($candidato['propina'])) {
                $fila = $candidato;
                break;
            }
        }
        if ($fila === null) {
            abort(404, 'No hay fila del anexo III para esa asignación.');
        }

        $asignacion = self::cargarAsignacion($jornada, $asignacionId);
        $detalle = self::detalleDeAsignacion($asignacion, $columna, $fila);

        return [
            'titulo' => trim($fila['caja'].' · '.$fila['cajero']),
            'columna' => $columna,
            'etiqueta_columna' => $etiqueta,
            'total' => round((float) ($fila[$columna] ?? 0), 2),
            'formula' => $detalle['formula'],
            'como_auditar' => self::comoAuditar($columna, (string) ($fila['tarea'] ?? '')),
            'avisos' => $detalle['avisos'],
            'movimientos' => $detalle['movimientos'],
            'cierre_url' => route('ver_tesoreria_crown_cierre', [
                'jornada' => $asignacion->jornada_id,
                'cajero' => $asignacion->cajero_crown_id,
            ]),
        ];
    }

    /**
     * @param  array{filas: list<array<string, mixed>>, grupos: list<array<string, mixed>>, totales: array<string, float>}  $armado
     * @return array<string, mixed>
     */
    private static function agregado(array $armado, TesoreriaCrownJornada $jornada, string $columna, string $etiqueta, ?string $tipoGrupo): array
    {
        $titulo = 'Total general';
        $total = (float) ($armado['totales'][$columna] ?? 0);
        $filas = $armado['filas'];
        if ($tipoGrupo !== null && $tipoGrupo !== '') {
            $grupo = null;
            foreach ($armado['grupos'] as $candidato) {
                if ((string) $candidato['tipo'] === $tipoGrupo) {
                    $grupo = $candidato;
                    break;
                }
            }
            if ($grupo === null) {
                abort(404, 'Grupo inexistente en el anexo III.');
            }
            $titulo = 'Total '.$grupo['titulo'];
            $total = (float) ($grupo['totales'][$columna] ?? 0);
            $filas = $grupo['filas'];
        }

        $movimientos = [];
        $avisos = [];
        $formula = self::formulaColumna($columna);
        foreach ($filas as $fila) {
            if (! empty($fila['propina'])) {
                $parte = self::movimientosPropina($jornada, $columna, $fila);
                foreach ($parte['movimientos'] as $mov) {
                    $movimientos[] = $mov;
                }
                foreach ($parte['avisos'] as $aviso) {
                    $avisos[] = $aviso;
                }

                continue;
            }
            $asignacionId = (int) ($fila['asignacion_id'] ?? 0);
            if ($asignacionId < 1) {
                continue;
            }
            $asignacion = self::cargarAsignacion($jornada, $asignacionId);
            $parte = self::detalleDeAsignacion($asignacion, $columna, $fila);
            foreach ($parte['movimientos'] as $mov) {
                $mov['caja'] = (string) $fila['caja'];
                $movimientos[] = $mov;
            }
        }

        return [
            'titulo' => $titulo,
            'columna' => $columna,
            'etiqueta_columna' => $etiqueta,
            'total' => round($total, 2),
            'formula' => $formula,
            'como_auditar' => self::comoAuditar($columna),
            'avisos' => array_values(array_unique($avisos)),
            'movimientos' => $movimientos,
            'cierre_url' => null,
        ];
    }

    /**
     * @param  array{filas: list<array<string, mixed>>, grupos: list<array<string, mixed>>, totales: array<string, float>, total_tarjetas?: float}  $armado
     * @return array<string, mixed>
     */
    private static function pieTarjetas(array $armado, TesoreriaCrownJornada $jornada): array
    {
        $movimientos = [];
        foreach (['tarjeta_credito', 'tarjeta_debito', 'total_qr'] as $columna) {
            $parte = self::agregado($armado, $jornada, $columna, self::COLUMNAS[$columna], null);
            foreach ($parte['movimientos'] as $mov) {
                $mov['grupo_columna'] = self::COLUMNAS[$columna];
                $movimientos[] = $mov;
            }
        }

        return [
            'titulo' => 'Total general',
            'columna' => 'pie_tarjetas',
            'etiqueta_columna' => self::COLUMNAS['pie_tarjetas'],
            'total' => round((float) ($armado['total_tarjetas'] ?? 0), 2),
            'formula' => 'Tarjeta crédito + Tarjeta débito + QR (crédito bruto; el recargo ya está dentro del crédito).',
            'como_auditar' => self::comoAuditar('pie_tarjetas'),
            'avisos' => [],
            'movimientos' => $movimientos,
            'cierre_url' => null,
        ];
    }

    /**
     * @param  array{filas: list<array<string, mixed>>, grupos: list<array<string, mixed>>}  $armado
     * @return array<string, mixed>
     */
    private static function propina(array $armado, TesoreriaCrownJornada $jornada, string $columna, string $etiqueta): array
    {
        $fila = null;
        foreach ($armado['filas'] as $candidato) {
            if (! empty($candidato['propina'])) {
                $fila = $candidato;
                break;
            }
        }
        if ($fila === null) {
            abort(404, 'No hay fila de propina en el anexo III.');
        }
        $parte = self::movimientosPropina($jornada, $columna, $fila);

        return [
            'titulo' => trim($fila['caja'].' · '.$fila['cajero'].' (propina)'),
            'columna' => $columna,
            'etiqueta_columna' => $etiqueta,
            'total' => round((float) ($fila[$columna] ?? 0), 2),
            'formula' => $parte['formula'],
            'como_auditar' => 'Abrí cada estado final de mesa confirmado y verificá el campo propina.',
            'avisos' => $parte['avisos'],
            'movimientos' => $parte['movimientos'],
            'cierre_url' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array{movimientos: list<array<string, mixed>>, avisos: list<string>, formula: ?string}
     */
    private static function movimientosPropina(TesoreriaCrownJornada $jornada, string $columna, array $fila): array
    {
        if (in_array($columna, ['egresos', 'resultado'], true)) {
            $movimientos = [];
            $cierres = TesoreriaCrownMesa::query()
                ->with('deposito')
                ->where('jornada_id', $jornada->id)
                ->where('tipo', 'F')
                ->where('estado', TesoreriaCrownMesa::CONFIRMADO)
                ->whereNull('revierte_id')
                ->whereDoesntHave('reversa')
                ->where('propina', '!=', 0)
                ->orderBy('id')
                ->get();
            foreach ($cierres as $cierre) {
                $propina = round((float) $cierre->propina, 2);
                if (abs($propina) < 0.001) {
                    continue;
                }
                $mesaEtiqueta = trim((string) ($cierre->deposito->nombre ?? $cierre->deposito->codigo ?? 'Mesa'));
                $movimientos[] = self::movimientoMesa($cierre, $mesaEtiqueta, $propina, [
                    ['etiqueta' => 'Propina estado final', 'importe' => $propina],
                ]);
            }

            return [
                'movimientos' => $movimientos,
                'avisos' => [],
                'formula' => 'Suma de fichmm_propina de los estados finales confirmados de la jornada.',
            ];
        }

        if ($columna === 'efectivo') {
            $avisos = [
                'Anita reimprime acá el efectivo residual de la última caja de empleados (RESC_efectivo) al listar la propina; no es un segundo movimiento de efectivo.',
            ];
            $movimientos = [];
            $asignacionId = (int) ($fila['asignacion_id'] ?? 0);
            if ($asignacionId > 0) {
                $asignacion = self::cargarAsignacion($jornada, $asignacionId);
                $filaBase = $fila;
                $filaBase['propina'] = false;
                $parte = self::detalleDeAsignacion($asignacion, 'efectivo', $filaBase);
                $movimientos = $parte['movimientos'];
            }

            return [
                'movimientos' => $movimientos,
                'avisos' => $avisos,
                'formula' => 'Residual de efectivo de la última caja de empleados (misma cifra que la fila anterior).',
            ];
        }

        return [
            'movimientos' => [],
            'avisos' => ['La fila de propina no aporta a esta columna (salvo egresos/resultado/efectivo residual).'],
            'formula' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $fila
     * @return array{movimientos: list<array<string, mixed>>, avisos: list<string>, formula: ?string}
     */
    private static function detalleDeAsignacion(TesoreriaCrownAsignacion $asignacion, string $columna, array $fila): array
    {
        $conforme = $asignacion->comprobantes->filter(
            fn (TesoreriaCrownComprobante $doc) => $doc->estado === TesoreriaCrownComprobanteSupport::CONFORME
        );
        $movimientos = [];
        $avisos = [];
        $formula = self::formulaColumna($columna, (string) ($fila['tarea'] ?? ''));

        if ($columna === 'egresos') {
            foreach ($conforme as $doc) {
                if ($doc->tipo !== TesoreriaCrownComprobanteSupport::HABILITACION) {
                    continue;
                }
                $partes = self::partes($doc);
                $aporte = $partes['efectivo'];
                if (abs($aporte) < 0.001) {
                    continue;
                }
                $movimientos[] = self::movimientoComprobante($doc, $aporte, $partes['renglones_efectivo']);
            }

            return compact('movimientos', 'avisos', 'formula');
        }

        if ($columna === 'hab_fichas') {
            foreach ($conforme as $doc) {
                if ($doc->tipo !== TesoreriaCrownComprobanteSupport::HABILITACION) {
                    continue;
                }
                $partes = self::partes($doc);
                if (abs($partes['fichas']) < 0.001) {
                    continue;
                }
                $movimientos[] = self::movimientoComprobante($doc, $partes['fichas'], $partes['renglones_fichas']);
            }

            return compact('movimientos', 'avisos', 'formula');
        }

        if (in_array($columna, ['rend_fichas', 'fichas_casino'], true)) {
            foreach ($conforme as $doc) {
                if ($doc->tipo !== TesoreriaCrownComprobanteSupport::RENDICION) {
                    continue;
                }
                $partes = self::partes($doc);
                if (abs($partes['fichas']) < 0.001) {
                    continue;
                }
                $movimientos[] = self::movimientoComprobante($doc, $partes['fichas'], $partes['renglones_fichas']);
            }

            return compact('movimientos', 'avisos', 'formula');
        }

        if ($columna === 'efectivo') {
            foreach ($conforme as $doc) {
                if ($doc->tipo !== TesoreriaCrownComprobanteSupport::RENDICION) {
                    continue;
                }
                $partes = self::partes($doc);
                if (abs($partes['efectivo']) < 0.001) {
                    continue;
                }
                $movimientos[] = self::movimientoComprobante($doc, $partes['efectivo'], $partes['renglones_efectivo']);
            }

            return compact('movimientos', 'avisos', 'formula');
        }

        $camposDirectos = [
            'cierre_x' => 'total_x',
            'tarjeta_credito' => 'tarjeta_credito',
            'tarjeta_debito' => 'tarjeta_debito',
            'total_qr' => 'total_qr',
            'recargo_fin' => 'recargo_fin',
            'pago_cashless' => 'pago_cash',
            'vta_cashless' => 'venta_cash',
            'pago_voucher' => 'pago_voucher',
            'venta_voucher' => 'venta_voucher',
            'pago_online' => 'pago_online',
            'venta_online' => 'venta_online',
        ];
        if (isset($camposDirectos[$columna])) {
            $attr = $camposDirectos[$columna];
            foreach ($conforme as $doc) {
                if ($doc->tipo !== TesoreriaCrownComprobanteSupport::RENDICION) {
                    continue;
                }
                $aporte = round((float) $doc->{$attr}, 2);
                if (abs($aporte) < 0.001) {
                    continue;
                }
                $movimientos[] = self::movimientoComprobante($doc, $aporte, [
                    ['etiqueta' => self::COLUMNAS[$columna], 'importe' => $aporte],
                ]);
            }

            return compact('movimientos', 'avisos', 'formula');
        }

        if ($columna === 'ctacte') {
            foreach ($conforme as $doc) {
                if ($doc->tipo !== TesoreriaCrownComprobanteSupport::RENDICION) {
                    continue;
                }
                $ctacte = round((float) $doc->ctacte_hotel, 2);
                $transf = round((float) $doc->transferencia_hotel, 2);
                $aporte = round($ctacte + $transf, 2);
                if (abs($aporte) < 0.001) {
                    continue;
                }
                $renglones = [];
                if (abs($ctacte) >= 0.001) {
                    $renglones[] = ['etiqueta' => 'Cta.cte. hotel', 'importe' => $ctacte];
                }
                if (abs($transf) >= 0.001) {
                    $renglones[] = ['etiqueta' => 'Transferencia hotel', 'importe' => $transf];
                }
                $movimientos[] = self::movimientoComprobante($doc, $aporte, $renglones);
            }

            return compact('movimientos', 'avisos', 'formula');
        }

        if ($columna === 'online') {
            foreach ($conforme as $doc) {
                if ($doc->tipo !== TesoreriaCrownComprobanteSupport::RENDICION) {
                    continue;
                }
                $pago = round((float) $doc->pago_online, 2);
                $venta = round((float) $doc->venta_online, 2);
                $aporte = round($venta - $pago, 2);
                if (abs($aporte) < 0.001) {
                    continue;
                }
                $movimientos[] = self::movimientoComprobante($doc, $aporte, [
                    ['etiqueta' => 'Pago online', 'importe' => $pago],
                    ['etiqueta' => 'Venta online', 'importe' => $venta],
                ]);
            }

            return compact('movimientos', 'avisos', 'formula');
        }

        if ($columna === 'apuestas') {
            foreach ($conforme as $doc) {
                if ($doc->tipo !== TesoreriaCrownComprobanteSupport::RENDICION) {
                    continue;
                }
                $pago = round((float) $doc->pago_apuesta, 2);
                $vta = round((float) $doc->vta_apuesta, 2);
                $aporte = round($pago - $vta, 2);
                if (abs($aporte) < 0.001 && abs($pago) < 0.001 && abs($vta) < 0.001) {
                    continue;
                }
                if (abs($pago) < 0.001 && abs($vta) < 0.001) {
                    continue;
                }
                $movimientos[] = self::movimientoComprobante($doc, $aporte, [
                    ['etiqueta' => 'Pago apuestas', 'importe' => $pago],
                    ['etiqueta' => 'Venta apuestas', 'importe' => $vta],
                    ['etiqueta' => 'Neto (pago − venta)', 'importe' => $aporte],
                ]);
            }

            return compact('movimientos', 'avisos', 'formula');
        }

        if ($columna === 'ingresos') {
            foreach (['efectivo', 'tarjeta_credito', 'tarjeta_debito', 'total_qr', 'recargo_fin', 'ctacte'] as $parteCol) {
                if ($parteCol === 'ctacte' && ! in_array((string) ($fila['tarea'] ?? ''), ['G', 'H'], true)) {
                    continue;
                }
                $parte = self::detalleDeAsignacion($asignacion, $parteCol, $fila);
                foreach ($parte['movimientos'] as $mov) {
                    $signo = $parteCol === 'recargo_fin' ? -1 : 1;
                    $mov['aporte'] = round($signo * (float) $mov['aporte'], 2);
                    $mov['grupo_columna'] = self::COLUMNAS[$parteCol].($parteCol === 'recargo_fin' ? ' (−)' : '');
                    $movimientos[] = $mov;
                }
            }
            if (in_array((string) ($fila['tarea'] ?? ''), ['G', 'H'], true)) {
                foreach ($conforme as $doc) {
                    if ($doc->tipo !== TesoreriaCrownComprobanteSupport::RENDICION) {
                        continue;
                    }
                    $fallo = round((float) $doc->fallo_mozo, 2);
                    if (abs($fallo) < 0.001) {
                        continue;
                    }
                    $mov = self::movimientoComprobante($doc, $fallo, [
                        ['etiqueta' => 'Fallo mozo', 'importe' => $fallo],
                    ]);
                    $mov['grupo_columna'] = 'Fallo mozo';
                    $movimientos[] = $mov;
                }
            }

            return compact('movimientos', 'avisos', 'formula');
        }

        if ($columna === 'resultado') {
            $partes = [
                'ingresos' => 1,
                'egresos' => -1,
                'pago_cashless' => 1,
                'vta_cashless' => -1,
                'fichas_casino' => 1,
                'online' => 1,
                'apuestas' => 1,
            ];
            // voucher neto
            foreach ($conforme as $doc) {
                if ($doc->tipo !== TesoreriaCrownComprobanteSupport::RENDICION) {
                    continue;
                }
                $pago = round((float) $doc->pago_voucher, 2);
                $venta = round((float) $doc->venta_voucher, 2);
                $neto = round($pago - $venta, 2);
                if (abs($neto) < 0.001) {
                    continue;
                }
                $mov = self::movimientoComprobante($doc, $neto, [
                    ['etiqueta' => 'Pago voucher', 'importe' => $pago],
                    ['etiqueta' => 'Venta voucher', 'importe' => $venta],
                ]);
                $mov['grupo_columna'] = 'Voucher neto';
                $movimientos[] = $mov;
            }
            foreach ($partes as $parteCol => $signo) {
                $parte = self::detalleDeAsignacion($asignacion, $parteCol, $fila);
                foreach ($parte['movimientos'] as $mov) {
                    $mov['aporte'] = round($signo * (float) $mov['aporte'], 2);
                    $mov['grupo_columna'] = self::COLUMNAS[$parteCol].($signo < 0 ? ' (−)' : '');
                    $movimientos[] = $mov;
                }
            }

            return compact('movimientos', 'avisos', 'formula');
        }

        if ($columna === 'diferencia') {
            $tarea = (string) ($fila['tarea'] ?? '');
            if ($tarea === 'E') {
                return [
                    'movimientos' => [],
                    'avisos' => ['En caja de empleados la diferencia del listado es siempre cero.'],
                    'formula' => $formula,
                ];
            }
            if (in_array($tarea, ['G', 'H'], true)) {
                $calc = TesoreriaCrownCierreCalculo::de($asignacion);
                $avisos[] = sprintf(
                    'Diferencia del cierre: rendido %s − habilitado %s − a rendir/resultado %s = %s.',
                    number_format((float) $calc['rendido'], 2, ',', '.'),
                    number_format((float) $calc['habilitado'], 2, ',', '.'),
                    number_format((float) $calc['resultado'], 2, ',', '.'),
                    number_format((float) $calc['diferencia'], 2, ',', '.')
                );
                foreach ($conforme as $doc) {
                    $movimientos[] = self::movimientoComprobante($doc, 0.0, []);
                }

                return [
                    'movimientos' => $movimientos,
                    'avisos' => $avisos,
                    'formula' => $formula,
                ];
            }
            // Otras tareas: diferencia = resultado
            $parte = self::detalleDeAsignacion($asignacion, 'resultado', $fila);
            $avisos[] = 'En este tipo de caja Dif. cajero = Resultado del listado.';

            return [
                'movimientos' => $parte['movimientos'],
                'avisos' => $avisos,
                'formula' => $formula,
            ];
        }

        return [
            'movimientos' => [],
            'avisos' => ['Sin desglose para esta columna.'],
            'formula' => $formula,
        ];
    }

    private static function formulaColumna(string $columna, string $tarea = ''): ?string
    {
        return match ($columna) {
            'egresos' => 'Efectivo de habilitaciones conformes (billetes/ME).',
            'ingresos' => in_array($tarea, ['G', 'H'], true)
                ? 'Efectivo + T.crédito − Recargo + T.débito + QR + Fallo mozo + Cta.cte./transferencia.'
                : 'Efectivo + T.crédito − Recargo + T.débito + QR.',
            'fichas_casino' => 'Fichas de las rendiciones conformes (tot_rendicion_ficha de Anita).',
            'pago_cashless' => 'Campo pago cashless de cada rendición conforme.',
            'vta_cashless' => 'Campo venta cashless de cada rendición conforme.',
            'pago_voucher' => 'Campo pago voucher de cada rendición conforme.',
            'venta_voucher' => 'Campo venta voucher de cada rendición conforme.',
            'online' => 'Ventas online − Pagos online (neto Del Rio ONLINE).',
            'apuestas' => 'Pago apuestas − Venta apuestas.',
            'resultado' => 'Ingresos − Egresos + (pago cashless − vta. cashless) + Fichas + Online + (pago voucher − venta voucher) + apuestas.',
            'diferencia' => in_array($tarea, ['G', 'H'], true)
                ? 'Diferencia del cierre de caja (gastro/hotel).'
                : ($tarea === 'E' ? 'Siempre 0 en caja de empleados.' : 'Igual al Resultado.'),
            'pie_tarjetas' => 'T. crédito + T. débito + QR.',
            default => null,
        };
    }

    private static function comoAuditar(string $columna, string $tarea = ''): ?string
    {
        return match ($columna) {
            'egresos' => 'Abrí cada habilitación conforme de la caja y verificá el efectivo (billetes y ME valorizada).',
            'ingresos' => in_array($tarea, ['G', 'H'], true)
                ? 'Abrí las rendiciones conformes y sumá efectivo, tarjetas (− recargo), QR, fallo mozo y cta.cte./transferencia.'
                : 'Abrí las rendiciones conformes y sumá efectivo + tarjetas (− recargo) + QR.',
            'fichas_casino', 'rend_fichas' => 'En cada rendición conforme revisá las líneas de fichas (FVAL/FCOL) y su valuación.',
            'hab_fichas' => 'En cada habilitación conforme revisá las líneas de fichas habilitadas.',
            'pago_cashless', 'vta_cashless', 'pago_voucher', 'venta_voucher',
            'pago_online', 'venta_online', 'cierre_x',
            'tarjeta_credito', 'tarjeta_debito', 'total_qr', 'recargo_fin' => 'Entrá a cada rendición conforme y controlá el campo homónimo del comprobante.',
            'efectivo' => 'En cada rendición conforme sumá billetes y moneda extranjera valorizada a la fecha.',
            'ctacte' => 'En cada rendición conforme sumá cta.cte. hotel + transferencia hotel.',
            'online' => 'En cada rendición conforme restá pago online a venta online; el neto es el aporte.',
            'apuestas' => 'En cada rendición conforme restá venta apuestas a pago apuestas.',
            'resultado' => 'Revisá cada componente (ingresos, egresos, cashless, fichas, online, voucher, apuestas) con el mismo detalle de esta auditoría.',
            'diferencia' => in_array($tarea, ['G', 'H'], true)
                ? 'Abrí el cierre de caja: diferencia = rendido − habilitado − a rendir/resultado.'
                : ($tarea === 'E'
                    ? 'En caja de empleados el listado fuerza diferencia en cero.'
                    : 'Coincide con el Resultado; auditá ese importe.'),
            'pie_tarjetas' => 'Sumá crédito, débito y QR de todas las cajas del día.',
            default => 'Abrí los documentos listados abajo y contrastá cada renglón con el aporte.',
        };
    }

    private static function cargarAsignacion(TesoreriaCrownJornada $jornada, int $asignacionId): TesoreriaCrownAsignacion
    {
        $asignacion = TesoreriaCrownAsignacion::query()
            ->with([
                'deposito',
                'cajero',
                'cierre',
                'comprobantes.lineas.valor.tipoValor',
                'comprobantes.reversa',
                'jornada',
            ])
            ->where('jornada_id', $jornada->id)
            ->findOrFail($asignacionId);

        return $asignacion;
    }

    /**
     * @return array{
     *     efectivo: float,
     *     fichas: float,
     *     renglones_efectivo: list<array{etiqueta: string, importe: float}>,
     *     renglones_fichas: list<array{etiqueta: string, importe: float}>
     * }
     */
    private static function partes(TesoreriaCrownComprobante $doc): array
    {
        $efectivo = 0.0;
        $fichas = 0.0;
        $renglonesEfectivo = [];
        $renglonesFichas = [];
        $fecha = $doc->jornada?->fecha?->format('Y-m-d') ?? now()->format('Y-m-d');
        foreach ($doc->lineas as $linea) {
            $valor = $linea->valor;
            $tipo = $valor?->tipoValor;
            if ($valor === null || $tipo === null) {
                continue;
            }
            $importe = round((float) $linea->cantidad * (float) $valor->denominacion, 2);
            $codigo = (string) $tipo->codigo;
            $etiqueta = trim((string) ($valor->nombre ?? $tipo->nombre ?? $codigo));
            if ($etiqueta === '') {
                $etiqueta = $codigo;
            }
            if (in_array($codigo, ['FVAL', 'FCOL'], true)) {
                $fichas = round($fichas + $importe, 2);
                $renglonesFichas[] = [
                    'etiqueta' => $etiqueta.' × '.(float) $linea->cantidad,
                    'importe' => $importe,
                ];

                continue;
            }
            if ($tipo->es_moneda_referencia || $tipo->cotizacion_fija) {
                $efectivo = round($efectivo + $importe, 2);
                $renglonesEfectivo[] = [
                    'etiqueta' => $etiqueta.' × '.(float) $linea->cantidad,
                    'importe' => $importe,
                ];

                continue;
            }
            $tasa = TesoreriaCrownCierreCalculo::tasaDocumento($doc, $fecha, $codigo, (int) $tipo->id);
            if ($tasa > 0) {
                $enPesos = round($importe * $tasa, 2);
                $efectivo = round($efectivo + $enPesos, 2);
                $renglonesEfectivo[] = [
                    'etiqueta' => $etiqueta.' × '.(float) $linea->cantidad.' (×'.number_format($tasa, 4, ',', '.').')',
                    'importe' => $enPesos,
                ];
            }
        }

        return [
            'efectivo' => $efectivo,
            'fichas' => $fichas,
            'renglones_efectivo' => $renglonesEfectivo,
            'renglones_fichas' => $renglonesFichas,
        ];
    }

    /**
     * @param  list<array{etiqueta: string, importe: float}>  $renglones
     * @return array<string, mixed>
     */
    private static function movimientoComprobante(TesoreriaCrownComprobante $doc, float $aporte, array $renglones): array
    {
        $tipo = TesoreriaCrownComprobanteSupport::tipo((string) $doc->tipo);
        $numero = TesoreriaCrownNumeradorSupport::visible((int) $doc->numero);

        return [
            'kind' => 'comprobante',
            'id' => (int) $doc->id,
            'etiqueta' => $tipo.' '.$numero,
            'estado' => TesoreriaCrownComprobanteSupport::estado((string) $doc->estado),
            'aporte' => round($aporte, 2),
            'url' => route('editar_tesoreria_crown_movimiento', [
                'id' => $doc->id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]),
            'renglones' => array_values(array_filter(
                $renglones,
                fn (array $r) => abs((float) $r['importe']) >= 0.001 || ($r['etiqueta'] ?? '') !== ''
            )),
        ];
    }

    /**
     * @param  list<array{etiqueta: string, importe: float}>  $renglones
     * @return array<string, mixed>
     */
    private static function movimientoMesa(TesoreriaCrownMesa $mesa, string $mesaEtiqueta, float $aporte, array $renglones): array
    {
        $numero = TesoreriaCrownNumeradorSupport::visible((int) $mesa->numero);

        return [
            'kind' => 'mesa',
            'id' => (int) $mesa->id,
            'etiqueta' => 'Estado final '.$numero.' · '.$mesaEtiqueta,
            'estado' => 'Confirmado',
            'aporte' => round($aporte, 2),
            'url' => route('editar_tesoreria_crown_mesa', [
                'id' => $mesa->id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]),
            'renglones' => $renglones,
        ];
    }
}
