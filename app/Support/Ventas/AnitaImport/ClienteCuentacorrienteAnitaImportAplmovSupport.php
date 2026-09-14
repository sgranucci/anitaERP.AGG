<?php

namespace App\Support\Ventas\AnitaImport;

/**
 * Interpreta aplmov: aplv_* = deuda (factura), aplv_*_cob (o *_ref) = crédito (COB/NC).
 *
 * @phpstan-type Lado array{tipo: string, letra: string, sucursal: int, numero: int, clave: string}
 * @phpstan-type Par array{
 *   fecha: string,
 *   monto: float,
 *   nro_cuota: int,
 *   credito: Lado,
 *   deuda: Lado,
 *   credito_es_pago: bool,
 *   etiqueta_credito: string,
 *   etiqueta_deuda: string
 * }
 */
final class ClienteCuentacorrienteAnitaImportAplmovSupport
{
    /** @var list<string> */
    private const TIPOS_PAGO = ['COB', 'COA', 'ANT', 'REC', 'RBO'];

    public static function esTipoPago(string $tipo): bool
    {
        $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo($tipo);

        return $tipo !== '' && in_array($tipo, self::TIPOS_PAGO, true);
    }

    /**
     * @param  array<string, int>  $signoPorTipo  abreviatura => 1|-1
     */
    public static function esCredito(string $tipo, array $signoPorTipo): bool
    {
        $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo($tipo);
        if ($tipo === '') {
            return false;
        }

        if (isset($signoPorTipo[$tipo])) {
            return $signoPorTipo[$tipo] < 0;
        }

        if (self::esTipoPago($tipo) || str_starts_with($tipo, 'NC')) {
            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>|object  $fila
     * @param  array<string, int>  $signoPorTipo
     * @return Par|null
     */
    public static function parDesdeFila(array|object $fila, array $signoPorTipo, bool $fallbackRef = true): ?array
    {
        $f = (array) $fila;
        $monto = round(abs((float) ($f['aplv_monto'] ?? 0)), 4);
        if ($monto < 0.0001) {
            return null;
        }

        $deudaDoc = self::lado(
            (string) ($f['aplv_tipo'] ?? ''),
            (string) ($f['aplv_letra'] ?? ''),
            (int) ($f['aplv_sucursal'] ?? 0),
            (int) ($f['aplv_nro'] ?? 0),
        );

        $tipoCob = trim((string) ($f['aplv_tipo_cob'] ?? ''));
        $letraCob = (string) ($f['aplv_letra_cob'] ?? '');
        $sucCob = (int) ($f['aplv_sucursal_cob'] ?? 0);
        $nroCob = (int) ($f['aplv_nro_cob'] ?? 0);

        if ($fallbackRef && ($tipoCob === '' || $nroCob <= 0)) {
            $tipoCob = (string) ($f['aplv_ref_tipo'] ?? $tipoCob);
            $letraCob = (string) ($f['aplv_ref_letra'] ?? $letraCob);
            $sucCob = (int) ($f['aplv_ref_sucursal'] ?? $sucCob);
            $nroCob = (int) ($f['aplv_ref_nro'] ?? $nroCob);
        }

        $creditoDoc = self::lado($tipoCob, $letraCob, $sucCob, $nroCob);

        if ($deudaDoc === null || $creditoDoc === null || $deudaDoc['clave'] === $creditoDoc['clave']) {
            return null;
        }

        $aEsCredito = self::esCredito($deudaDoc['tipo'], $signoPorTipo);
        $bEsCredito = self::esCredito($creditoDoc['tipo'], $signoPorTipo);

        if ($aEsCredito === $bEsCredito) {
            if (self::esTipoPago($creditoDoc['tipo']) || str_starts_with($creditoDoc['tipo'], 'NC')) {
                $aEsCredito = false;
                $bEsCredito = true;
            } else {
                return null;
            }
        }

        $credito = $bEsCredito ? $creditoDoc : $deudaDoc;
        $deuda = $bEsCredito ? $deudaDoc : $creditoDoc;

        $fecha = ClienteCuentacorrienteAnitaImportClaveSupport::fechaIsoDesdeAnita(
            $f['aplv_fecha_aplic'] ?? $f['aplv_fecha'] ?? ''
        );
        if ($fecha === '') {
            $fecha = ClienteCuentacorrienteAnitaImportClaveSupport::fechaIsoDesdeAnita($f['aplv_fecha'] ?? '');
        }
        if ($fecha === '') {
            $fecha = date('Y-m-d');
        }

        return [
            'fecha' => $fecha,
            'monto' => $monto,
            'nro_cuota' => max(0, (int) ($f['aplv_nro_cuota'] ?? 1)),
            'credito' => $credito,
            'deuda' => $deuda,
            'credito_es_pago' => self::esTipoPago($credito['tipo']),
            'etiqueta_credito' => ClienteCuentacorrienteAnitaImportClaveSupport::etiquetaErp(
                $credito['tipo'],
                $credito['letra'],
                $credito['sucursal'],
                $credito['numero']
            ),
            'etiqueta_deuda' => ClienteCuentacorrienteAnitaImportClaveSupport::etiquetaErp(
                $deuda['tipo'],
                $deuda['letra'],
                $deuda['sucursal'],
                $deuda['numero']
            ),
        ];
    }

    /**
     * @param  list<array<string, mixed>|object>  $filas
     * @param  array<string, int>  $signoPorTipo
     * @return list<Par>
     */
    public static function paresDesdeFilas(array $filas, array $signoPorTipo, bool $fallbackRef = true): array
    {
        $pares = [];
        $vistos = [];
        foreach ($filas as $fila) {
            $par = self::parDesdeFila($fila, $signoPorTipo, $fallbackRef);
            if ($par === null) {
                continue;
            }
            $dedup = implode('~', [
                $par['deuda']['clave'],
                $par['credito']['clave'],
                number_format($par['monto'], 4, '.', ''),
                (string) $par['nro_cuota'],
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
    private static function lado(string $tipo, string $letra, int $sucursal, int $numero): ?array
    {
        $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo($tipo);
        if ($tipo === '' || $numero <= 0) {
            return null;
        }

        $letraN = ClienteCuentacorrienteAnitaImportClaveSupport::letra($letra);

        return [
            'tipo' => $tipo,
            'letra' => $letraN,
            'sucursal' => $sucursal,
            'numero' => $numero,
            'clave' => ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento(
                $tipo,
                $letraN,
                $sucursal,
                $numero
            ),
        ];
    }
}
