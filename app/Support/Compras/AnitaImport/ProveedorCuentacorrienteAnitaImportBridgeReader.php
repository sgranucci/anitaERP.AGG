<?php

namespace App\Support\Compras\AnitaImport;

use App\ApiAnita;
use RuntimeException;

/**
 * Lecturas Anita para deuda proveedores: promov pendiente + compra + aplmovp.
 */
final class ProveedorCuentacorrienteAnitaImportBridgeReader
{
    public function __construct(
        private readonly ApiAnita $api = new ApiAnita,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listarPromovPendiente(
        ?int $fechaDesdeYmd = null,
        ?int $fechaHastaYmd = null,
        ?string $proveedorCodigo = null,
        ?int $empresaCodigoAnita = null,
    ): array {
        $perfil = ProveedorCuentacorrienteAnitaImportFormatoSupport::perfil();
        if ($fechaDesdeYmd && $fechaHastaYmd && $fechaDesdeYmd <= $fechaHastaYmd) {
            return $this->listarPromovPendienteRango(
                $fechaDesdeYmd,
                $fechaHastaYmd,
                $proveedorCodigo,
                $perfil,
                $empresaCodigoAnita,
            );
        }

        // Sin rango: por año para no tumbar el UNLOAD.
        $desde = $fechaDesdeYmd ?: 20000101;
        $hasta = $fechaHastaYmd ?: (int) date('Ymd');
        $anioDesde = (int) substr((string) $desde, 0, 4);
        $anioHasta = (int) substr((string) $hasta, 0, 4);
        $out = [];
        for ($y = $anioDesde; $y <= $anioHasta; $y++) {
            $d = max($desde, (int) ($y.'0101'));
            $h = min($hasta, (int) ($y.'1231'));
            if ($d > $h) {
                continue;
            }
            foreach ($this->listarPromovPendienteRango(
                $d,
                $h,
                $proveedorCodigo,
                $perfil,
                $empresaCodigoAnita,
            ) as $fila) {
                $out[] = $fila;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $claves  proveedor|tipo|letra|suc|nro
     * @return array<string, array<string, mixed>>
     */
    public function indexarCompraPorClaves(array $claves, ?int $empresaCodigoAnita = null): array
    {
        $perfil = ProveedorCuentacorrienteAnitaImportFormatoSupport::perfil();
        $out = [];
        foreach ($this->listarPorClaves(
            $perfil['tabla_compra'],
            $perfil['campos_compra'],
            'com_proveedor',
            'com_tipo',
            'com_letra',
            'com_sucursal',
            'com_nro',
            $claves,
            $perfil,
            true,
            $perfil['tiene_empresa'] ? $empresaCodigoAnita : null,
            'com_empresa',
        ) as $fila) {
            $clave = ComprobanteProveedorAnitaImportClaveSupport::claveDesdeCompra($fila);
            if (isset($out[$clave])) {
                continue;
            }
            $out[$clave] = $fila;
        }

        return $out;
    }

    /**
     * Promov por clave documental (incluye saldadas: monto = t_pagado).
     *
     * @param  list<string>  $claves  proveedor|tipo|letra|suc|nro
     * @return array<string, list<array<string, mixed>>>
     */
    public function indexarPromovPorClaves(array $claves, ?int $empresaCodigoAnita = null): array
    {
        $perfil = ProveedorCuentacorrienteAnitaImportFormatoSupport::perfil();
        $out = [];
        foreach ($this->listarPorClaves(
            $perfil['tabla_promov'],
            $perfil['campos_promov'],
            'prov_proveedor',
            'prov_tipo',
            'prov_letra',
            'prov_sucursal',
            'prov_nro',
            $claves,
            $perfil,
            true,
            $perfil['tiene_empresa'] ? $empresaCodigoAnita : null,
            'prov_empresa',
        ) as $fila) {
            $clave = ComprobanteProveedorAnitaImportClaveSupport::claveDesdePromov($fila);
            $out[$clave][] = $fila;
        }

        return $out;
    }

    /**
     * @param  list<string>  $clavesDeuda  proveedor|tipo|letra|suc|nro
     * @return list<array<string, mixed>>
     */
    public function listarAplmovpPorDeudas(array $clavesDeuda): array
    {
        $perfil = ProveedorCuentacorrienteAnitaImportFormatoSupport::perfil();

        return $this->listarPorClaves(
            $perfil['tabla_aplmovp'],
            $perfil['campos_aplmovp'],
            'aplvp_proveedor',
            'aplvp_tipo',
            'aplvp_letra',
            'aplvp_sucursal',
            'aplvp_nro',
            $clavesDeuda,
            $perfil,
            true,
        );
    }

    /**
     * @param  array<string, mixed>  $perfil
     * @return list<array<string, mixed>>
     */
    private function listarPromovPendienteRango(
        int $desdeYmd,
        int $hastaYmd,
        ?string $proveedorCodigo,
        array $perfil,
        ?int $empresaCodigoAnita = null,
    ): array {
        $where = ' WHERE prov_fecha >= '.(int) $desdeYmd
            .' AND prov_fecha <= '.(int) $hastaYmd
            .' AND ABS(prov_monto) <> ABS(prov_t_pagado)';
        if ($proveedorCodigo !== null && trim($proveedorCodigo) !== '') {
            $prov = ComprobanteProveedorAnitaImportClaveSupport::proveedorCodigoAnita($proveedorCodigo);
            $where .= " AND prov_proveedor = '".$this->esc($prov)."'";
        }
        if ($perfil['tiene_empresa'] && $empresaCodigoAnita !== null && $empresaCodigoAnita > 0) {
            $where .= ' AND prov_empresa = '.(int) $empresaCodigoAnita;
        }

        return $this->listar(
            $perfil['tabla_promov'],
            $perfil['campos_promov'],
            $where,
            'prov_fecha, prov_proveedor, prov_nro, prov_nro_cuota',
            $perfil
        );
    }

    /**
     * @param  list<string>  $claves
     * @param  array<string, mixed>  $perfil
     * @return list<array<string, mixed>>
     */
    private function listarPorClaves(
        string $tabla,
        string $campos,
        string $colProv,
        string $colTipo,
        string $colLetra,
        string $colSuc,
        string $colNro,
        array $claves,
        array $perfil,
        bool $incluyeProveedor,
        ?int $empresaCodigoAnita = null,
        ?string $colEmpresa = null,
    ): array {
        $claves = array_values(array_unique(array_filter($claves)));
        if ($claves === []) {
            return [];
        }

        $out = [];
        foreach (array_chunk($claves, 30) as $chunk) {
            $ors = [];
            foreach ($chunk as $clave) {
                $partes = explode('|', $clave);
                if ($incluyeProveedor) {
                    if (count($partes) < 5) {
                        continue;
                    }
                    [$prov, $tipo, $letra, $suc, $nro] = $partes;
                    $ors[] = '('.$colProv." = '".$this->esc($prov)
                        ."' AND ".$colTipo." = '".$this->esc($tipo)
                        ."' AND ".$colLetra." = '".$this->esc($letra)
                        ."' AND ".$colSuc.' = '.(int) $suc
                        .' AND '.$colNro.' = '.(int) $nro.')';
                } else {
                    if (count($partes) < 4) {
                        continue;
                    }
                    [$tipo, $letra, $suc, $nro] = $partes;
                    $ors[] = '('.$colTipo." = '".$this->esc($tipo)
                        ."' AND ".$colLetra." = '".$this->esc($letra)
                        ."' AND ".$colSuc.' = '.(int) $suc
                        .' AND '.$colNro.' = '.(int) $nro.')';
                }
            }
            if ($ors === []) {
                continue;
            }
            $where = ' WHERE ('.implode(' OR ', $ors).')';
            if (
                $perfil['tiene_empresa']
                && $empresaCodigoAnita !== null
                && $empresaCodigoAnita > 0
                && $colEmpresa !== null
                && $colEmpresa !== ''
            ) {
                $where .= ' AND '.$colEmpresa.' = '.(int) $empresaCodigoAnita;
            }
            foreach ($this->listar(
                $tabla,
                $campos,
                $where,
                $colTipo.', '.$colSuc.', '.$colNro,
                $perfil
            ) as $fila) {
                $out[] = $fila;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $perfil
     * @return list<array<string, mixed>>
     */
    private function listar(
        string $tabla,
        string $campos,
        string $where,
        string $orderBy,
        array $perfil,
    ): array {
        $intentos = (int) $perfil['bridge_list_reintentos'];
        $ultimoErr = null;
        $mejor = [];

        for ($i = 1; $i <= $intentos; $i++) {
            $raw = (string) $this->api->apiCall([
                'acc' => 'list',
                'sistema' => $perfil['sistema'],
                'tabla' => $tabla,
                'campos' => $campos,
                'whereArmado' => $where,
                'orderBy' => $orderBy,
            ]);
            $ultimoErr = ApiAnita::extraerMensajeError($raw);
            if ($ultimoErr === null) {
                $filas = [];
                foreach (ApiAnita::decodificarListaFilas($raw) as $fila) {
                    $filas[] = (array) $fila;
                }
                if (count($filas) > count($mejor)) {
                    $mejor = $filas;
                }
                if ($filas !== [] || $i === $intentos) {
                    return $mejor !== [] ? $mejor : $filas;
                }
            } elseif ($i === $intentos) {
                throw new RuntimeException(
                    'Anita bridge '.$tabla.': '.$ultimoErr
                    .' (entorno='.$perfil['entorno'].')'
                );
            }
            usleep(200000);
        }

        if ($ultimoErr !== null) {
            throw new RuntimeException('Anita bridge '.$tabla.': '.$ultimoErr);
        }

        return $mejor;
    }

    private function esc(string $valor): string
    {
        return str_replace("'", "''", $valor);
    }
}
