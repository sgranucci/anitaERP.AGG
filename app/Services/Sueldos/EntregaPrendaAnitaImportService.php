<?php

namespace App\Services\Sueldos;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Models\Stock\Color;
use App\Models\Stock\Talle;
use App\Models\Sueldos\Empleado_Sueldos;
use App\Models\Sueldos\Entrega_Prenda_Articulo_Sueldos;
use App\Models\Sueldos\Entrega_Prenda_Sueldos;
use App\Models\Sueldos\Prenda_Articulo_Sueldos;
use App\Models\Sueldos\Prenda_Sueldos;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Importa el histórico de entregas de indumentaria desde Anita (Informix):
 *   entrprenda (cabecera) + entrprendav (líneas) -> ledger entrega_prenda_sueldos.
 *
 * Es un backfill puro: NO genera movimiento de stock ni asiento (esas bajas ya
 * ocurrieron en el pasado). Idempotente por entrp_id (columna origen_anita_id).
 * Sync pull unilateral: nunca escribe hacia Anita.
 */
class EntregaPrendaAnitaImportService
{
    private const TABLA_CAB = 'entrprenda';

    private const TABLA_DET = 'entrprendav';

    /**
     * @param  array{desde?:?string, empresa_anita?:?int, estado?:?string, dry_run?:bool}  $opciones
     * @return array{leidas:int, importadas:int, ya_existentes:int, sin_empleado:int, sin_empresa:int, sin_lineas:int, lineas:int, lineas_sin_prenda:int, errores:list<string>}
     */
    public function importar(array $opciones = []): array
    {
        $desde = $opciones['desde'] ?? null;                 // 'YYYY-MM-DD'
        $empresaAnitaFiltro = $opciones['empresa_anita'] ?? null;
        $estado = $opciones['estado'] ?? 'A';
        $dryRun = (bool) ($opciones['dry_run'] ?? false);

        $res = [
            'leidas' => 0, 'importadas' => 0, 'ya_existentes' => 0, 'sin_empleado' => 0,
            'sin_empresa' => 0, 'sin_lineas' => 0, 'lineas' => 0, 'lineas_sin_prenda' => 0, 'errores' => [],
        ];

        $mapaEmpresa = $this->mapaEmpresaAnita();       // [codigoAnita => empresaIdErp]
        $mapaColor = Color::query()->pluck('id', 'codigo')->all();
        $mapaTalle = Talle::query()->pluck('id', 'codigo')->all();
        $prendas = Prenda_Sueldos::query()->get(['id', 'codigo', 'vida_util_meses'])->keyBy('codigo');
        $mapaEmpleado = $this->mapaEmpleado();          // ["empresaId-legajo" => empleadoId]
        $variantes = $this->mapaVariantes();            // ["prendaId-colorId-talleId" => row]
        $yaImportadas = Entrega_Prenda_Sueldos::query()->whereNotNull('origen_anita_id')
            ->pluck('origen_anita_id')->map(fn ($v) => (int) $v)->flip()->all();

        $cabeceras = $this->leerCabeceras($desde, $empresaAnitaFiltro, $estado);
        $lineasPorCab = $this->leerLineasAgrupadas();

        foreach ($cabeceras as $cab) {
            $res['leidas']++;
            $anitaId = (int) ($cab['entrp_id'] ?? 0);
            if ($anitaId <= 0) {
                continue;
            }
            if (isset($yaImportadas[$anitaId])) {
                $res['ya_existentes']++;

                continue;
            }

            $empresaAnita = (int) ($cab['entrp_empresa'] ?? 0);
            $empresaId = $mapaEmpresa[$empresaAnita] ?? null;
            if (! $empresaId) {
                $res['sin_empresa']++;
                $this->log($res, "entrp #$anitaId: empresa Anita $empresaAnita sin mapeo en ERP.");

                continue;
            }

            $legajo = (int) ($cab['entrp_legajo'] ?? 0);
            $empleadoId = $mapaEmpleado[$empresaId.'-'.$legajo] ?? null;
            if (! $empleadoId) {
                $res['sin_empleado']++;
                $this->log($res, "entrp #$anitaId: empleado legajo $legajo (empresa $empresaId) no encontrado.");

                continue;
            }

            $fecha = $this->parsearFecha($cab['entrp_fecha'] ?? null);
            if ($fecha === null) {
                $this->log($res, "entrp #$anitaId: fecha inválida '".($cab['entrp_fecha'] ?? '')."'.");

                continue;
            }

            $lineas = $lineasPorCab[$anitaId] ?? [];
            $detalle = [];
            foreach ($lineas as $ln) {
                $res['lineas']++;
                $prendaCod = (int) ($ln['entrpv_prenda'] ?? 0);
                $prenda = $prendas[$prendaCod] ?? null;
                if ($prenda === null) {
                    $res['lineas_sin_prenda']++;

                    continue; // prenda_id es NOT NULL con FK: no se puede insertar sin prenda
                }
                $colorId = $mapaColor[(int) ($ln['entrpv_color'] ?? 0)] ?? null;
                $talleId = $mapaTalle[(int) ($ln['entrpv_talle'] ?? 0)] ?? null;
                $variante = $variantes[$prenda->id.'-'.(int) $colorId.'-'.(int) $talleId] ?? null;

                $cant = (float) ($ln['entrpv_cantentr'] ?? 0);
                if ($cant <= 0) {
                    $cant = (float) ($ln['entrpv_cantidad'] ?? 0);
                }
                if ($cant <= 0) {
                    continue;
                }

                $vidaUtil = (int) ($prenda->vida_util_meses ?? 0);
                $venceEl = $vidaUtil > 0 ? $fecha->copy()->addMonthsNoOverflow($vidaUtil)->toDateString() : null;

                $detalle[] = [
                    'prenda_id' => (int) $prenda->id,
                    'prenda_articulo_id' => $variante ? (int) $variante->id : null,
                    'color_id' => $colorId ? (int) $colorId : ($variante ? (int) $variante->color_id : null),
                    'talle_id' => $talleId ? (int) $talleId : ($variante ? (int) $variante->talle_id : null),
                    'articulo_id' => $variante ? (int) $variante->articulo_id : null,
                    'sku' => $variante ? $variante->sku : null,
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

            try {
                DB::transaction(function () use ($cab, $anitaId, $empleadoId, $fecha, $detalle) {
                    $entrega = Entrega_Prenda_Sueldos::create([
                        'empleado_id' => $empleadoId,
                        'fecha' => $fecha->toDateString(),
                        'anio' => (int) $fecha->format('Y'),
                        'deposito_id' => null,
                        'tipotransaccion_stock_id' => null,
                        'movimientostock_id' => null,
                        'observacion' => $this->leyenda($cab, $anitaId),
                        'usuario_id' => null,
                        'origen_anita_id' => $anitaId,
                    ]);
                    foreach ($detalle as $d) {
                        $entrega->articulos()->create($d);
                    }
                });
                $res['importadas']++;
            } catch (\Throwable $e) {
                $this->log($res, "entrp #$anitaId: error al grabar - ".$e->getMessage());
            }
        }

        return $res;
    }

    /** @return array<int,int> [codigoAnita => empresaIdErp] */
    private function mapaEmpresaAnita(): array
    {
        $mapa = [];
        foreach (Empresa::query()->get(['id', 'codigo']) as $e) {
            $cod = trim((string) ($e->codigo ?? ''));
            if ($cod !== '' && ctype_digit($cod)) {
                $mapa[(int) $cod] = (int) $e->id;
            }
        }

        return $mapa;
    }

    /** @return array<string,int> ["empresaId-legajo" => empleadoId] */
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

    /** @return array<string,\App\Models\Sueldos\Prenda_Articulo_Sueldos> */
    private function mapaVariantes(): array
    {
        $mapa = [];
        foreach (Prenda_Articulo_Sueldos::query()->get(['id', 'prenda_id', 'color_id', 'talle_id', 'articulo_id', 'sku']) as $v) {
            $mapa[((int) $v->prenda_id).'-'.((int) $v->color_id).'-'.((int) $v->talle_id)] = $v;
        }

        return $mapa;
    }

    /**
     * @return list<array<string,string>>
     */
    private function leerCabeceras(?string $desde, ?int $empresaAnita, ?string $estado): array
    {
        $where = [];
        if ($estado !== null && $estado !== '') {
            $where[] = "entrp_estado = '".addslashes($estado)."'";
        }
        if ($empresaAnita) {
            $where[] = 'entrp_empresa = '.(int) $empresaAnita;
        }
        if ($desde) {
            $ymd = str_replace('-', '', substr($desde, 0, 10));
            if (ctype_digit($ymd)) {
                $where[] = 'entrp_fecha >= '.$ymd;
            }
        }
        $whereArmado = $where === [] ? '' : ('WHERE '.implode(' AND ', $where));

        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list', 'sistema' => 'sueldos', 'tabla' => self::TABLA_CAB,
            'campos' => 'entrp_id,entrp_fecha,entrp_empresa,entrp_legajo,entrp_estado,entrp_leyenda',
            'whereArmado' => $whereArmado, 'orderBy' => 'entrp_id',
        ]);
        $filas = json_decode((string) $raw, true);

        return is_array($filas) ? array_map(fn ($f) => (array) $f, $filas) : [];
    }

    /**
     * @return array<int, list<array<string,string>>> [entrpv_id => [líneas]]
     */
    private function leerLineasAgrupadas(): array
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list', 'sistema' => 'sueldos', 'tabla' => self::TABLA_DET,
            'campos' => 'entrpv_id,entrpv_orden,entrpv_prenda,entrpv_color,entrpv_talle,entrpv_cantidad,entrpv_cantentr',
            'orderBy' => 'entrpv_id,entrpv_orden',
        ]);
        $filas = json_decode((string) $raw, true);
        $grupos = [];
        if (is_array($filas)) {
            foreach ($filas as $f) {
                $f = (array) $f;
                $id = (int) ($f['entrpv_id'] ?? 0);
                if ($id > 0) {
                    $grupos[$id][] = $f;
                }
            }
        }

        return $grupos;
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
     * @param  array<string,string>  $cab
     */
    private function leyenda(array $cab, int $anitaId): ?string
    {
        $ley = trim((string) ($cab['entrp_leyenda'] ?? ''));
        $texto = 'Histórico Anita #'.$anitaId.($ley !== '' ? ' - '.$ley : '');

        return mb_substr($texto, 0, 255);
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
