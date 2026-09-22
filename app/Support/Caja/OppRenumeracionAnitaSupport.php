<?php

namespace App\Support\Caja;

use App\ApiAnita;
use RuntimeException;

/**
 * Cambia el número de una OPP ya replicada en Anita (pago, auxpag, tesmov, promov, aplmovp).
 * No toca certificados de retención ni el numerador de Anita.
 */
final class OppRenumeracionAnitaSupport
{
    public static function renumerar(int $numeroViejo, int $numeroNuevo): void
    {
        if ($numeroViejo <= 0 || $numeroNuevo <= 0 || $numeroViejo === $numeroNuevo) {
            throw new RuntimeException('Numeración OPP inválida.');
        }

        $viejas = self::cantidadPago($numeroViejo);
        $nuevas = self::cantidadPago($numeroNuevo);
        if ($viejas > 1 || $nuevas > 1 || ($viejas === 1 && $nuevas === 1)) {
            throw new RuntimeException(
                'pago OPP en Anita inconsistente al renumerar '.$numeroViejo.' → '.$numeroNuevo
                .' (viejas='.$viejas.', nuevas='.$nuevas.').'
            );
        }
        if ($viejas === 0 && $nuevas === 0) {
            throw new RuntimeException('No está el pago OPP '.$numeroViejo.' en Anita.');
        }

        $leyenda = 'Orden de pago Nro. '.$numeroNuevo;
        if ($viejas === 1) {
            self::actualizar(
            'che_ban',
            'pago',
            'pag_rec = '.$numeroNuevo.', pag_leyenda = '.self::literal($leyenda),
            " WHERE pag_tipo = 'OPP' AND pag_rec = ".$numeroViejo.' AND pag_empresa = 1',
            'pago '.$numeroViejo
            );
        }
        self::actualizar(
            'che_ban',
            'auxpag',
            'axp_rec = '.$numeroNuevo,
            " WHERE axp_tipo = 'OPP' AND axp_rec = ".$numeroViejo,
            'auxpag '.$numeroViejo
        );
        self::actualizar(
            'che_ban',
            'tesmov',
            'tesv_nro = '.$numeroNuevo,
            " WHERE tesv_tipo = 'OPP' AND tesv_nro = ".$numeroViejo,
            'tesmov '.$numeroViejo
        );
        self::actualizar(
            'compras',
            'promov',
            "prov_nro = '".$numeroNuevo."'",
            " WHERE prov_tipo = 'OPP' AND prov_sucursal = '1' AND prov_nro = '".$numeroViejo."'",
            'promov '.$numeroViejo
        );
        self::actualizar(
            'compras',
            'aplmovp',
            "aplvp_nro_cob = '".$numeroNuevo."', aplvp_ref_nro = '".$numeroNuevo."'",
            " WHERE aplvp_tipo_cob = 'OPP' AND aplvp_nro_cob = '".$numeroViejo."'",
            'aplmovp '.$numeroViejo
        );

        if (self::cantidadPago($numeroNuevo) !== 1 || self::cantidadPago($numeroViejo) !== 0) {
            throw new RuntimeException(
                'Después de renumerar, el pago OPP '.$numeroNuevo.' no quedó único en Anita.'
            );
        }
    }

    private static function cantidadPago(int $numero): int
    {
        $raw = (new ApiAnita)->apiCallEscritura([
            'acc' => 'list',
            'sistema' => 'che_ban',
            'tabla' => 'pago',
            'campos' => 'pag_tipo,pag_rec,pag_empresa',
            'whereArmado' => " WHERE pag_tipo = 'OPP' AND pag_rec = ".$numero.' AND pag_empresa = 1',
        ], 'opp renumerar lectura pago '.$numero);

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new RuntimeException('No se pudo leer pago OPP '.$numero.' en Anita: '.$err);
        }

        $filas = json_decode((string) $raw, true);

        return is_array($filas) ? count($filas) : 0;
    }

    private static function actualizar(
        string $sistema,
        string $tabla,
        string $valores,
        string $where,
        string $contexto,
    ): void {
        $raw = (new ApiAnita)->apiCallEscritura([
            'acc' => 'update',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'valores' => $valores,
            'whereArmado' => $where,
        ], 'opp renumerar '.$contexto);

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new RuntimeException('No se pudo actualizar '.$contexto.' en Anita: '.$err);
        }
    }

    private static function literal(string $valor): string
    {
        return "'".str_replace("'", "''", $valor)."'";
    }
}
