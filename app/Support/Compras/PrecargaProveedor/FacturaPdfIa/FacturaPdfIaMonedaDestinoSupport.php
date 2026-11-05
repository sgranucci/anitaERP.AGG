<?php

namespace App\Support\Compras\PrecargaProveedor\FacturaPdfIa;

use App\Models\Configuracion\Moneda;
use App\Support\Compras\ComprobanteProveedorMonedaMotor;
use App\Support\Database\SqlDialectSupport;
use Illuminate\Support\Collection;

/**
 * Moneda opcional al cargar una factura con IA.
 *
 * Si el operador indica un código, pisa la moneda leída del PDF.
 * Si no es la misma, los importes se convierten con la cotización de la factura
 * (pesos por unidad de moneda extranjera). Si el PDF no la trae, se usa la
 * cargada a mano. Sin ninguna de las dos, no se carga la factura.
 */
final class FacturaPdfIaMonedaDestinoSupport
{
    /**
     * @param  array<string, mixed>  $resuelto
     * @return array<string, mixed>
     */
    public static function aplicar(array $resuelto, ?string $codigoMoneda, mixed $cotizacionManual): array
    {
        $codigo = self::normalizarCodigo($codigoMoneda);
        if ($codigo === '') {
            return $resuelto;
        }

        $destino = self::buscar($codigo);
        if ($destino === null) {
            throw new FacturaPdfIaMonedaDestinoException(
                'No existe la moneda con código «'.$codigo.'».'
            );
        }

        $origenId = (int) ($resuelto['moneda_id'] ?? 0);
        if ($origenId <= 0) {
            $origenId = 1;
        }
        $destinoId = (int) $destino->id;
        $nombreDestino = self::etiqueta($destino);
        $codigoDestino = self::codigoVisible($destino);

        if ($origenId === $destinoId) {
            $resuelto['moneda'] = $nombreDestino;
            $resuelto['moneda_id'] = $destinoId;
            $resuelto['moneda_codigo'] = $codigoDestino;

            return $resuelto;
        }

        $origenExtranjera = ComprobanteProveedorMonedaMotor::esMonedaExtranjera($origenId);
        $destinoExtranjera = ComprobanteProveedorMonedaMotor::esMonedaExtranjera($destinoId);
        $nombreOrigen = trim((string) ($resuelto['moneda'] ?? ''));
        if ($nombreOrigen === '') {
            $nombreOrigen = 'la moneda del PDF';
        }

        if ($origenExtranjera && $destinoExtranjera) {
            throw new FacturaPdfIaMonedaDestinoException(
                'La factura está en '.$nombreOrigen.' y se eligió '.$nombreDestino
                .' ('.$codigoDestino.'). Con la cotización de la factura solo se puede pasar'
                .' entre esa moneda y pesos. Elija pesos o deje el código de moneda vacío.'
            );
        }

        $cotFactura = (float) ($resuelto['cotizacion_recibida'] ?? 0);
        $cotManual = self::parsearCotizacion($cotizacionManual);
        $cot = 0.0;
        $origenCot = '';
        if ($cotFactura > ComprobanteProveedorMonedaMotor::COTIZACION_MINIMA) {
            $cot = $cotFactura;
            $origenCot = 'factura';
        } elseif ($cotManual > ComprobanteProveedorMonedaMotor::COTIZACION_MINIMA) {
            $cot = $cotManual;
            $origenCot = 'manual';
        }

        if ($cot <= ComprobanteProveedorMonedaMotor::COTIZACION_MINIMA) {
            throw new FacturaPdfIaMonedaDestinoException(
                'La factura está en '.$nombreOrigen.' y se eligió '.$nombreDestino
                .' ('.$codigoDestino.'), pero el PDF no trae cotización. '
                .'Indique la cotización para convertir los importes. Sin cotización no se carga la factura.',
                true
            );
        }

        $factor = $origenExtranjera ? $cot : (1 / $cot);
        $resuelto['subtotal'] = self::escalar((float) ($resuelto['subtotal'] ?? 0), $factor, 2);
        $resuelto['total'] = self::escalar((float) ($resuelto['total'] ?? 0), $factor, 2);

        $conceptos = [];
        foreach (is_array($resuelto['conceptos_asignados'] ?? null) ? $resuelto['conceptos_asignados'] : [] as $linea) {
            if (! is_array($linea)) {
                $conceptos[] = $linea;
                continue;
            }
            $linea['importe'] = self::escalar((float) ($linea['importe'] ?? 0), $factor, 2);
            $conceptos[] = $linea;
        }
        $resuelto['conceptos_asignados'] = $conceptos;

        $articulos = [];
        foreach (is_array($resuelto['articulos'] ?? null) ? $resuelto['articulos'] : [] as $linea) {
            if (! is_array($linea)) {
                $articulos[] = $linea;
                continue;
            }
            $linea['precio_unitario'] = self::escalar((float) ($linea['precio_unitario'] ?? 0), $factor, 6);
            $articulos[] = $linea;
        }
        $resuelto['articulos'] = $articulos;

        $resuelto['moneda'] = $nombreDestino;
        $resuelto['moneda_id'] = $destinoId;
        $resuelto['moneda_codigo'] = $codigoDestino;
        $resuelto['moneda_origen'] = $nombreOrigen;
        $resuelto['cotizacion_recibida'] = $cot;
        $resuelto['cotizacion'] = $cot;
        $resuelto['cotizacion_conversion_origen'] = $origenCot;
        $resuelto['moneda_destino_aplicada'] = true;

        $advertencias = is_array($resuelto['advertencias'] ?? null) ? $resuelto['advertencias'] : [];
        $textoCot = number_format($cot, 2, ',', '.');
        $deDonde = $origenCot === 'manual'
            ? 'indicada a mano (el PDF no traía tipo de cambio)'
            : 'de la factura';
        $advertencias[] = 'La factura está en '.$nombreOrigen
            .'. Se pasó a '.$nombreDestino.' ('.$codigoDestino.') con la cotización '
            .$textoCot.' '.$deDonde.'. Los importes quedaron en esa moneda.';
        $resuelto['advertencias'] = $advertencias;

        return $resuelto;
    }

