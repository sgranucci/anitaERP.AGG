<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownAnexo2Calculo;
use App\Support\Caja\TesoreriaCrownAnexo3AuditoriaSupport;
use App\Support\Caja\TesoreriaCrownAnexo7Calculo;
use App\Support\Caja\TesoreriaCrownAnexo8Calculo;
use App\Support\Caja\TesoreriaCrownListadoDocumento;
use App\Support\Caja\TesoreriaCrownResumenCajeroCalculo;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Export Pdf/Excel/Csv de anexos II, III, VII, VIII y resumen de cajeros.
 * La pantalla vive en la jornada (paneles); el permiso es el de anexo.
 */
class TesoreriaCrownAnexoController extends Controller
{
    public function listarAnexo2(Request $request, $formato = null)
    {
        return $this->exportar($request, $formato, 'anexo2', function (TesoreriaCrownJornada $jornada) use ($request) {
            $grupo = TesoreriaCrownAnexo2Calculo::resolverGrupoPermitido($request->query('grupo_anexo2'));
            if ($grupo === null) {
                abort(403, 'No tenés permiso para exportar ese grupo del anexo II.');
            }
            $armado = TesoreriaCrownAnexo2Calculo::deJornada($jornada, $grupo);
            $etiqueta = TesoreriaCrownAnexo2Calculo::GRUPOS[$grupo] ?? $grupo;
            $vacioMesas = array_fill(0, 21, '');
            $filas = [];
            foreach ($armado['secciones'] as $seccion) {
                $filas[] = array_replace($vacioMesas, [0 => $seccion['titulo']]);
                if (($seccion['tipo'] ?? '') === 'mesas') {
                    foreach ($seccion['filas'] as $fila) {
                        $filas[] = [
                            $fila['juego'],
                            $fila['mesa'],
                            (float) ($fila['banca_tipo'] ?? 0),
                            (float) $fila['creditos'],
                            (float) $fila['reposiciones'],
                            (float) $fila['estado_final'],
                            (float) $fila['drop'],
                            (float) $fila['pagos_manuales'],
                            (float) $fila['recomp_banca'],
                            (float) $fila['caja_empleado'],
                            (float) $fila['resultado'],
                            $fila['pct_mesa'] === null ? '' : (float) $fila['pct_mesa'],
                            $fila['pct_prop'] === null ? '' : (float) $fila['pct_prop'],
                            (float) ($fila['acum_estado_final'] ?? 0),
                            (float) ($fila['acum_drop'] ?? 0),
                            (float) ($fila['acum_pagos_manuales'] ?? 0),
                            (float) ($fila['acum_recomp_banca'] ?? 0),
                            (float) ($fila['acum_caja_empleado'] ?? 0),
                            (float) ($fila['acum_resultado'] ?? 0),
                            ($fila['acum_pct_mesa'] ?? null) === null ? '' : (float) $fila['acum_pct_mesa'],
                            ($fila['acum_pct_prop'] ?? null) === null ? '' : (float) $fila['acum_pct_prop'],
                        ];
                    }
                } elseif (($seccion['tipo'] ?? '') === 'maquinas') {
                    foreach ($seccion['filas'] as $fila) {
                        $filas[] = array_replace($vacioMesas, [
                            0 => $fila['concepto'],
                            3 => (float) $fila['diario'],
                        ]);
                    }
                } elseif (($seccion['tipo'] ?? '') === 'gastronomia') {
                    foreach ($seccion['filas'] as $fila) {
                        $filas[] = array_replace($vacioMesas, [
                            0 => (string) ($fila['etiqueta'] ?? ''),
                            3 => (float) ($fila['ventas_z'] ?? 0),
                            4 => (float) ($fila['rendiciones'] ?? 0),
                            5 => (float) ($fila['anulados'] ?? 0),
                            6 => (float) ($fila['invitaciones'] ?? 0),
                            7 => (float) ($fila['tarjetas'] ?? 0),
                            13 => (float) ($fila['acum_ventas_z'] ?? 0),
                            14 => (float) ($fila['acum_rendiciones'] ?? 0),
                            15 => (float) ($fila['acum_anulados'] ?? 0),
                            16 => (float) ($fila['acum_invitaciones'] ?? 0),
                            17 => (float) ($fila['acum_tarjetas'] ?? 0),
                        ]);
                    }
                }
                $filas[] = $vacioMesas;
            }

            return [
                'titulo' => 'Anexo II '.$jornada->fecha->format('d/m/Y'),
                'subtitulo' => 'l-resmesa.c · grupo '.$etiqueta.' · acumulado desde 1º del mes',
                'archivo' => 'anexo2_'.$grupo.'_'.$jornada->fecha->format('Ymd'),
                'titulo_corto' => 'Anexo II',
                'columnas' => [
                    ['label' => 'Juego', 'ancho' => 18, 'wrap' => true],
                    ['label' => 'Mesa', 'ancho' => 8],
                    ['label' => 'Banca tipo', 'ancho' => 12, 'numero' => true],
                    ['label' => 'Créditos', 'ancho' => 11, 'numero' => true],
                    ['label' => 'Reposiciones', 'ancho' => 11, 'numero' => true],
                    ['label' => 'Estado final', 'ancho' => 12, 'numero' => true],
                    ['label' => 'Drop', 'ancho' => 11, 'numero' => true],
                    ['label' => 'Pagos man.', 'ancho' => 11, 'numero' => true],
                    ['label' => 'Recomp. banca', 'ancho' => 12, 'numero' => true],
                    ['label' => 'Caja empl.', 'ancho' => 11, 'numero' => true],
                    ['label' => 'Resultado', 'ancho' => 11, 'numero' => true],
                    ['label' => '% Mesa', 'ancho' => 9, 'numero' => true],
                    ['label' => '% Prop.', 'ancho' => 9, 'numero' => true],
                    ['label' => 'Acum. estado final', 'ancho' => 12, 'numero' => true],
                    ['label' => 'Acum. drop', 'ancho' => 11, 'numero' => true],
                    ['label' => 'Acum. pagos', 'ancho' => 11, 'numero' => true],
                    ['label' => 'Acum. recomp.', 'ancho' => 12, 'numero' => true],
                    ['label' => 'Acum. caja', 'ancho' => 11, 'numero' => true],
                    ['label' => 'Acum. resultado', 'ancho' => 12, 'numero' => true],
                    ['label' => 'Acum. % mesa', 'ancho' => 10, 'numero' => true],
                    ['label' => 'Acum. % prop.', 'ancho' => 10, 'numero' => true],
                ],
                'filas' => $filas,
                'queryparams' => ['grupo_anexo2' => $grupo],
            ];
        });
    }

