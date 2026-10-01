<?php

namespace App\Support\Compras\AnitaImport;

/**
 * Interpreta aplmovp: aplvp_* es el comprobante aplicado (deuda típica),
 * aplvp_*_cob es el que aplica (OP / NC).
 *
 * @phpstan-type Lado array{proveedor: string, tipo: string, letra: string, sucursal: int, numero: int, clave: string}
 * @phpstan-type Par array{
 *   fecha: string,
 *   monto: float,
 *   credito: Lado,
 *   deuda: Lado,
 *   credito_es_pago: bool,
 *   etiqueta_credito: string,
 *   etiqueta_deuda: string
 * }
 */
final class ComprobanteProveedorAnitaImportAplmovpSupport
{
    /** @var list<string> */
    private const TIPOS_PAGO = ['ANT', 'CHP', 'REC'];

    public static function esTipoPago(string $tipo): bool
    {
        $tipo = ComprobanteProveedorAnitaImportClaveSupport::tipo($tipo);
        if ($tipo === '') {
            return false;
        }

        // OPP/OPA/… y AOP (anulación de OP: mismo circuito, signo inverso).
        if (str_starts_with($tipo, 'OP') || $tipo === 'AOP') {
            return true;
        }

        return in_array($tipo, self::TIPOS_PAGO, true);
    }

    /**
     * @param  array<string, string|int>  $signoPorTipo  abreviatura => R|S o 1|-1
     */
    public static function esCredito(string $tipo, array $signoPorTipo): bool
    {
        $tipo = ComprobanteProveedorAnitaImportClaveSupport::tipo($tipo);
        if ($tipo === '') {
            return false;
        }

        if (array_key_exists($tipo, $signoPorTipo)) {
            return self::signoEsCredito($signoPorTipo[$tipo]);
        }

        if (self::esTipoPago($tipo) || str_starts_with($tipo, 'NC')) {
            return true;
        }

        return false;
    }

    /**
     * tipotransaccion_compra.signo es -1/1. El mapa semilla histórico usa R/S.
     */
    private static function signoEsCredito(mixed $signo): bool
    {
        if (is_numeric($signo)) {
            return (float) $signo < 0;
        }

        return strtoupper(trim((string) $signo)) === 'R';
    }

    /**
     * @param  array<string, mixed>|object  $fila
     * @param  array<string, string>  $signoPorTipo
     * @return Par|null
     */
    public static function parDesdeFila(array|object $fila, array $signoPorTipo): ?array
    {
        $f = (array) $fila;
        $monto = round(abs((float) ($f['aplvp_monto'] ?? 0)), 4);
        if ($monto < 0.0001) {
            return null;
        }

        $deudaDoc = self::lado(
            (string) ($f['aplvp_proveedor'] ?? ''),
            (string) ($f['aplvp_tipo'] ?? ''),
            (string) ($f['aplvp_letra'] ?? ''),
            (int) ($f['aplvp_sucursal'] ?? 0),
            (int) ($f['aplvp_nro'] ?? 0),
        );
        $creditoDoc = self::lado(
            (string) ($f['aplvp_proveedor'] ?? ''),
            (string) ($f['aplvp_tipo_cob'] ?? ''),
            (string) ($f['aplvp_letra_cob'] ?? ''),
            (int) ($f['aplvp_sucursal_cob'] ?? 0),
            (int) ($f['aplvp_nro_cob'] ?? 0),
        );

        if ($deudaDoc === null || $creditoDoc === null || $deudaDoc['clave'] === $creditoDoc['clave']) {
            return null;
        }

        $aEsCredito = self::esCredito($deudaDoc['tipo'], $signoPorTipo);
        $bEsCredito = self::esCredito($creditoDoc['tipo'], $signoPorTipo);

        if ($aEsCredito === $bEsCredito) {
            $docEsNotaCredito = $aEsCredito && ! self::esTipoPago($deudaDoc['tipo']);
            $cobEsPago = self::esTipoPago($creditoDoc['tipo']);
            if ($docEsNotaCredito && $cobEsPago) {
                // La OP aplica la nota de crédito: la NC queda en crédito (+)
                // y la OP en el lado que resta. Si la NC se trata como deuda,
                // la aplicación queda del mismo signo que el total y el saldo se duplica.
                $bEsCredito = false;
            } elseif ($cobEsPago || str_starts_with($creditoDoc['tipo'], 'NC')) {
                $aEsCredito = false;
                $bEsCredito = true;
            } else {
                return null;
            }
        }

        $credito = $bEsCredito ? $creditoDoc : $deudaDoc;
        $deuda = $bEsCredito ? $deudaDoc : $creditoDoc;

        $fecha = ComprobanteProveedorAnitaImportClaveSupport::fechaIsoDesdeAnita($f['aplvp_fecha'] ?? '');
        if ($fecha === '') {
            $fecha = date('Y-m-d');
        }

        return [
            'fecha' => $fecha,
            'monto' => $monto,
            'credito' => $credito,
            'deuda' => $deuda,
            'credito_es_pago' => self::esTipoPago($credito['tipo']),
            'etiqueta_credito' => ComprobanteProveedorAnitaImportClaveSupport::etiqueta(
                $credito['tipo'],
                $credito['letra'],
                $credito['sucursal'],
                $credito['numero']
            ),
            'etiqueta_deuda' => ComprobanteProveedorAnitaImportClaveSupport::etiqueta(
                $deuda['tipo'],
                $deuda['letra'],
                $deuda['sucursal'],
                $deuda['numero']
            ),
        ];
    }

    /**
     * @param  list<array<string, mixed>|object>  $filas
     * @param  array<string, string>  $signoPorTipo
     * @return list<Par>
     */
    public static function paresDesdeFilas(array $filas, array $signoPorTipo): array
    {
        $pares = [];
        $vistos = [];
        foreach ($filas as $fila) {
            $par = self::parDesdeFila($fila, $signoPorTipo);
            if ($par === null) {
                continue;
            }
            $dedup = implode('~', [
                $par['deuda']['clave'],
                $par['credito']['clave'],
                number_format($par['monto'], 4, '.', ''),
                $par['fecha'],
            ]);
            if (isset($vistos[$dedup])) {
                continue;
            }
            $vistos[$dedup] = true;
            $pares[] = $par;
        }

        return $pares;
    }

    /**
     * @return Lado|null
     */
    private static function lado(
        string $proveedor,
        string $tipo,
        string $letra,
        int $sucursal,
        int $numero,
    ): ?array {
        $tipo = ComprobanteProveedorAnitaImportClaveSupport::tipo($tipo);
        if ($tipo === '' || $numero <= 0) {
            return null;
        }

        $prov = ComprobanteProveedorAnitaImportClaveSupport::proveedorCodigoAnita($proveedor);

        return [
            'proveedor' => $prov,
            'tipo' => $tipo,
            'letra' => ComprobanteProveedorAnitaImportClaveSupport::letra($letra),
            'sucursal' => $sucursal,
            'numero' => $numero,
            'clave' => ComprobanteProveedorAnitaImportClaveSupport::clave(
                $prov,
                $tipo,
                $letra,
                $sucursal,
                $numero
            ),
        ];
    }
}
