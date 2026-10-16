<?php

namespace App\Support\Seguridad;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\IngresoProveedorArea;
use App\Models\Seguridad\IngresoProveedorAreaCorreo;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Correos de aviso al generar un ingreso, según área de destino y empresa del ticket.
 */
final class IngresoProveedorAreaCorreoSupport
{
    /**
     * Clave de área (nombre normalizado) → empresa (BIYEMAS|KANDIKO|REBISCO) → correos.
     *
     * @var array<string, array<string, list<string>>>
     */
    public const MATRIZ_AGG = [
        'mantenimiento' => [
            'BIYEMAS' => [
                'mantenimiento.b@grupoagg.com',
                'maquinas.biy@grupoagg.com',
            ],
            'KANDIKO' => [
                'mantenimiento.k@grupoagg.com',
                'maquinas.kan@grupoagg.com',
            ],
            'REBISCO' => [
                'mantenimiento.r@grupoagg.com',
                'maquinas.reb@grupoagg.com',
            ],
        ],
        'sala' => [
            'BIYEMAS' => [
                'maquinas.biy@grupoagg.com',
                'mantenimiento.b@grupoagg.com',
            ],
            'KANDIKO' => [
                'maquinas.kan@grupoagg.com',
                'mantenimiento.k@grupoagg.com',
            ],
            'REBISCO' => [
                'maquinas.reb@grupoagg.com',
                'mantenimiento.r@grupoagg.com',
            ],
        ],
        'gastronomia' => [
            'BIYEMAS' => [
                'maquinas.biy@grupoagg.com',
                'mantenimiento.b@grupoagg.com',
                'gastronomia.biy@grupoagg.com',
                'ddominguez@grupoagg.com',
            ],
            'KANDIKO' => [
                'maquinas.kan@grupoagg.com',
                'mantenimiento.k@grupoagg.com',
                'gastronomia.kan@grupoagg.com',
                'mmoskaluc@grupoagg.com',
            ],
            'REBISCO' => [
                'maquinas.reb@grupoagg.com',
                'mantenimiento.r@grupoagg.com',
                'gastronomia.reb@grupoagg.com',
                'wchavez@grupoagg.com',
            ],
        ],
        'tesoreria' => [
            'BIYEMAS' => [
                'maquinas.biy@grupoagg.com',
                'mantenimiento.b@grupoagg.com',
                'gmagliolo@grupoagg.com',
                'bcanosa@grupoagg.com',
                'tesoreria.biy@grupoagg.com',
            ],
            'KANDIKO' => [
                'maquinas.kan@grupoagg.com',
                'mantenimiento.k@grupoagg.com',
                'gmagliolo@grupoagg.com',
                'tesoreria.kan@grupoagg.com',
            ],
            'REBISCO' => [
                'maquinas.reb@grupoagg.com',
                'mantenimiento.r@grupoagg.com',
                'gmagliolo@grupoagg.com',
                'tesoreria.reb@grupoagg.com',
            ],
        ],
    ];

    /**
     * @return Collection<int, Empresa>
     */
    public static function empresasOperativas(): Collection
    {
        return Empresa::query()
            ->where(function ($query) {
                $query->where('nombre', 'like', 'BIYEMAS%')
                    ->orWhere('nombre', 'like', 'KANDIKO%')
                    ->orWhere('nombre', 'like', 'REBISCO%');
            })
            ->where('nombre', 'not like', 'BUDGET%')
            ->where('nombre', 'not like', '%TEMPORAL%')
            ->orderBy('id')
            ->get(['id', 'nombre']);
    }

    public static function claveArea(string $nombre): ?string
    {
        $normalizado = trim(Str::upper(Str::ascii($nombre)));

        return match ($normalizado) {
            'MANTENIMIENTO' => 'mantenimiento',
            'GASTRONOMIA' => 'gastronomia',
            'TESORERIA' => 'tesoreria',
            'SALA' => 'sala',
            default => null,
        };
    }