    public function listarRescajero(Request $request, $formato = null)
    {
        return $this->exportar($request, $formato, 'rescajero', function (TesoreriaCrownJornada $jornada) {
            $armado = TesoreriaCrownResumenCajeroCalculo::deJornada($jornada);
            $filas = [];
            foreach ($armado['grupos'] as $grupo) {
                $filas[] = [$grupo['titulo'], '', '', '', '', '', '', '', '', '', '', ''];
                foreach ($grupo['filas'] as $fila) {
                    $filas[] = [
                        $fila['caja'],
                        $fila['cajero'],
                        (float) $fila['egresos'],
                        (float) $fila['ingresos'],
                        (float) $fila['fichas_casino'],
                        (float) $fila['pago_cashless'],
                        (float) $fila['vta_cashless'],
                        (float) $fila['pago_voucher'],
                        (float) $fila['venta_voucher'],
                        (float) $fila['online'],
                        (float) $fila['resultado'],
                        (float) $fila['diferencia'],
                    ];
                }
                $t = $grupo['totales'];
                $filas[] = [
                    'Total '.$grupo['titulo'], '',
                    (float) $t['egresos'], (float) $t['ingresos'], (float) $t['fichas_casino'],
                    (float) $t['pago_cashless'], (float) $t['vta_cashless'],
                    (float) $t['pago_voucher'], (float) $t['venta_voucher'],
                    (float) $t['online'], (float) $t['resultado'], (float) $t['diferencia'],
                ];
            }
            if ($armado['grupos'] !== []) {
                $t = $armado['totales'];
                $filas[] = [
                    'Total general', '',
                    (float) $t['egresos'], (float) $t['ingresos'], (float) $t['fichas_casino'],
                    (float) $t['pago_cashless'], (float) $t['vta_cashless'],
                    (float) $t['pago_voucher'], (float) $t['venta_voucher'],
                    (float) $t['online'], (float) $t['resultado'], (float) $t['diferencia'],
                ];
            }

            return [
                'titulo' => 'Resumen de cajeros '.$jornada->fecha->format('d/m/Y'),
                'subtitulo' => 'l-rescajero.c · por tipo de caja',
                'archivo' => 'rescajero_'.$jornada->fecha->format('Ymd'),
                'titulo_corto' => 'Res. cajeros',
                'columnas' => [
                    ['label' => 'Caja', 'ancho' => 18, 'wrap' => true],
                    ['label' => 'Cajero', 'ancho' => 28, 'wrap' => true],
                    ['label' => 'Egresos', 'ancho' => 12, 'numero' => true],
                    ['label' => 'Ingresos', 'ancho' => 12, 'numero' => true],
                    ['label' => 'Fichas', 'ancho' => 12, 'numero' => true],
                    ['label' => 'Pago cashless', 'ancho' => 12, 'numero' => true],
                    ['label' => 'Vta. cashless', 'ancho' => 12, 'numero' => true],
                    ['label' => 'Pago voucher', 'ancho' => 12, 'numero' => true],
                    ['label' => 'Venta voucher', 'ancho' => 12, 'numero' => true],
                    ['label' => 'Online', 'ancho' => 12, 'numero' => true],
                    ['label' => 'Resultado', 'ancho' => 12, 'numero' => true],
                    ['label' => 'Dif. cajero', 'ancho' => 12, 'numero' => true],
                ],
                'filas' => $filas,
            ];
        });
    }

