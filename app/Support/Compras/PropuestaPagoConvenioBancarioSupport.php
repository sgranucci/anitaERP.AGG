<?php

namespace App\Support\Compras;

use App\Models\Compras\LoteBancario;

/**
 * Punto de extensión para layouts bancarios por convenio (cliente / banco).
 * Default: CSV genérico. Drivers a medida se registran en config.
 *
 * Cuando se cierre un convenio, implementar PropuestaPagoConvenioBancarioDriver
 * y setear PROPUESTA_PAGO_CONVENIO_DRIVER=nombre_driver.
 */
interface PropuestaPagoConvenioBancarioDriver
{
    public function codigo(): string;

    public function extension(): string;

    public function mime(): string;

    public function generar(LoteBancario $lote): string;
}

class PropuestaPagoConvenioCsvGenericoDriver implements PropuestaPagoConvenioBancarioDriver
{
    public function codigo(): string
    {
        return 'csv_generico';
    }

    public function extension(): string
    {
        return 'csv';
    }

    public function mime(): string
    {
        return 'text/csv; charset=UTF-8';
    }

    public function generar(LoteBancario $lote): string
    {
        return PropuestaPagoLoteBancarioSupport::contenidoCsv($lote);
    }
}

class PropuestaPagoConvenioBancarioSupport
{
    public static function driverActivo(): PropuestaPagoConvenioBancarioDriver
    {
        $codigo = (string) config('propuesta_pago.convenio_driver', 'csv_generico');
        $map = (array) config('propuesta_pago.convenio_drivers', []);
        $class = $map[$codigo] ?? PropuestaPagoConvenioCsvGenericoDriver::class;
        if (! class_exists($class)) {
            return new PropuestaPagoConvenioCsvGenericoDriver;
        }
        $driver = app($class);
        if (! $driver instanceof PropuestaPagoConvenioBancarioDriver) {
            return new PropuestaPagoConvenioCsvGenericoDriver;
        }

        return $driver;
    }

    public static function exportar(LoteBancario $lote): array
    {
        $driver = self::driverActivo();
        $contenido = $driver->generar($lote);
        $nombre = sprintf(
            'lote_bancario_pp%d_l%d_%s.%s',
            (int) $lote->propuesta_pago_id,
            (int) $lote->id,
            date('Ymd_His'),
            $driver->extension()
        );

        return [
            'contenido' => $contenido,
            'nombre' => $nombre,
            'mime' => $driver->mime(),
            'driver' => $driver->codigo(),
        ];
    }
}
