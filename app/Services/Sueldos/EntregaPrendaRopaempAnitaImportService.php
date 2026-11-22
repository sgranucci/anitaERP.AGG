<?php

namespace App\Services\Sueldos;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Models\Stock\Color;
use App\Models\Stock\Talle;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Entrega_Prenda_Sueldos;
use App\Models\Sueldos\Prenda_Articulo_Sueldos;
use App\Models\Sueldos\Prenda_Sueldos;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Histórico de entregas desde Anita `ropaemp` (El Bierzo / esquemas sin entrprenda).
 *
 * Cada fila es una línea; se agrupa en cabecera por (empresa, legajo, nro, fecha).
 * Idempotente por origen_anita_id = min(rop_orden) del grupo.
 * Backfill puro: sin stock ni asiento. Solo pull; nunca escribe hacia Anita.
 */
class EntregaPrendaRopaempAnitaImportService
{
    private const TABLA = 'ropaemp';

    /**
     * @param  array{desde?:?string, empresa_anita?:?int, dry_run?:bool}  $opciones
     * @return array{leidas:int, importadas:int, ya_existentes:int, sin_empleado:int, sin_empresa:int, sin_lineas:int, lineas:int, lineas_sin_prenda:int, omitidas_empresa:int, errores:list<string>}
     */
    public function importar(array $opciones = []): array
    {
        $desde = $opciones['desde'] ?? null;
        // El Bierzo: solo empresa Anita 1 (no hay mapeo de empresa 2).
        $empresaAnitaFiltro = $opciones['empresa_anita']
            ?? (EntornoEmpresaSupport::esElBierzo() ? 1 : null);
        $dryRun = (bool) ($opciones['dry_run'] ?? false);

        $res = [
            'leidas' => 0, 'importadas' => 0, 'ya_existentes' => 0, 'sin_empleado' => 0,
            'sin_empresa' => 0, 'sin_lineas' => 0, 'lineas' => 0, 'lineas_sin_prenda' => 0,
            'omitidas_empresa' => 0, 'errores' => [],
        ];

        $mapaEmpresa = $this->mapaEmpresaAnita();
        $mapaColor = Color::query()->pluck('id', 'codigo')->all();
        $mapaTalle = Talle::query()->pluck('id', 'codigo')->all();
        $prendas = Prenda_Sueldos::query()->get(['id', 'codigo', 'vida_util_meses'])->keyBy('codigo');
        $mapaEmpleado = $this->mapaEmpleado();
        $variantes = $this->mapaVariantes();
        $yaImportadas = Entrega_Prenda_Sueldos::query()->whereNotNull('origen_anita_id')
            ->pluck('origen_anita_id')->map(fn ($v) => (int) $v)->flip()->all();

        $grupos = $this->leerYAgrupar($desde, $empresaAnitaFiltro, $res);

        foreach ($grupos as $grupo) {
            $res['leidas']++;
            $anitaId = (int) ($grupo['origen_id'] ?? 0);
            if ($anitaId <= 0) {
                continue;
            }
            if (isset($yaImportadas[$anitaId])) {
                $res['ya_existentes']++;

                continue;
            }

            $empresaAnita = (int) ($grupo['empresa_anita'] ?? 0);
            $empresaId = $mapaEmpresa[$empresaAnita] ?? null;
            if (! $empresaId) {
                $res['sin_empresa']++;
                $this->log($res, "ropaemp grupo #$anitaId: empresa Anita $empresaAnita sin mapeo en ERP.");

                continue;
            }

            $legajo = (int) ($grupo['legajo'] ?? 0);
            $empleadoId = $mapaEmpleado[$empresaId.'-'.$legajo] ?? null;
            if (! $empleadoId) {
                $res['sin_empleado']++;
                $this->log($res, "ropaemp grupo #$anitaId: empleado legajo $legajo (empresa ERP $empresaId) no encontrado.");

                continue;
            }

            $fecha = $this->parsearFecha($grupo['fecha'] ?? null);
            if ($fecha === null) {
                $this->log($res, "ropaemp grupo #$anitaId: fecha inválida.");

                continue;
            }

            $detalle = [];
            foreach ($grupo['lineas'] as $ln) {
                $res['lineas']++;
                $prendaCod = (int) ($ln['rop_prenda'] ?? 0);
                $prenda = $prendas[$prendaCod] ?? null;
                if ($prenda === null) {
                    $res['lineas_sin_prenda']++;

                    continue;
                }

                $colorCod = (int) ($ln['rop_color'] ?? 0);
                $talleCod = (int) ($ln['rop_cod_talle'] ?? 0);
                $colorId = $colorCod > 0 ? ($mapaColor[$colorCod] ?? null) : null;
                $talleId = $talleCod > 0 ? ($mapaTalle[$talleCod] ?? null) : null;
                $variante = $variantes[$prenda->id.'-'.(int) $colorId.'-'.(int) $talleId] ?? null;

                $cant = (float) ($ln['rop_cantidad'] ?? 0);
                if ($cant <= 0) {
                    continue;
                }

                $sku = trim((string) ($ln['rop_articulo'] ?? ''));
                if ($sku === '' && $variante) {
                    $sku = (string) ($variante->sku ?? '');
                }

                $vidaUtil = (int) ($prenda->vida_util_meses ?? 0);
                $venceEl = $vidaUtil > 0 ? $fecha->copy()->addMonthsNoOverflow($vidaUtil)->toDateString() : null;

                $detalle[] = [
                    'prenda_id' => (int) $prenda->id,
                    'prenda_articulo_id' => $variante ? (int) $variante->id : null,
                    'color_id' => $colorId ? (int) $colorId : ($variante ? (int) $variante->color_id : null),
                    'talle_id' => $talleId ? (int) $talleId : ($variante ? (int) $variante->talle_id : null),
                    'articulo_id' => $variante && $variante->articulo_id
                        ? (int) $variante->articulo_id
                        : null,
                    'sku' => $sku !== '' ? mb_substr($sku, 0, 20) : ($variante->sku ?? null),
                    'cantidad' => $cant,
                    'vence_el' => $venceEl,
                ];
            }

            if ($detalle === []) {
                $res['sin_lineas']++;

                continue;
            }

            if ($dryRun) {
                $res['importadas']++;

                continue;
            }

            $nro = (int) ($grupo['nro'] ?? 0);
            try {
                DB::transaction(function () use ($anitaId, $empleadoId, $fecha, $detalle, $nro) {
                    $entrega = Entrega_Prenda_Sueldos::create([
                        'empleado_id' => $empleadoId,
                        'fecha' => $fecha->toDateString(),
                        'anio' => (int) $fecha->format('Y'),
                        'deposito_id' => null,
                        'tipotransaccion_stock_id' => null,
                        'movimientostock_id' => null,
                        'observacion' => mb_substr(
                            'Histórico Anita ropaemp nro '.$nro.' (orden '.$anitaId.')',
                            0,
                            255
                        ),
                        'usuario_id' => null,
                        'origen_anita_id' => $anitaId,
                    ]);
                    foreach ($detalle as $d) {
                        $entrega->articulos()->create($d);
                    }
                });
                $res['importadas']++;
            } catch (\Throwable $e) {
                $this->log($res, "ropaemp grupo #$anitaId: error al grabar - ".$e->getMessage());
            }
        }

        return $res;
    }

