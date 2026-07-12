<?php

namespace App\Support\Seguridad;

use App\Models\Seguridad\Usuario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Reglas únicas para elegir/consultar usuarios en procesos operativos (modales, árboles, avisos, etc.).
 *
 * Cambios de filtro (suspendidos, empresa, búsqueda) deben hacerse solo aquí.
 */
final class UsuarioOperativoSupport
{
    /** @var list<string> */
    private const COLUMNAS_TEXTO_CONSULTA = [
        'usuario.id',
        'usuario.usuario',
        'usuario.nombre',
        'usuario.email',
        'centrocosto.nombre',
        'sector_legajocompra.nombre',
    ];

    public static function query(): Builder
    {
        return Usuario::query()->soloActivos();
    }

    public static function esOperativo(?Usuario $usuario): bool
    {
        return $usuario !== null && ! $usuario->estaSuspendido();
    }

    public static function normalizarEmpresaId(mixed $empresaId): ?int
    {
        if ($empresaId === null || $empresaId === '') {
            return null;
        }

        $id = (int) $empresaId;

        return $id > 0 ? $id : null;
    }

    /**
     * @param  list<int>  $usuarioIds
     * @return list<int>
     */
    public static function normalizarIds(array $usuarioIds): array
    {
        return array_values(array_unique(array_filter(
            array_map('intval', $usuarioIds),
            static fn (int $id) => $id > 0
        )));
    }