    public function listarAnexo3(Request $request, $formato = null)
    {
        return $this->exportar($request, $formato, 'anexo3', function (TesoreriaCrownJornada $jornada) {
            $armado = TesoreriaCrownResumenCajeroCalculo::deJornada($jornada);
            $vacio = array_fill(0, 22, '');
            $filaNums = static function (array $f): array {
                return [
                    $f['caja'] ?? '',
                    $f['cajero'] ?? '',
                    (float) ($f['cierre_x'] ?? 0),
                    (float) ($f['egresos'] ?? 0),
                    (float) ($f['efectivo'] ?? 0),
                    (float) ($f['tarjeta_credito'] ?? 0),
                    (float) ($f['tarjeta_debito'] ?? 0),
                    (float) ($f['total_qr'] ?? 0),
                    (float) ($f['recargo_fin'] ?? 0),
                    (float) ($f['ctacte'] ?? 0),
                    (float) ($f['ingresos'] ?? 0),
                    (float) ($f['hab_fichas'] ?? 0),
                    (float) ($f['rend_fichas'] ?? 0),
                    (float) ($f['fichas_casino'] ?? 0),
                    (float) ($f['pago_cashless'] ?? 0),
                    (float) ($f['vta_cashless'] ?? 0),
                    (float) ($f['pago_online'] ?? 0),
                    (float) ($f['venta_online'] ?? 0),
                    (float) ($f['online'] ?? 0),
                    (float) ($f['apuestas'] ?? 0),
                    (float) ($f['resultado'] ?? 0),
                    (float) ($f['diferencia'] ?? 0),
                ];
            };
            $filas = [];
            foreach ($armado['grupos'] as $grupo) {
                $filas[] = array_replace($vacio, [0 => $grupo['titulo']]);
                foreach ($grupo['filas'] as $fila) {
                    $filas[] = $filaNums($fila);
                }
                $filas[] = $filaNums(array_merge($grupo['totales'], [
                    'caja' => 'Total '.$grupo['titulo'],
                    'cajero' => '',
                ]));
            }
            if ($armado['grupos'] !== []) {
                $filas[] = $filaNums(array_merge($armado['totales'], [
                    'caja' => 'Total general',
                    'cajero' => '',
                ]));
                $filas[] = array_replace($vacio, [
                    0 => 'Total de Tarj. Credito + Tarj. Debito + Total QR + Recargo fin.',
                    2 => (float) ($armado['total_tarjetas'] ?? 0),
                ]);
            }

            return [
                'titulo' => 'Anexo III '.$jornada->fecha->format('d/m/Y'),
                'subtitulo' => 'Cajeros ampliado · l-anexo3new.c',
                'archivo' => 'anexo3_'.$jornada->fecha->format('Ymd'),
                'titulo_corto' => 'Anexo III',
                'columnas' => [
                    ['label' => 'Caja', 'ancho' => 10],
                    ['label' => 'Cajero', 'ancho' => 20, 'wrap' => true],
                    ['label' => 'Cierre X', 'ancho' => 10, 'numero' => true],
                    ['label' => 'Egresos', 'ancho' => 10, 'numero' => true],
                    ['label' => 'Efectivo', 'ancho' => 10, 'numero' => true],
                    ['label' => 'T. crédito', 'ancho' => 10, 'numero' => true],
                    ['label' => 'T. débito', 'ancho' => 10, 'numero' => true],
                    ['label' => 'QR', 'ancho' => 9, 'numero' => true],
                    ['label' => 'Recargo', 'ancho' => 9, 'numero' => true],
                    ['label' => 'Cta.cte.', 'ancho' => 9, 'numero' => true],
                    ['label' => 'Ingresos', 'ancho' => 10, 'numero' => true],
                    ['label' => 'Hab. fichas', 'ancho' => 10, 'numero' => true],
                    ['label' => 'Rend. fichas', 'ancho' => 10, 'numero' => true],
                    ['label' => 'Fichas Casino', 'ancho' => 10, 'numero' => true],
                    ['label' => 'Pago cashless', 'ancho' => 10, 'numero' => true],
                    ['label' => 'Vta. cashless', 'ancho' => 10, 'numero' => true],
                    ['label' => 'Pagos online', 'ancho' => 10, 'numero' => true],
                    ['label' => 'Ventas online', 'ancho' => 10, 'numero' => true],
                    ['label' => 'Del Rio ONLINE', 'ancho' => 10, 'numero' => true],
                    ['label' => 'Apuestas Dep.', 'ancho' => 10, 'numero' => true],
                    ['label' => 'Resultado', 'ancho' => 10, 'numero' => true],
                    ['label' => 'Dif. cajero', 'ancho' => 10, 'numero' => true],
                ],
                'filas' => $filas,
            ];
        });
    }