    /**
     * Primer id ERP por código Anita (evita que Villafranca codigo=1 pise a Bierzo id=1).
     *
     * @return array<int,int>
     */
    private function mapaEmpresaAnita(): array
    {
        $mapa = [];
        foreach (Empresa::query()->orderBy('id')->get(['id', 'codigo']) as $e) {
            $cod = trim((string) ($e->codigo ?? ''));
            if ($cod !== '' && ctype_digit($cod)) {
                $c = (int) $cod;
                if (! isset($mapa[$c])) {
                    $mapa[$c] = (int) $e->id;
                }
            }
        }

        return $mapa;
    }

    /** @return array<string,int> */
    private function mapaEmpleado(): array
    {
        $mapa = [];
        Empleado_Sueldos::query()->select(['id', 'empresa_id', 'legajo'])->orderBy('id')
            ->chunk(2000, function ($chunk) use (&$mapa) {
                foreach ($chunk as $emp) {
                    $mapa[((int) $emp->empresa_id).'-'.((int) $emp->legajo)] = (int) $emp->id;
                }
            });

        return $mapa;
    }

    /** @return array<string, Prenda_Articulo_Sueldos> */
    private function mapaVariantes(): array
    {
        $mapa = [];
        foreach (Prenda_Articulo_Sueldos::query()->get(['id', 'prenda_id', 'color_id', 'talle_id', 'articulo_id', 'sku']) as $v) {
            $mapa[((int) $v->prenda_id).'-'.(int) $v->color_id.'-'.(int) $v->talle_id] = $v;
        }

        return $mapa;
    }

