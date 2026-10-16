<?php

namespace App\Support\Stock;

/**
 * Estado de un artículo en una sala a partir de los envíos ya registrados.
 * Un alta exitosa no bloquea otro alta: el mismo externalId actualiza el producto.
 *
 * @phpstan-type Intento array{operacion:string,ok:bool,mensaje?:string}
 */
final class ArticuloWaitryEstadoSupport
{
    public const ALTA = 'alta';

    public const BAJA = 'baja';

    /**
     * @param  list<Intento>  $intentos  orden cronológico
     * @return array{vigente:?string,puede_alta:bool,puede_baja:bool,ultimo_error:?string,estado:string}
     */
    public static function decidir(array $intentos): array
    {
        $vigente = null;
        $ultimoError = null;

        foreach ($intentos as $intento) {
            $operacion = (string) ($intento['operacion'] ?? '');
            $ok = (bool) ($intento['ok'] ?? false);
            if ($ok && ($operacion === self::ALTA || $operacion === self::BAJA)) {
                $vigente = $operacion;
                $ultimoError = null;

                continue;
            }

            if (! $ok) {
                $mensaje = trim((string) ($intento['mensaje'] ?? ''));
                $ultimoError = $mensaje !== '' ? $mensaje : 'Error al comunicar con Waitry.';
            }
        }

        return [
            'vigente' => $vigente,
            'puede_alta' => true,
            'puede_baja' => $vigente === self::ALTA,
            'ultimo_error' => $ultimoError,
            'estado' => self::texto($vigente, $ultimoError),
        ];
    }

    private static function texto(?string $vigente, ?string $ultimoError): string
    {
        if ($vigente === self::ALTA && $ultimoError === null) {
            return 'En Waitry. Un nuevo envío actualiza el producto.';
        }

        if ($vigente === self::ALTA) {
            return 'En Waitry. La última suspensión falló: '.$ultimoError;
        }

        if ($vigente === self::BAJA && $ultimoError === null) {
            return 'Suspendido hasta 2040. Un alta lo vuelve a publicar.';
        }

        if ($vigente === self::BAJA) {
            return 'Suspendido. La última alta falló: '.$ultimoError;
        }

        if ($ultimoError !== null) {
            return 'Error: '.$ultimoError;
        }

        return 'Sin envío';
    }
}