    public function listarAnexo7(Request $request, $formato = null)
    {
        return $this->exportar($request, $formato, 'anexo7', function (TesoreriaCrownJornada $jornada) {
            $armado = TesoreriaCrownAnexo7Calculo::deJornada($jornada);
            $filas = [];
            foreach ($armado['filas'] as $fila) {
                $filas[] = [
                    $fila['caja'],
                    $fila['cajero'],
                    $fila['legajo'] ?: '',
                    $fila['concepto'],
                    (float) $fila['fallos'],
                    (float) $fila['anticipos'],
                    (float) $fila['excedentes'],
                ];
            }
            if ($armado['filas'] !== []) {
                $t = $armado['totales'];
                $filas[] = [
                    'Total', '', '', '',
                    (float) $t['fallos'], (float) $t['anticipos'], (float) $t['excedentes'],
                ];
            }

            return [
                'titulo' => 'Anexo VII '.$jornada->fecha->format('d/m/Y'),
                'subtitulo' => 'Fallos y excedentes · l-anexo7.c',
                'archivo' => 'anexo7_'.$jornada->fecha->format('Ymd'),
                'titulo_corto' => 'Anexo VII',
                'columnas' => [
                    ['label' => 'Caja', 'ancho' => 12],
                    ['label' => 'Cajero', 'ancho' => 28, 'wrap' => true],
                    ['label' => 'Legajo', 'ancho' => 10],
                    ['label' => 'Concepto', 'ancho' => 22, 'wrap' => true],
                    ['label' => 'Fallos', 'ancho' => 14, 'numero' => true],
                    ['label' => 'Anticipos', 'ancho' => 14, 'numero' => true],
                    ['label' => 'Excedentes', 'ancho' => 14, 'numero' => true],
                ],
                'filas' => $filas,
            ];
        });
    }