    /**
     * @param  list<int>  $usuarioIds
     * @return list<int>
     */
    public static function filtrarIdsActivos(array $usuarioIds): array
    {
        $ids = self::normalizarIds($usuarioIds);
        if ($ids === []) {
            return [];
        }

        $activos = self::query()
            ->whereIn('usuario.id', $ids)
            ->pluck('usuario.id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        return array_values(array_filter(
            $ids,
            static fn (int $id) => in_array($id, $activos, true)
        ));
    }

    /**
     * Usuario sin filas en usuario_empresa aplica a todas las empresas.
     *
     * @param  list<int>  $usuarioIds
     * @return list<int>
     */
    public static function filtrarIdsPorEmpresa(array $usuarioIds, int $empresaId): array
    {
        $usuarioIds = self::normalizarIds($usuarioIds);
        if ($empresaId <= 0 || $usuarioIds === []) {
            return $usuarioIds;
        }

        $asignaciones = DB::table('usuario_empresa')
            ->whereIn('usuario_id', $usuarioIds)
            ->get(['usuario_id', 'empresa_id'])
            ->groupBy('usuario_id');

        $filtrados = [];
        foreach ($usuarioIds as $uid) {
            if (! isset($asignaciones[$uid]) || $asignaciones[$uid]->isEmpty()) {
                $filtrados[] = $uid;

                continue;
            }
            foreach ($asignaciones[$uid] as $row) {
                if ((int) $row->empresa_id === $empresaId) {
                    $filtrados[] = $uid;
                    break;
                }
            }
        }

        return array_values(array_unique($filtrados));
    }

    /**
     * @param  list<int>  $usuarioIds
     * @return list<int>
     */
    public static function filtrarIdsOperativosPorEmpresa(array $usuarioIds, int $empresaId): array
    {
        return self::filtrarIdsPorEmpresa(
            self::filtrarIdsActivos($usuarioIds),
            $empresaId
        );
    }

    public static function perteneceAEmpresa(Usuario $usuario, int $empresaId): bool
    {
        if ($empresaId <= 0) {
            return true;
        }

        if (! $usuario->relationLoaded('usuario_empresas')) {
            $usuario->load('usuario_empresas:id');
        }

        if ($usuario->usuario_empresas->isEmpty()) {
            return true;
        }

        return $usuario->usuario_empresas->contains('id', $empresaId);
    }

    public static function validoParaEmpresa(Usuario $usuario, ?int $empresaId, bool $omitirFiltroEmpresa = false): bool
    {
        if ($omitirFiltroEmpresa || $empresaId === null || $empresaId <= 0) {
            return true;
        }

        return self::perteneceAEmpresa($usuario, $empresaId);
    }

    public static function find(int $id): ?Usuario
    {
        if ($id <= 0) {
            return null;
        }

        return self::query()
            ->with('usuario_empresas:id,nombre')
            ->whereKey($id)
            ->first();
    }

    public static function findPorIdOCodigo(string $valor, ?int $empresaId = null): ?Usuario
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }

        $q = self::query()->with('usuario_empresas:id,nombre');

        if (preg_match('/^\d+$/', $valor)) {
            $q->where('usuario.id', (int) $valor);
        } else {
            $q->whereRaw('UPPER(TRIM(usuario.usuario)) = ?', [Str::upper($valor)]);
        }

        self::aplicarFiltroEmpresa($q, $empresaId);

        return $q->first();
    }

    public static function queryConsulta(?int $empresaId = null, ?int $centrocostoId = null): Builder
    {
        $query = self::query()
            ->select(
                'usuario.id as id',
                'usuario.usuario as usuariologin',
                'usuario.nombre as nombre',
                'usuario.email as email',
                'usuario.centrocosto_id as idcentrocosto',
                'centrocosto.nombre as nombrecentrocosto',
                'sector_legajocompra.nombre as nombresectorlegajocompra'
            )
            ->leftJoin('centrocosto', 'centrocosto.id', '=', 'usuario.centrocosto_id')
            ->leftJoin('sector_legajocompra', 'sector_legajocompra.id', '=', 'usuario.sector_legajocompra_id')
            ->with(['usuario_empresas:id,nombre']);

        self::aplicarFiltroEmpresa($query, $empresaId);

        if ($centrocostoId !== null && $centrocostoId > 0) {
            $query->where('usuario.centrocosto_id', $centrocostoId);
        }

        return $query;
    }

    public static function aplicarFiltroEmpresa(Builder $query, ?int $empresaId): void
    {
        if ($empresaId === null || $empresaId <= 0) {
            return;
        }

        $query->whereHas('usuario_empresas', function ($q) use ($empresaId) {
            $q->where('empresa_id', $empresaId);
        });
    }

    public static function aplicarFiltroTextoConsulta(Builder $query, string $consulta): void
    {
        $consulta = strtoupper(trim($consulta));
        if ($consulta === '') {
            return;
        }

        $query->where(function ($q) use ($consulta) {
            foreach (self::COLUMNAS_TEXTO_CONSULTA as $columna) {
                $q->orWhere($columna, 'LIKE', '%'.$consulta.'%');
            }
            $q->orWhereHas('usuario_empresas', function ($sub) use ($consulta) {
                $sub->whereRaw('UPPER(empresa.nombre) LIKE ?', ['%'.$consulta.'%']);
            });
        });
    }

    /**
     * Listado para selects / ABM que eligen un usuario operativo.
     *
     * @param  list<string>  $columnas
     */
    public static function listadoParaSelector(
        ?int $empresaId = null,
        ?int $centrocostoId = null,
        array $columnas = ['id', 'nombre', 'email', 'usuario'],
        bool $soloConEmail = false,
        array $with = [],
    ): Collection {
        $query = self::query()->orderBy('nombre');

        if ($with !== []) {
            $query->with($with);
        }

        if ($soloConEmail) {
            $query->whereNotNull('email')->where('email', '!=', '');
        }

        self::aplicarFiltroEmpresa($query, $empresaId);

        if ($centrocostoId !== null && $centrocostoId > 0) {
            $query->where('centrocosto_id', $centrocostoId);
        }

        return $query->get($columnas);
    }

    public static function etiquetaEmpresasUsuario(Usuario $usuario): string
    {
        if (! $usuario->relationLoaded('usuario_empresas')) {
            $usuario->load('usuario_empresas:id,nombre');
        }

        $texto = $usuario->usuario_empresas
            ->pluck('nombre')
            ->filter(static fn ($nombre) => trim((string) $nombre) !== '')
            ->unique()
            ->implode(', ');

        return $texto !== '' ? $texto : '—';
    }
}