    public static function claveEmpresa(string $nombre): ?string
    {
        $normalizado = Str::upper(Str::ascii($nombre));
        if (str_starts_with($normalizado, 'BUDGET') || str_contains($normalizado, 'TEMPORAL')) {
            return null;
        }
        if (str_starts_with($normalizado, 'BIYEMAS')) {
            return 'BIYEMAS';
        }
        if (str_starts_with($normalizado, 'KANDIKO')) {
            return 'KANDIKO';
        }
        if (str_starts_with($normalizado, 'REBISCO')) {
            return 'REBISCO';
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function emailsDe(int $areaId, int $empresaId): array
    {
        if ($areaId <= 0 || $empresaId <= 0) {
            return [];
        }

        return IngresoProveedorAreaCorreo::query()
            ->where('area_id', $areaId)
            ->where('empresa_id', $empresaId)
            ->orderBy('email')
            ->pluck('email')
            ->map(static fn ($email) => strtolower(trim((string) $email)))
            ->filter(static fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<int, string> empresa_id => texto (un correo por línea)
     */
    public static function textoPorEmpresa(int $areaId): array
    {
        $grupos = IngresoProveedorAreaCorreo::query()
            ->where('area_id', $areaId)
            ->orderBy('email')
            ->get(['empresa_id', 'email'])
            ->groupBy('empresa_id');

        $texto = [];
        foreach ($grupos as $empresaId => $filas) {
            $texto[(int) $empresaId] = $filas->pluck('email')->implode("\n");
        }

        return $texto;
    }

    /**
     * @param  array<int|string, mixed>  $correosPorEmpresa
     * @param  list<int>  $empresaIdsAlcance
     */
    public static function sincronizar(int $areaId, array $correosPorEmpresa, array $empresaIdsAlcance): void
    {
        $alcance = array_values(array_unique(array_map('intval', $empresaIdsAlcance)));
        $deseados = [];
        foreach ($correosPorEmpresa as $empresaId => $texto) {
            $empresaId = (int) $empresaId;
            if (! in_array($empresaId, $alcance, true)) {
                continue;
            }
            foreach (self::lineas($texto) as $email) {
                $deseados[$empresaId.'|'.$email] = [$empresaId, $email];
            }
        }

        $actuales = IngresoProveedorAreaCorreo::query()
            ->where('area_id', $areaId)
            ->whereIn('empresa_id', $alcance === [] ? [0] : $alcance)
            ->get();

        $claves = [];
        foreach ($actuales as $fila) {
            $clave = (int) $fila->empresa_id.'|'.strtolower(trim((string) $fila->email));
            $claves[$clave] = $fila;
            if (! isset($deseados[$clave])) {
                $fila->delete();
            }
        }

        foreach ($deseados as $clave => [$empresaId, $email]) {
            if (isset($claves[$clave])) {
                continue;
            }
            IngresoProveedorAreaCorreo::query()->create([
                'area_id' => $areaId,
                'empresa_id' => $empresaId,
                'email' => $email,
            ]);
        }
    }

    /**
     * Alta inicial en AGG. No pisa correos que ya existan.
     */
    public static function sembrarCanonicoAgg(): int
    {
        if (! EntornoEmpresaSupport::esAgg() || ! DB::getSchemaBuilder()->hasTable('ingreso_proveedor_area_correo')) {
            return 0;
        }

        self::asegurarAreaSala();

        $empresas = [];
        foreach (self::empresasOperativas() as $empresa) {
            $clave = self::claveEmpresa((string) $empresa->nombre);
            if ($clave !== null) {
                $empresas[$clave] = (int) $empresa->id;
            }
        }

        $insertados = 0;
        foreach (IngresoProveedorArea::query()->get(['id', 'nombre']) as $area) {
            $claveArea = self::claveArea((string) $area->nombre);
            if ($claveArea === null || ! isset(self::MATRIZ_AGG[$claveArea])) {
                continue;
            }
            foreach (self::MATRIZ_AGG[$claveArea] as $claveEmpresa => $emails) {
                $empresaId = $empresas[$claveEmpresa] ?? 0;
                if ($empresaId <= 0) {
                    continue;
                }
                foreach ($emails as $email) {
                    $email = strtolower(trim($email));
                    $existe = IngresoProveedorAreaCorreo::query()
                        ->where('area_id', $area->id)
                        ->where('empresa_id', $empresaId)
                        ->where('email', $email)
                        ->exists();
                    if ($existe) {
                        continue;
                    }
                    IngresoProveedorAreaCorreo::query()->create([
                        'area_id' => $area->id,
                        'empresa_id' => $empresaId,
                        'email' => $email,
                    ]);
                    $insertados++;
                }
            }
        }

        return $insertados;
    }

    private static function asegurarAreaSala(): void
    {
        $existe = IngresoProveedorArea::query()->get(['nombre'])
            ->contains(static fn (IngresoProveedorArea $area) => self::claveArea((string) $area->nombre) === 'sala');
        if ($existe) {
            return;
        }

        IngresoProveedorArea::query()->create([
            'codigo' => 'SALA',
            'nombre' => 'Sala',
            'activo' => true,
        ]);
    }

    /**
     * Quita solo las filas de la matriz canónica.
     */
    public static function quitarCanonicoAgg(): void
    {
        if (! EntornoEmpresaSupport::esAgg() || ! DB::getSchemaBuilder()->hasTable('ingreso_proveedor_area_correo')) {
            return;
        }

        $empresas = [];
        foreach (self::empresasOperativas() as $empresa) {
            $clave = self::claveEmpresa((string) $empresa->nombre);
            if ($clave !== null) {
                $empresas[$clave] = (int) $empresa->id;
            }
        }

        foreach (IngresoProveedorArea::query()->get(['id', 'nombre']) as $area) {
            $claveArea = self::claveArea((string) $area->nombre);
            if ($claveArea === null || ! isset(self::MATRIZ_AGG[$claveArea])) {
                continue;
            }
            foreach (self::MATRIZ_AGG[$claveArea] as $claveEmpresa => $emails) {
                $empresaId = $empresas[$claveEmpresa] ?? 0;
                if ($empresaId <= 0) {
                    continue;
                }
                IngresoProveedorAreaCorreo::query()
                    ->where('area_id', $area->id)
                    ->where('empresa_id', $empresaId)
                    ->whereIn('email', array_map('strtolower', $emails))
                    ->get()
                    ->each(static fn (IngresoProveedorAreaCorreo $fila) => $fila->delete());
            }
        }
    }

    /**
     * @return list<string>
     */
    public static function lineas(mixed $texto): array
    {
        $partes = preg_split('/[\s,;]+/', (string) $texto) ?: [];
        $emails = [];
        foreach ($partes as $parte) {
            $email = strtolower(trim($parte));
            if ($email === '') {
                continue;
            }
            $emails[] = $email;
        }

        return array_values(array_unique($emails));
    }
}