    public function listarAnexo8(Request $request, $formato = null)
    {
        return $this->exportar($request, $formato, 'anexo8', function (TesoreriaCrownJornada $jornada) {
            $armado = TesoreriaCrownAnexo8Calculo::deJornada($jornada);
            $filas = [];
            $fichas = $armado['fichas'] ?? null;
            if (is_array($fichas) && ($fichas['filas'] ?? []) !== []) {
                $filas[] = ['Stock de fichas (no entra al total)', '', '', ''];
                foreach ($fichas['filas'] as $fila) {
                    $filas[] = [
                        $fila['serie'].' '.$fila['descripcion'],
                        (float) $fila['valor'],
                        (float) $fila['cantidad'],
                        (float) $fila['monto'],
                    ];
                }
                $filas[] = ['', 'Total conversión valorizado', '', (float) $fichas['conversion_valorizada']];
                $filas[] = ['', 'Total poder público valorizado', '', (float) $fichas['poder_valorizado']];
                $filas[] = ['', 'Total poder público diario', '', (float) $fichas['diario_valorizado']];
                $filas[] = ['', '', '', ''];
            }
            $c = $armado['componentes'] ?? null;
            if (is_array($c)) {
                $filas[] = ['Componentes', '', '', ''];
                $filas[] = ['', 'Pesos en bóveda', '', (float) ($c['pesos'] ?? 0)];
                $filas[] = ['', 'Slots (recuento)', '', (float) ($c['slots'] ?? 0)];
                $filas[] = ['', 'Drops de mesa', '', (float) ($c['drops_mesa'] ?? 0)];
                $filas[] = ['', 'Items resumen final', '', (float) ($c['resumen'] ?? 0)];
                foreach ($c['resumen_detalle'] ?? [] as $item) {
                    $filas[] = ['', '  '.$item['descripcion'], '', (float) $item['monto']];
                }
                $filas[] = ['', 'Propina acumulada', '', (float) ($c['propina'] ?? 0)];
                if (((float) ($c['usd_pesos'] ?? 0)) > 0.001) {
                    $filas[] = [
                        '',
                        'Total dolares convertidos a pesos (equiv. '.(float) ($c['usd_face'] ?? 0).' u$s)',
                        '',
                        (float) ($c['usd_pesos'] ?? 0),
                    ];
                }
                if (((float) ($c['eur_pesos'] ?? 0)) > 0.001) {
                    $filas[] = [
                        '',
                        'Total euros convertidos a pesos (equiv. '.(float) ($c['eur_face'] ?? 0).' EUR)',
                        '',
                        (float) ($c['eur_pesos'] ?? 0),
                    ];
                }
                $filas[] = ['', 'Otras monedas segun cotiz.', '', (float) ($c['moneda_extranjera'] ?? 0)];
                $filas[] = ['', 'Total Anexo VIII', '', (float) $armado['total']];
                $filas[] = ['', '', '', ''];
            }
            foreach ($armado['lugares'] as $lugar) {
                $filas[] = [$lugar['lugar'], '', '', ''];
                foreach ($lugar['grupos'] as $grupo) {
                    $filas[] = [$grupo['titulo'], '', '', ''];
                    foreach ($grupo['filas'] as $fila) {
                        $filas[] = [
                            '',
                            $fila['denominacion'],
                            (float) $fila['cantidad'],
                            (float) $fila['monto'],
                        ];
                    }
                    $filas[] = ['', 'Subtotal '.$grupo['titulo'], '', (float) $grupo['monto']];
                }
                $filas[] = ['', 'Total '.$lugar['lugar'], '', (float) $lugar['total']];
            }
            if ($armado['lugares'] !== []) {
                $filas[] = ['', 'Total general', '', (float) $armado['total']];
            }

            return [
                'titulo' => 'Anexo VIII '.$jornada->fecha->format('d/m/Y'),
                'subtitulo' => 'Estado de bóveda · l-estboveda.c',
                'archivo' => 'anexo8_'.$jornada->fecha->format('Ymd'),
                'titulo_corto' => 'Anexo VIII',
                'columnas' => [
                    ['label' => 'Grupo', 'ancho' => 36, 'wrap' => true],
                    ['label' => 'Denominación', 'ancho' => 28, 'wrap' => true],
                    ['label' => 'Cantidad', 'ancho' => 14, 'numero' => true],
                    ['label' => 'Monto', 'ancho' => 16, 'numero' => true],
                ],
                'filas' => $filas,
            ];
        });
    }

