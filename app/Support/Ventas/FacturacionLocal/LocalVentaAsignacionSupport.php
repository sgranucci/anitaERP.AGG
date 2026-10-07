<?php

namespace App\Support\Ventas\FacturacionLocal;

use App\Models\Ventas\LocalVenta;
use App\Models\Ventas\TurnoLocal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Usuarios y turnos de caja asignados a un local de venta.
 *
 * Un usuario sin filas en local_venta_usuario opera todos los locales (administración).
 * Con al menos una fila, el POS, la apertura de caja y los listados operativos
 * quedan limitados a esos locales.
 *
 * Un local sin filas en local_venta_turno acepta cualquier turno activo de su empresa.
 * Con filas, solo se puede abrir uno de esos turnos.
 */
final class LocalVentaAsignacionSupport
{
    public static function usuarioIdActual(?int $usuarioId = null): int
    {
        if ($usuarioId !== null && $usuarioId > 0) {
            return $usuarioId;
        }

        return (int) Auth::id();
    }

    /**
     * null = el usuario no está atado a ningún local.
     *
     * @return list<int>|null
     */
    public static function idsOperables(?int $usuarioId = null): ?array
    {
        $usuarioId = self::usuarioIdActual($usuarioId);
        if ($usuarioId <= 0 || ! self::tablaUsuarios()) {
            return null;
        }

        $ids = DB::table('local_venta_usuario')
            ->where('usuario_id', $usuarioId)
            ->orderBy('orden')
            ->orderBy('local_venta_id')
            ->pluck('local_venta_id')
            ->map(static fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        return $ids === [] ? null : $ids;
    }

    public static function puedeOperarLocal(int $localId, ?int $usuarioId = null): bool
    {
        if ($localId <= 0) {
            return false;
        }

        $ids = self::idsOperables($usuarioId);

        return $ids === null || in_array($localId, $ids, true);
    }

    public static function assertPuedeOperarLocal(int $localId, ?int $usuarioId = null): void
    {
        if (! self::puedeOperarLocal($localId, $usuarioId)) {
            throw new InvalidArgumentException('Este usuario no puede operar ese local.');
        }
    }

    /**
     * @return Collection<int, LocalVenta>
     */
    public static function localesParaUsuario(?int $usuarioId = null, bool $soloActivos = true): Collection
    {
        $query = LocalVenta::query()->orderBy('codigo');
        if ($soloActivos) {
            $query->where('activo', true);
        }

        $ids = self::idsOperables($usuarioId);
        if ($ids !== null) {
            $query->whereIn('id', $ids !== [] ? $ids : [0]);
        }

        return $query->get();
    }

    public static function localTieneTurnosAsignados(int $localId): bool
    {
        if ($localId <= 0 || ! self::tablaTurnos()) {
            return false;
        }

        return DB::table('local_venta_turno')->where('local_venta_id', $localId)->exists();
    }

    /**
     * Turnos que se pueden abrir en el local. Si el local no tiene ninguno
     * asignado, devuelve los activos de la empresa.
     *
     * @return Collection<int, TurnoLocal>
     */
    public static function turnosParaLocal(LocalVenta $local): Collection
    {
        $query = TurnoLocal::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre');

        $empresaId = (int) ($local->empresa_id ?? 0);
        if ($empresaId > 0) {
            $query->where('empresa_id', $empresaId);
        }

        if (self::localTieneTurnosAsignados((int) $local->id)) {
            $query->whereHas('locales', fn ($q) => $q->where('local_venta.id', (int) $local->id));
        }

        return $query->get();
    }

    public static function assertTurnoDelLocal(LocalVenta $local, int $turnoLocalId): void
    {
        if ($turnoLocalId <= 0) {
            throw new InvalidArgumentException('Debe elegir un turno.');
        }

        if (! self::localTieneTurnosAsignados((int) $local->id)) {
            return;
        }

        $permitido = self::turnosParaLocal($local)->contains('id', $turnoLocalId);
        if (! $permitido) {
            throw new InvalidArgumentException('Ese turno no corresponde a este local.');
        }
    }

    private static function tablaUsuarios(): bool
    {
        return DB::getSchemaBuilder()->hasTable('local_venta_usuario');
    }

    private static function tablaTurnos(): bool
    {
        return DB::getSchemaBuilder()->hasTable('local_venta_turno');
    }
}
