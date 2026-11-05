<?php

namespace App\Http\Controllers\Caja;

use App\Http\Controllers\Controller;
use App\Models\Caja\TesoreriaCrownJornada;
use App\Support\Caja\TesoreriaCrownAcceso;
use App\Support\Caja\TesoreriaCrownAnexo2Calculo;
use App\Support\Caja\TesoreriaCrownAnexo7Calculo;
use App\Support\Caja\TesoreriaCrownAnexo8Calculo;
use App\Support\Caja\TesoreriaCrownListadoDocumento;
use App\Support\Caja\TesoreriaCrownResumenCajeroCalculo;
use App\Support\Caja\TesoreriaCrownSalaSupport;
use Illuminate\Http\Request;

/**
 * Export Pdf/Excel/Csv de anexos II, III, VII, VIII y resumen de cajeros.
 * La pantalla vive en la jornada (paneles); el permiso es el de anexo.
 */
class TesoreriaCrownAnexoController extends Controller
{
    public function listarAnexo2(Request $request, $formato = null)
    {
        return $this->exportar($request, $formato, 'anexo2', function (TesoreriaCrownJornada $jornada) {
            $armado = TesoreriaCrownAnexo2Calculo::deJornada($jornada);
            $filas = [];
            foreach ($armado['filas'] as $fila) {
                $filas[] = [
                    $fila['juego'],
                    $fila['mesa'],
                    $fila['banca'],
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
                ];
            }
            if ($armado['filas'] !== []) {
                $t = $armado['totales'];
                $filas[] = [
                    'Total', '', '',
                    (float) $t['creditos'], (float) $t['reposiciones'], (float) $t['estado_final'],
                    (float) $t['drop'], (float) $t['pagos_manuales'], (float) $t['recomp_banca'],
                    (float) $t['caja_empleado'], (float) $t['resultado'], '', '',
                ];
            }

            return [
                'titulo' => 'Anexo II '.$jornada->fecha->format('d/m/Y'),
                'subtitulo' => 'Resultado de mesas · l-resmesa.c',
                'archivo' => 'anexo2_'.$jornada->fecha->format('Ymd'),
                'titulo_corto' => 'Anexo II',
                'columnas' => [
                    ['label' => 'Juego', 'ancho' => 18, 'wrap' => true],
                    ['label' => 'Mesa', 'ancho' => 10],
                    ['label' => 'Banca', 'ancho' => 10],
                    ['label' => 'Créditos', 'ancho' => 12, 'numero' => true],
                    ['label' => 'Reposiciones', 'ancho' => 12, 'numero' => true],
                    ['label' => 'Estado final', 'ancho' => 12, 'numero' => true],
                    ['label' => 'Drop', 'ancho' => 12, 'numero' => true],
                    ['label' => 'Pagos man.', 'ancho' => 12, 'numero' => true],
                    ['label' => 'Recomp. banca', 'ancho' => 12, 'numero' => true],
                    ['label' => 'Caja empl.', 'ancho' => 12, 'numero' => true],
                    ['label' => 'Resultado', 'ancho' => 12, 'numero' => true],
                    ['label' => '% Mesa', 'ancho' => 10, 'numero' => true],
                    ['label' => '% Prop.', 'ancho' => 10, 'numero' => true],
                ],
                'filas' => $filas,
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
            $filas = [];
            foreach ($armado['filas'] as $fila) {
                $filas[] = [
                    $fila['caja'],
                    $fila['cajero'],
                    (float) $fila['cierre_x'],
                    (float) $fila['egresos'],
                    (float) $fila['efectivo'],
                    (float) $fila['tarjeta_credito'],
                    (float) $fila['tarjeta_debito'],
                    (float) $fila['total_qr'],
                    (float) $fila['recargo_fin'],
                    (float) $fila['ctacte'],
                    (float) $fila['ingresos'],
                    (float) $fila['hab_fichas'],
                    (float) $fila['rend_fichas'],
                    (float) $fila['pago_cashless'],
                    (float) $fila['vta_cashless'],
                    (float) $fila['pago_online'],
                    (float) $fila['venta_online'],
                    (float) $fila['apuestas'],
                    (float) $fila['resultado'],
                    (float) $fila['diferencia'],
                ];
            }
            if ($armado['filas'] !== []) {
                $t = $armado['totales'];
                $filas[] = [
                    'Total', '',
                    (float) $t['cierre_x'], (float) $t['egresos'], (float) $t['efectivo'],
                    (float) $t['tarjeta_credito'], (float) $t['tarjeta_debito'], (float) $t['total_qr'],
                    (float) $t['recargo_fin'], (float) $t['ctacte'], (float) $t['ingresos'],
                    (float) $t['hab_fichas'], (float) $t['rend_fichas'],
                    (float) $t['pago_cashless'], (float) $t['vta_cashless'],
                    (float) $t['pago_online'], (float) $t['venta_online'],
                    (float) $t['apuestas'], (float) $t['resultado'], (float) $t['diferencia'],
                ];
            }

            return [
                'titulo' => 'Anexo III '.$jornada->fecha->format('d/m/Y'),
                'subtitulo' => 'Cajeros ampliado · l-anexo3new.c',
                'archivo' => 'anexo3_'.$jornada->fecha->format('Ymd'),
                'titulo_corto' => 'Anexo III',
                'columnas' => [
                    ['label' => 'Caja', 'ancho' => 10],
                    ['label' => 'Cajero', 'ancho' => 22, 'wrap' => true],
                    ['label' => 'Cierre X', 'ancho' => 11, 'numero' => true],
                    ['label' => 'Egresos', 'ancho' => 11, 'numero' => true],
                    ['label' => 'Efectivo', 'ancho' => 11, 'numero' => true],
                    ['label' => 'T. crédito', 'ancho' => 11, 'numero' => true],
                    ['label' => 'T. débito', 'ancho' => 11, 'numero' => true],
                    ['label' => 'QR', 'ancho' => 10, 'numero' => true],
                    ['label' => 'Recargo', 'ancho' => 10, 'numero' => true],
                    ['label' => 'Cta.cte.', 'ancho' => 10, 'numero' => true],
                    ['label' => 'Ingresos', 'ancho' => 11, 'numero' => true],
                    ['label' => 'Hab. fichas', 'ancho' => 11, 'numero' => true],
                    ['label' => 'Rend. fichas', 'ancho' => 11, 'numero' => true],
                    ['label' => 'Pago cashless', 'ancho' => 11, 'numero' => true],
                    ['label' => 'Vta. cashless', 'ancho' => 11, 'numero' => true],
                    ['label' => 'Pago online', 'ancho' => 11, 'numero' => true],
                    ['label' => 'Vta. online', 'ancho' => 11, 'numero' => true],
                    ['label' => 'Apuestas', 'ancho' => 11, 'numero' => true],
                    ['label' => 'Resultado', 'ancho' => 11, 'numero' => true],
                    ['label' => 'Dif.', 'ancho' => 11, 'numero' => true],
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