    public function auditoriaAnexo3(Request $request, int $id): JsonResponse
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-anexo');

        $jornada = $this->jornadaEnAlcance($id);
        $columna = (string) $request->query('columna', '');
        $scope = (string) $request->query('scope', 'fila');
        $asignacionId = $request->query('asignacion_id');
        $tipoGrupo = $request->query('tipo');

        $detalle = TesoreriaCrownAnexo3AuditoriaSupport::deRequest(
            $jornada,
            $columna,
            $scope,
            $asignacionId !== null && $asignacionId !== '' ? (int) $asignacionId : null,
            $tipoGrupo !== null && $tipoGrupo !== '' ? (string) $tipoGrupo : null
        );

        return response()->json($detalle);
    }

    /**
     * @param  callable(TesoreriaCrownJornada): array<string, mixed>  $armar
     */
    private function exportar(Request $request, $formato, string $panel, callable $armar)
    {
        TesoreriaCrownAcceso::abortSiNoEsCrown();
        can('listar-tesoreria-crown-anexo');

        $jornada = $this->jornadaEnAlcance((int) $request->input('id'));
        if ($formato === null || $formato === '') {
            return redirect()->route('ver_tesoreria_crown_jornada', [
                'id' => $jornada->id,
                'panel' => $panel,
            ]);
        }

        ini_set('memory_limit', '-1');
        ini_set('max_execution_time', '0');

        $jornada->loadMissing('empresa');
        $payload = $armar($jornada);
        $payload['coleccion'] = collect([(object) ['nombreempresa' => (string) ($jornada->empresa->nombre ?? '')]]);

        $respuesta = TesoreriaCrownListadoDocumento::responder((string) $formato, $payload);
        if ($respuesta) {
            return $respuesta;
        }

        return redirect()->route('ver_tesoreria_crown_jornada', [
            'id' => $jornada->id,
            'panel' => $panel,
        ]);
    }

    private function jornadaEnAlcance(int $id): TesoreriaCrownJornada
    {
        $jornada = TesoreriaCrownJornada::query()->with('empresa')->findOrFail($id);
        TesoreriaCrownSalaSupport::exigida((int) $jornada->sala_id);

        return $jornada;
    }
}
