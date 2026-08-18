<?php

namespace App\Services\Sueldos;

use App\ApiAnita;
use App\Models\Sueldos\Liquidacion_Sueldos;
use App\Repositories\Sueldos\Novedad_SueldosRepository;
use App\Support\Sueldos\Anita\AnitaLiquidacionTipoMapa;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Sync prueba paralela: maeliq (master) → liquidacion_sueldos, luego novedades
 * filtradas por empresa + números de liquidación Anita.
 */
class AnitaLiquidacionNovedadSyncService
{
    public function __construct(
        private Novedad_SueldosRepository $novedades
    ) {
    }

    /**
     * @return array{
     *   liquidaciones: array{en_anita: int, importadas: int, actualizadas: int, omitidas: int, numeros: list<int>, errores: list<string>},
     *   novedades: array{en_anita: int, importados: int, actualizados: int, eliminados: int, omitidos: int, errores: list<string>}
     * }
     */
    public function sincronizarEmpresaDesdeFechaLiq(int $empresaId, int $fechaLiqDesde): array
    {
        ini_set('max_execution_time', '900');
        ini_set('memory_limit', '-1');

        $liq = $this->sincronizarMaeliq($empresaId, $fechaLiqDesde);
        $nov = [
            'en_anita' => 0,
            'importados' => 0,
            'actualizados' => 0,
            'eliminados' => 0,
            'omitidos' => 0,
            'errores' => [],
        ];

        if ($liq['numeros'] !== []) {
            $nov = $this->novedades->sincronizarConAnita([
                'empresa_id' => $empresaId,
                'numeros_liquidacion' => $liq['numeros'],
            ]);
        } else {
            $nov['errores'][] = 'Sin liquidaciones Anita para traer novedades.';
        }

        return [
            'liquidaciones' => $liq,
            'novedades' => $nov,
        ];
    }

    /**
     * @return array{en_anita: int, importadas: int, actualizadas: int, omitidas: int, numeros: list<int>, errores: list<string>}
     */
    public function sincronizarMaeliq(int $empresaId, int $fechaLiqDesde): array
    {
        $resultado = [
            'en_anita' => 0,
            'importadas' => 0,
            'actualizadas' => 0,
            'omitidas' => 0,
            'numeros' => [],
            'errores' => [],
        ];

        $api = new ApiAnita();
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => 'maeliq',
            'campos' => 'mael_empresa, mael_liquidacion, mael_fecha_liq, mael_detalle,'
                .' mael_tipo_liq, mael_fecha_pago, mael_estado, mael_lugar_pago',
            'orderBy' => 'mael_empresa, mael_liquidacion',
        ]);
        $parsed = ApiAnita::parsearRespuestaLista(is_string($raw) ? $raw : null);
        if (! empty($parsed['error_lectura'])) {
            $resultado['errores'][] = 'maeliq: '.$parsed['error_lectura'];

            return $resultado;
        }

        $candidatas = [];
        foreach ($parsed['filas'] as $f) {
            $emp = (int) ($f->mael_empresa ?? 0);
            $fecha = (int) ($f->mael_fecha_liq ?? 0);
            $num = (int) ($f->mael_liquidacion ?? 0);
            if ($emp !== $empresaId || $fecha < $fechaLiqDesde || $num <= 0) {
                continue;
            }
            $candidatas[] = $f;
        }
        $resultado['en_anita'] = count($candidatas);

        foreach ($candidatas as $f) {
            try {
                $numero = (int) $f->mael_liquidacion;
                $fechaLiq = (int) ($f->mael_fecha_liq ?? 0);
                $fechaPago = (int) ($f->mael_fecha_pago ?? 0);
                $detalle = trim((string) ($f->mael_detalle ?? ''));
                $tipo = AnitaLiquidacionTipoMapa::mapear($f->mael_tipo_liq ?? '', $detalle);
                $estado = AnitaLiquidacionTipoMapa::mapearEstado($f->mael_estado ?? '');

                $periodoYm = $fechaLiq >= 19000101
                    ? (int) floor($fechaLiq / 100)
                    : (int) floor($numero / 100);
                if ($periodoYm < 190001 || $periodoYm > 299912) {
                    $periodoYm = (int) Carbon::now()->format('Ym');
                }
                $anio = (int) floor($periodoYm / 100);
                $mes = (int) ($periodoYm % 100);
                if ($mes < 1 || $mes > 12) {
                    $mes = 1;
                }

                $desde = Carbon::create($anio, $mes, 1)->startOfDay();
                $hasta = $desde->copy()->endOfMonth();
                $fechaLiqStr = $this->fechaAnita($fechaLiq) ?? $hasta->toDateString();
                $fechaPagoStr = $this->fechaAnita($fechaPago);

                $payload = [
                    'empresa_id' => $empresaId,
                    'numero' => $numero,
                    'descripcion' => $detalle !== '' ? $detalle : ('Liq. Anita '.$numero),
                    'tipo' => $tipo,
                    'periodo' => (string) $periodoYm,
                    'periodo_anio' => $anio,
                    'periodo_mes' => $mes,
                    'periodo_desde' => $desde->toDateString(),
                    'periodo_hasta' => $hasta->toDateString(),
                    'fecha_liquidacion' => $fechaLiqStr,
                    'fecha_pago' => $fechaPagoStr,
                    'lugar_pago' => trim((string) ($f->mael_lugar_pago ?? '')) ?: null,
                    'estado' => $estado,
                    'simulacion' => false,
                    'acumula_novedades' => true,
                    'alcance' => 'todos',
                    'observacion' => 'Sync Anita maeliq',
                ];

                $existente = Liquidacion_Sueldos::query()
                    ->where('empresa_id', $empresaId)
                    ->where('numero', $numero)
                    ->first();

                if ($existente) {
                    $existente->update([
                        'descripcion' => $payload['descripcion'],
                        'tipo' => $payload['tipo'],
                        'periodo' => $payload['periodo'],
                        'periodo_anio' => $payload['periodo_anio'],
                        'periodo_mes' => $payload['periodo_mes'],
                        'periodo_desde' => $payload['periodo_desde'],
                        'periodo_hasta' => $payload['periodo_hasta'],
                        'fecha_liquidacion' => $payload['fecha_liquidacion'],
                        'fecha_pago' => $payload['fecha_pago'],
                        'lugar_pago' => $payload['lugar_pago'],
                        'estado' => $payload['estado'],
                        'observacion' => $payload['observacion'],
                    ]);
                    $resultado['actualizadas']++;
                } else {
                    DB::table('liquidacion_sueldos')->insert(array_merge($payload, [
                        'usuario_id' => optional(auth()->user())->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]));
                    $resultado['importadas']++;
                }

                $resultado['numeros'][] = $numero;
            } catch (\Throwable $e) {
                $resultado['omitidas']++;
                $resultado['errores'][] = 'Liq '.(int) ($f->mael_liquidacion ?? 0).': '.$e->getMessage();
            }
        }

        $resultado['numeros'] = array_values(array_unique($resultado['numeros']));
        sort($resultado['numeros']);

        return $resultado;
    }

    private function fechaAnita(int $yyyymmdd): ?string
    {
        if ($yyyymmdd < 19000101) {
            return null;
        }
        $s = (string) $yyyymmdd;
        if (strlen($s) !== 8) {
            return null;
        }

        return substr($s, 0, 4).'-'.substr($s, 4, 2).'-'.substr($s, 6, 2);
    }
}
