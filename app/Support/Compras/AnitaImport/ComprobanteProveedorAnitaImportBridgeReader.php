<?php

namespace App\Support\Compras\AnitaImport;

use App\ApiAnita;
use RuntimeException;

/**
 * Lectura Anita: compra + promov + aplmovp (+ concmov como detalle de compra).
 * Campos de compra/promov según instalación (Ferli sin *_empresa / FCE).
 */
final class ComprobanteProveedorAnitaImportBridgeReader
{
    private const CHUNK_CONCMOV = 80;

    public function __construct(
        private readonly ApiAnita $api = new ApiAnita,
    ) {}

    public function sistemaCompras(): string
    {
        return (string) config('comprobante_proveedor.anita_sistema_compras', 'compras');
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarCompra(
        string $proveedorCodigo,
        ?int $fechaDesdeYmd = null,
        ?int $fechaHastaYmd = null,
        ?int $empresaCodigo = null,
        bool $filtrarPorFechaIva = false,
    ): array {
        $perfil = ProveedorCuentacorrienteAnitaImportFormatoSupport::perfil();
        $prov = ComprobanteProveedorAnitaImportClaveSupport::proveedorCodigoAnita($proveedorCodigo);
        $colFecha = $filtrarPorFechaIva ? 'com_fecha_iva' : 'com_fecha';
        $where = " WHERE com_proveedor = '".$this->esc($prov)."'";
        if ($fechaDesdeYmd !== null && $fechaDesdeYmd > 0) {
            $where .= ' AND '.$colFecha.' >= '.(int) $fechaDesdeYmd;
        }
        if ($fechaHastaYmd !== null && $fechaHastaYmd > 0) {
            $where .= ' AND '.$colFecha.' <= '.(int) $fechaHastaYmd;
        }
        if ($perfil['tiene_empresa'] && $empresaCodigo !== null && $empresaCodigo > 0) {
            $where .= ' AND com_empresa = '.(int) $empresaCodigo;
        }

        return $this->listar('compra', $perfil['campos_compra'], $where, $colFecha.', com_nro_interno');
    }

    /**
     * Códigos de proveedor con al menos una compra en el rango.
     *
     * @return list<string>
     */
    public function listarProveedoresConCompra(
        ?int $fechaDesdeYmd = null,
        ?int $fechaHastaYmd = null,
        ?int $empresaCodigo = null,
        bool $filtrarPorFechaIva = false,
    ): array {
        $perfil = ProveedorCuentacorrienteAnitaImportFormatoSupport::perfil();
        $colFecha = $filtrarPorFechaIva ? 'com_fecha_iva' : 'com_fecha';
        $where = ' WHERE 1=1';
        if ($fechaDesdeYmd !== null && $fechaDesdeYmd > 0) {
            $where .= ' AND '.$colFecha.' >= '.(int) $fechaDesdeYmd;
        }
        if ($fechaHastaYmd !== null && $fechaHastaYmd > 0) {
            $where .= ' AND '.$colFecha.' <= '.(int) $fechaHastaYmd;
        }
        if ($perfil['tiene_empresa'] && $empresaCodigo !== null && $empresaCodigo > 0) {
            $where .= ' AND com_empresa = '.(int) $empresaCodigo;
        }
        // Informix: GROUP BY en whereArmado; el bridge no arma SELECT DISTINCT limpio.
        $where .= ' GROUP BY com_proveedor';

        $filas = $this->listar('compra', 'com_proveedor', $where, '1');
        $out = [];
        foreach ($filas as $fila) {
            $cod = trim((string) ($fila['com_proveedor'] ?? ''));
            if ($cod !== '') {
                $out[$cod] = $cod;
            }
        }

        return array_values($out);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarPromov(
        string $proveedorCodigo,
        ?int $fechaDesdeYmd = null,
        ?int $fechaHastaYmd = null,
        ?int $empresaCodigo = null,
    ): array {
        $perfil = ProveedorCuentacorrienteAnitaImportFormatoSupport::perfil();
        $prov = ComprobanteProveedorAnitaImportClaveSupport::proveedorCodigoAnita($proveedorCodigo);
        $where = " WHERE prov_proveedor = '".$this->esc($prov)."'";
        if ($fechaDesdeYmd !== null && $fechaDesdeYmd > 0) {
            $where .= ' AND prov_fecha >= '.(int) $fechaDesdeYmd;
        }
        if ($fechaHastaYmd !== null && $fechaHastaYmd > 0) {
            $where .= ' AND prov_fecha <= '.(int) $fechaHastaYmd;
        }
        if ($perfil['tiene_empresa'] && $empresaCodigo !== null && $empresaCodigo > 0) {
            $where .= ' AND prov_empresa = '.(int) $empresaCodigo;
        }

        return $this->listar(
            $perfil['tabla_promov'],
            $perfil['campos_promov'],
            $where,
            'prov_fecha, prov_nro, prov_nro_cuota'
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listarAplmovp(
        string $proveedorCodigo,
        ?int $fechaDesdeYmd = null,
        ?int $fechaHastaYmd = null,
    ): array {
        $perfil = ProveedorCuentacorrienteAnitaImportFormatoSupport::perfil();
        $prov = ComprobanteProveedorAnitaImportClaveSupport::proveedorCodigoAnita($proveedorCodigo);
        $where = " WHERE aplvp_proveedor = '".$this->esc($prov)."'";
        if ($fechaDesdeYmd !== null && $fechaDesdeYmd > 0) {
            $where .= ' AND aplvp_fecha >= '.(int) $fechaDesdeYmd;
        }
        if ($fechaHastaYmd !== null && $fechaHastaYmd > 0) {
            $where .= ' AND aplvp_fecha <= '.(int) $fechaHastaYmd;
        }

        return $this->listar(
            $perfil['tabla_aplmovp'],
            $perfil['campos_aplmovp'],
            $where,
            'aplvp_fecha, aplvp_nro'
        );
    }

    /**
     * @param  list<int>  $nrosInternos
     * @return array<int, list<array{concepto: int, importe: float}>>
     */
    public function listarConcmovPorInternos(array $nrosInternos): array
    {
        $nros = array_values(array_unique(array_filter(
            array_map('intval', $nrosInternos),
            static fn (int $n) => $n > 0,
        )));
        if ($nros === []) {
            return [];
        }

        $porInterno = [];
        foreach (array_chunk($nros, self::CHUNK_CONCMOV) as $lote) {
            $in = implode(',', $lote);
            $filas = $this->listar(
                'concmov',
                'concv_nro_interno, concv_concepto, concv_importe',
                ' WHERE concv_nro_interno IN ('.$in.')',
                'concv_nro_interno, concv_concepto'
            );
            foreach ($filas as $fila) {
                $nro = (int) ($fila['concv_nro_interno'] ?? 0);
                if ($nro <= 0) {
                    continue;
                }
                $porInterno[$nro][] = [
                    'concepto' => (int) ($fila['concv_concepto'] ?? 0),
                    'importe' => (float) ($fila['concv_importe'] ?? 0),
                ];
            }
        }

        return $porInterno;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function listar(string $tabla, string $campos, string $where, string $orderBy): array
    {
        $parsed = ApiAnita::parsearRespuestaLista($this->api->apiCall([
            'acc' => 'list',
            'sistema' => $this->sistemaCompras(),
            'tabla' => $tabla,
            'campos' => $campos,
            'whereArmado' => $where,
            'orderBy' => $orderBy,
        ]));

        if ($parsed['error_lectura'] !== null) {
            throw new RuntimeException(
                'Anita '.$tabla.': '.$parsed['error_lectura']
            );
        }

        $out = [];
        foreach ($parsed['filas'] as $fila) {
            $out[] = (array) $fila;
        }

        return $out;
    }

    private function esc(string $valor): string
    {
        return str_replace("'", "''", $valor);
    }
}
