<?php

namespace App\Support\Ventas\Vianda;

use App\Models\Sueldos\Empleado_Sueldos;
use App\Support\Sueldos\EmpleadoEstados;

/**
 * Centro de costo del usuario de vianda a partir del legajo de sueldos.
 *
 * El código de usuario de vianda (Anita usuv_usuario) es el documento del empleado.
 * Cuando Anita no trae usuv_ccosto, el centro de costo se toma de empleado_sueldos
 * para no dejar el consumo sin imputar.
 *
 * El documento en sueldos puede venir con prefijo de tipo ("DU  3439520", "LC  4716704"),
 * por eso la comparación se hace sobre los dígitos.
 */
final class ViandaCentrocostoEmpleadoSupport
{
    /**
     * Centro de costo del empleado cuyo documento coincide con el código de usuario.
     *
     * Prioridad: empleado de la misma empresa del usuario de vianda, luego cualquier
     * otra empresa del grupo (la persona liquida en una sola). Dentro de cada grupo,
     * primero los activos y, entre ellos, el de ingreso más reciente.
     */
    public static function centrocostoIdPorDocumento(int|string|null $documento, ?int $empresaId = null): ?int
    {
        $empleado = self::empleadoPorDocumento($documento, $empresaId);

        return $empleado !== null ? (int) $empleado->centrocosto_id : null;
    }

    public static function empleadoPorDocumento(int|string|null $documento, ?int $empresaId = null): ?Empleado_Sueldos
    {
        $buscado = self::soloDigitos($documento);
        if ($buscado === '') {
            return null;
        }

        $candidatos = Empleado_Sueldos::query()
            ->whereNotNull('centrocosto_id')
            ->where(function ($q) use ($buscado) {
                // El documento se guarda numérico o con prefijo de tipo; el LIKE acota y
                // la comparación final por dígitos evita falsos positivos (9123 vs 123).
                $q->where('documento', $buscado)
                    ->orWhere('documento', 'like', '%'.$buscado);
            })
            ->get(['id', 'empresa_id', 'legajo', 'nombre', 'documento', 'centrocosto_id', 'estado', 'fecha_ingreso']);

        $candidatos = $candidatos
            ->filter(fn ($e) => self::soloDigitos($e->documento) === $buscado)
            ->filter(fn ($e) => (int) ($e->centrocosto_id ?? 0) > 0);

        if ($candidatos->isEmpty()) {
            return null;
        }

        $ordenados = $candidatos->sortBy([
            fn ($e) => $empresaId !== null && (int) $e->empresa_id === $empresaId ? 0 : 1,
            fn ($e) => EmpleadoEstados::esActivo($e->estado) ? 0 : 1,
            fn ($e) => -strtotime((string) ($e->fecha_ingreso ?? '1900-01-01')),
        ]);

        return $ordenados->first();
    }

    private static function soloDigitos(int|string|null $valor): string
    {
        return ltrim((string) preg_replace('/\D/', '', (string) $valor), '0');
    }
}
