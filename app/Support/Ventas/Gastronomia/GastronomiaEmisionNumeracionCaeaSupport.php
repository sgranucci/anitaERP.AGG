<?php

namespace App\Support\Ventas\Gastronomia;

use App\Models\Ventas\Tipotransaccion;
use App\Repositories\Ventas\VentaRepository;
use InvalidArgumentException;

/**
 * Reserva rápida de número CAEA (mod A) vía numerador Anita (compemis + update).
 *
 * Sustituye buscaUltimoNumeroComprobante (2 consultas lentas max(ven_nro)) en cada emisión POS.
 * Debe ejecutarse bajo GastronomiaPuntoventaEmisionLock (milisegundos).
 */
final class GastronomiaEmisionNumeracionCaeaSupport
{
    public static function tipoAnitaDesdeTipotransaccion(Tipotransaccion $tipotransaccion): string
    {
        $codigo = (string) ($tipotransaccion->codigo ?? '');

        if ($codigo >= '200') {
            return substr((string) ($tipotransaccion->abreviatura ?? ''), 0, 1).'CE';
        }

        return (string) ($tipotransaccion->abreviatura ?? 'FAC');
    }

    /**
     * Incrementa numerador Anita y devuelve el número reservado.
     *
     * @throws InvalidArgumentException
     */
    public static function reservarNumeroAnita(string $tipoAnita, string $letra, string $puntoventaCodigo): int
    {
        $tipoAnita = trim($tipoAnita);
        $letra = trim($letra);
        $puntoventaCodigo = trim($puntoventaCodigo);

        if ($tipoAnita === '' || $letra === '' || $puntoventaCodigo === '') {
            throw new InvalidArgumentException('Datos incompletos para reservar numeración CAEA.');
        }

        /** @var VentaRepository $repo */
        $repo = app(VentaRepository::class);
        $resultado = $repo->numeraAnita($tipoAnita, $letra, $puntoventaCodigo);

        if (! is_int($resultado) || $resultado <= 0) {
            $detalle = is_string($resultado) ? $resultado : 'respuesta inválida del numerador';

            throw new InvalidArgumentException(
                'No pudo reservar número de comprobante en Anita (PV '.$puntoventaCodigo.'): '.$detalle
            );
        }

        return $resultado;
    }
}