    /**
     * @param  array{omitidas_empresa:int, errores:list<string>}  $res
     * @return list<array{origen_id:int, empresa_anita:int, legajo:int, nro:int, fecha:string, lineas:list<array<string,mixed>>}>
     */
    private function leerYAgrupar(?string $desde, ?int $empresaAnitaFiltro, array &$res): array
    {
        $api = new ApiAnita;
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'sueldos',
            'tabla' => self::TABLA,
            'campos' => 'rop_empresa,rop_legajo,rop_orden,rop_prenda,rop_color,rop_cantidad,'
                .'rop_cod_talle,rop_fecha_ent,rop_tipo,rop_nro,rop_articulo,rop_entrego_vieja',
            'orderBy' => 'rop_empresa,rop_legajo,rop_nro,rop_fecha_ent,rop_orden',
        ]));

        if (! empty($parsed['error_lectura'])) {
            $this->log($res, 'ropaemp: '.(string) $parsed['error_lectura']);

            return [];
        }

        $desdeYmd = null;
        if ($desde) {
            $ymd = str_replace('-', '', substr($desde, 0, 10));
            if (ctype_digit($ymd) && strlen($ymd) === 8) {
                $desdeYmd = $ymd;
            }
        }

        /** @var array<string, array{origen_id:int, empresa_anita:int, legajo:int, nro:int, fecha:string, lineas:list<array<string,mixed>>}> $grupos */
        $grupos = [];
        foreach ($parsed['filas'] as $row) {
            $empresaAnita = (int) ($row->rop_empresa ?? 0);
            if ($empresaAnitaFiltro !== null && $empresaAnita !== $empresaAnitaFiltro) {
                $res['omitidas_empresa']++;

                continue;
            }

            $fechaRaw = trim((string) ($row->rop_fecha_ent ?? ''));
            if ($desdeYmd !== null && $fechaRaw !== '' && ctype_digit($fechaRaw) && $fechaRaw < $desdeYmd) {
                continue;
            }

            $legajo = (int) ($row->rop_legajo ?? 0);
            $nro = (int) ($row->rop_nro ?? 0);
            $orden = (int) ($row->rop_orden ?? 0);
            if ($legajo <= 0 || $orden <= 0) {
                continue;
            }

            $key = $empresaAnita.'|'.$legajo.'|'.$nro.'|'.$fechaRaw;
            if (! isset($grupos[$key])) {
                $grupos[$key] = [
                    'origen_id' => $orden,
                    'empresa_anita' => $empresaAnita,
                    'legajo' => $legajo,
                    'nro' => $nro,
                    'fecha' => $fechaRaw,
                    'lineas' => [],
                ];
            } else {
                $grupos[$key]['origen_id'] = min($grupos[$key]['origen_id'], $orden);
            }

            $grupos[$key]['lineas'][] = [
                'rop_orden' => $orden,
                'rop_prenda' => $row->rop_prenda ?? null,
                'rop_color' => $row->rop_color ?? null,
                'rop_cantidad' => $row->rop_cantidad ?? null,
                'rop_cod_talle' => $row->rop_cod_talle ?? null,
                'rop_articulo' => $row->rop_articulo ?? null,
                'rop_entrego_vieja' => $row->rop_entrego_vieja ?? null,
            ];
        }

        return array_values($grupos);
    }

    private function parsearFecha($valor): ?Carbon
    {
        $s = trim((string) $valor);
        if ($s === '' || $s === '0') {
            return null;
        }
        try {
            if (ctype_digit($s) && strlen($s) === 8) {
                return Carbon::createFromFormat('Ymd', $s)->startOfDay();
            }

            return Carbon::parse($s)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array{errores:list<string>}  $res
     */
    private function log(array &$res, string $mensaje): void
    {
        if (count($res['errores']) < 50) {
            $res['errores'][] = $mensaje;
        }
    }
}
