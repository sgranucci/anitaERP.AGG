<?php

namespace App\Support\Caja;

use App\Models\Caja\Bingo\ConfiguracionPuntoventaBingo;
use App\Models\Caja\Caja;
use App\Models\Caja\Caja_Asignacion;
use App\Models\Caja\Estacionamiento\ConfiguracionPuntoventaEstacionamiento;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Queries\Caja\Caja_AsignacionQueryInterface;
use App\Support\Caja\Bingo\BingoIdentificadorPc;
use App\Support\Caja\Estacionamiento\EstacionamientoIdentificadorPc;
use App\Support\Ventas\GastronomiaIdentificadorPc;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Resuelve la caja física para recepción de rendiciones / movimientos.
 *
 * Orden: caja explícita → (si requiere asignación) caja_asignacion del día →
 * config PC (estacionamiento / gastronomía / bingo) → caja default.
 */
final class CajaRecepcionPcSupport
{
    public static function requiereAsignacion(): bool
    {
        return (bool) config('caja.requiere_asignacion', false);
    }

    public static function cajaDefaultId(): int
    {
        $id = (int) config('caja.caja_default_id', 1);

        return $id > 0 ? $id : 1;
    }

    /**
     * @return array{0:int,1:string}
     */
    public static function resolver(?int $cajaParam = null, ?Request $request = null): array
    {
        if ($cajaParam !== null && $cajaParam > 0) {
            $desdeParam = self::desdeCajaId($cajaParam);
            if ($desdeParam[0] > 0) {
                return $desdeParam;
            }
        }

        if (self::requiereAsignacion()) {
            $desdeAsignacion = self::desdeAsignacionDelDia();
            if ($desdeAsignacion[0] > 0) {
                return $desdeAsignacion;
            }

            return [0, ''];
        }

        $desdePc = self::desdeConfiguracionPc($request);
        if ($desdePc[0] > 0) {
            return $desdePc;
        }

        return self::desdeCajaId(self::cajaDefaultId());
    }

    /**
     * @return array{0:int,1:string}
     */
    public static function desdeAsignacionDelDia(?int $usuarioId = null, ?Carbon $fecha = null): array
    {
        $usuarioId = $usuarioId ?? (int) (Auth::id() ?? 0);
        if ($usuarioId <= 0) {
            return [0, ''];
        }

        $fecha = $fecha ?? Carbon::now();
        /** @var Caja_AsignacionQueryInterface $query */
        $query = app(Caja_AsignacionQueryInterface::class);
        /** @var Caja_Asignacion|null $asignacion */
        $asignacion = $query->leeAsignacionPorUsuario($usuarioId, $fecha);
        if ($asignacion === null || (int) ($asignacion->caja_id ?? 0) <= 0) {
            return [0, ''];
        }

        $cajaId = (int) $asignacion->caja_id;
        $nombre = trim((string) ($asignacion->cajas->nombre ?? ''));
        if ($nombre === '') {
            return self::desdeCajaId($cajaId);
        }

        return [$cajaId, $nombre];
    }

    /**
     * @return array{0:int,1:string}
     */
    public static function desdeConfiguracionPc(?Request $request = null): array
    {
        foreach (self::identificadoresPcCandidatos($request) as $pc) {
            $cajaId = self::cajaIdEnConfigsPorPc($pc);
            if ($cajaId > 0) {
                return self::desdeCajaId($cajaId);
            }
        }

        return [0, ''];
    }

    /**
     * @return list<string>
     */
    public static function identificadoresPcCandidatos(?Request $request = null): array
    {
        $candidatos = [
            EstacionamientoIdentificadorPc::resolver($request),
            GastronomiaIdentificadorPc::resolver($request),
            BingoIdentificadorPc::resolver($request),
        ];

        $out = [];
        foreach ($candidatos as $pc) {
            $pc = trim((string) $pc);
            if ($pc === '' || in_array($pc, $out, true)) {
                continue;
            }
            $out[] = $pc;
        }

        return $out;
    }

    public static function cajaIdEnConfigsPorPc(string $identificadorPc): int
    {
        $pc = trim($identificadorPc);
        if ($pc === '') {
            return 0;
        }

        $tablas = [
            ConfiguracionPuntoventaEstacionamiento::class,
            ConfiguracionPuntoventaGastronomia::class,
            ConfiguracionPuntoventaBingo::class,
        ];

        foreach ($tablas as $modelo) {
            $id = (int) $modelo::query()
                ->where('identificador_pc', $pc)
                ->whereNotNull('caja_id')
                ->where('caja_id', '>', 0)
                ->orderBy('id')
                ->value('caja_id');
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }

    /**
     * @return array{0:int,1:string}
     */
    public static function desdeCajaId(int $cajaId): array
    {
        if ($cajaId <= 0) {
            return [0, ''];
        }

        $caja = Caja::query()->find($cajaId);
        if ($caja === null) {
            return [0, ''];
        }

        return [(int) $caja->id, (string) $caja->nombre];
    }

    /**
     * Oculta "Asignación de Cajas" del aside cuando no se exige en el entorno.
     *
     * @param  array<int, array<string, mixed>>  $menus
     * @return array<int, array<string, mixed>>
     */
    public static function filtrarMenuAside(array $menus): array
    {
        if (self::requiereAsignacion()) {
            return $menus;
        }

        return self::filtrarMenuRecursivo($menus);
    }

    /**
     * @param  array<int, array<string, mixed>>  $menus
     * @return array<int, array<string, mixed>>
     */
    private static function filtrarMenuRecursivo(array $menus): array
    {
        $filtrados = [];

        foreach ($menus as $item) {
            $url = (string) ($item['url'] ?? '');
            if ($url === 'caja/cajaasignacion' || str_starts_with($url, 'caja/cajasignacion')) {
                continue;
            }

            if (! empty($item['submenu']) && is_array($item['submenu'])) {
                $item['submenu'] = self::filtrarMenuRecursivo($item['submenu']);
                if (($item['url'] ?? '') === '#' && $item['submenu'] === []) {
                    continue;
                }
            }

            $filtrados[] = $item;
        }

        return $filtrados;
    }
}
