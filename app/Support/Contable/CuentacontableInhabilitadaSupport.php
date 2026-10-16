<?php

namespace App\Support\Contable;

use App\Models\Contable\Cuentacontable;
use InvalidArgumentException;

/**
 * Una cuenta inhabilitada sigue en el plan y en el mayor.
 * No se puede elegir ni tipear en una imputación nueva.
 */
final class CuentacontableInhabilitadaSupport
{
    public static function estaInhabilitada(mixed $cuenta): bool
    {
        if ($cuenta === null) {
            return false;
        }

        return (bool) (is_object($cuenta) ? ($cuenta->inhabilitada ?? false) : ($cuenta['inhabilitada'] ?? false));
    }

    public static function mensaje(Cuentacontable $cuenta): string
    {
        $codigo = CuentacontableArbolSupport::formatearCodigo((string) $cuenta->codigo);

        return 'La cuenta '.$codigo.' '.$cuenta->nombre.' está inhabilitada. No se puede imputar.';
    }

    /**
     * @param  list<int|string|null>  $idsNuevos
     * @param  list<int|string|null>  $idsYaEnElAsiento  se pueden conservar al editar
     */
    public static function assertImputacionNueva(array $idsNuevos, array $idsYaEnElAsiento = []): void
    {
        $nuevos = self::idsPositivos($idsNuevos);
        if ($nuevos === []) {
            return;
        }

        $permitidos = array_flip(self::idsPositivos($idsYaEnElAsiento));
        $inhabilitadas = Cuentacontable::query()
            ->whereIn('id', $nuevos)
            ->where('inhabilitada', true)
            ->orderBy('codigo')
            ->get();

        $mensajes = [];
        foreach ($inhabilitadas as $cuenta) {
            if (isset($permitidos[(int) $cuenta->id])) {
                continue;
            }
            $mensajes[] = self::mensaje($cuenta);
        }

        if ($mensajes !== []) {
            throw new InvalidArgumentException(implode(' ', $mensajes));
        }
    }

    /**
     * @param  list<int|string|null>  $ids
     * @return list<int>
     */
    private static function idsPositivos(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $ids),
            static fn (int $id) => $id > 0
        )));
    }
}