    /**
     * @return Collection<int, Moneda>
     */
    public static function listarParaConsulta(?string $texto): Collection
    {
        $consulta = self::normalizarCodigo($texto);
        $query = Moneda::query()->orderBy('id');
        if ($consulta === '') {
            return $query->get();
        }

        $like = '%'.addcslashes(mb_strtolower($consulta), '%_\\').'%';

        return $query
            ->where(function ($q) use ($like) {
                $q->whereRaw(SqlDialectSupport::lower('codigo').' LIKE ?', [$like])
                    ->orWhereRaw(SqlDialectSupport::lower('abreviatura').' LIKE ?', [$like])
                    ->orWhereRaw(SqlDialectSupport::lower('nombre').' LIKE ?', [$like]);
            })
            ->get();
    }

    public static function buscar(string $codigo): ?Moneda
    {
        $codigo = self::normalizarCodigo($codigo);
        if ($codigo === '') {
            return null;
        }

        $lower = mb_strtolower($codigo);
        $porCodigo = self::queryIgual('codigo', $lower);
        if ($porCodigo !== null) {
            return $porCodigo;
        }

        $porAbreviatura = self::queryIgual('abreviatura', $lower);
        if ($porAbreviatura !== null) {
            return $porAbreviatura;
        }

        $porNombre = self::queryIgual('nombre', $lower);
        if ($porNombre !== null) {
            return $porNombre;
        }

        if (ctype_digit($codigo)) {
            return Moneda::query()->find((int) $codigo);
        }

        return null;
    }

    public static function normalizarCodigo(?string $codigo): string
    {
        $texto = strtoupper(trim((string) $codigo));
        $texto = preg_replace('/\s+/', '', $texto) ?? '';

        return substr($texto, 0, 20);
    }

    public static function codigoVisible(Moneda $moneda): string
    {
        $codigo = trim((string) ($moneda->codigo ?? ''));
        if ($codigo !== '') {
            return $codigo;
        }

        $abreviatura = trim((string) ($moneda->abreviatura ?? ''));
        if ($abreviatura !== '') {
            return $abreviatura;
        }

        return (string) $moneda->id;
    }

    public static function etiqueta(Moneda $moneda): string
    {
        $nombre = strtoupper(trim((string) ($moneda->nombre ?? '')));

        return $nombre !== '' ? $nombre : self::codigoVisible($moneda);
    }

    private static function queryIgual(string $columna, string $lower): ?Moneda
    {
        if (! in_array($columna, ['codigo', 'abreviatura', 'nombre'], true)) {
            return null;
        }

        return Moneda::query()
            ->whereRaw(SqlDialectSupport::lower($columna).' = ?', [$lower])
            ->orderBy('id')
            ->first();
    }

    private static function parsearCotizacion(mixed $raw): float
    {
        if ($raw === null || $raw === '') {
            return 0.0;
        }
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }
        if (is_numeric($raw)) {
            return (float) $raw;
        }

        $texto = str_replace([' ', '$', "\xc2\xa0"], '', trim((string) $raw));
        if ($texto === '' || ! preg_match('/\d/', $texto)) {
            return 0.0;
        }

        $ultimaComa = strrpos($texto, ',');
        $ultimoPunto = strrpos($texto, '.');
        if ($ultimaComa !== false && $ultimoPunto !== false) {
            if ($ultimaComa > $ultimoPunto) {
                $texto = str_replace('.', '', $texto);
                $texto = str_replace(',', '.', $texto);
            } else {
                $texto = str_replace(',', '', $texto);
            }
        } elseif ($ultimaComa !== false) {
            $texto = str_replace('.', '', $texto);
            $texto = str_replace(',', '.', $texto);
        } elseif (substr_count($texto, '.') > 1) {
            $partes = explode('.', $texto);
            $dec = array_pop($partes);
            $texto = implode('', $partes).'.'.$dec;
        }

        $texto = preg_replace('/[^\d.\-]/', '', $texto) ?? '';
        if ($texto === '' || ! is_numeric($texto)) {
            return 0.0;
        }

        return (float) $texto;
    }

    private static function escalar(float $importe, float $factor, int $decimales): float
    {
        if (abs($importe) < 0.0000005) {
            return 0.0;
        }

        return round($importe * $factor, $decimales);
    }
}
