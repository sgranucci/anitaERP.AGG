<?php

namespace App\Support\Ventas\AnitaImport;

use App\ApiAnita;
use RuntimeException;

/**
 * Lectura Anita: climov (deuda pendiente) + aplmov (aplicaciones).
 */
final class ClienteCuentacorrienteAnitaImportBridgeReader
{
    public function __construct(
        private readonly ApiAnita $api = new ApiAnita,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listarClimovPendiente(
        ?string $clienteCodigo = null,
        ?int $fechaDesdeYmd = null,
        ?int $fechaHastaYmd = null,
        ?int $empresaCodigo = null,
        bool $soloConSaldo = true,
    ): array {
        $perfil = ClienteCuentacorrienteAnitaImportFormatoSupport::perfil();
        // Informix: NULL/'' <> 'C' = UNKNOWN → no matchea. El grueso sigue con <> 'C'.
        $where = " WHERE cliv_estado <> 'C'";
        $where .= $this->filtrosClimovComunes($clienteCodigo, $fechaDesdeYmd, $fechaHastaYmd, $empresaCodigo, $soloConSaldo, $perfil);

        $filas = $this->listar(
            $perfil['tabla_climov'],
            $perfil['campos_climov'],
            $where,
            'cliv_fecha, cliv_tipo, cliv_sucursal, cliv_nro, cliv_nro_cuota',
            $perfil
        );

        // Complemento acotado: COA (créditos sin venta) con estado NULL/vacío.
        $extra = $this->listarClimovCreditoEstadoVacio(
            $clienteCodigo,
            $fechaDesdeYmd,
            $fechaHastaYmd,
            $empresaCodigo,
            $soloConSaldo,
            $perfil
        );
        if ($extra === []) {
            return $filas;
        }

        $vistos = [];
        foreach ($filas as $fila) {
            $vistos[ClienteCuentacorrienteAnitaImportClaveSupport::claveCuotaDesdeClimov($fila)] = true;
        }
        foreach ($extra as $fila) {
            $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveCuotaDesdeClimov($fila);
            if (isset($vistos[$clave])) {
                continue;
            }
            $filas[] = $fila;
            $vistos[$clave] = true;
        }

        return $filas;
    }

    /**
     * COA (u otros tipos_credito_sin_venta) con cliv_estado NULL/'' y saldo.
     * Informix no las incluye en `cliv_estado <> 'C'`.
     *
     * @param  array<string, mixed>  $perfil
     * @return list<array<string, mixed>>
     */
    private function listarClimovCreditoEstadoVacio(
        ?string $clienteCodigo,
        ?int $fechaDesdeYmd,
        ?int $fechaHastaYmd,
        ?int $empresaCodigo,
        bool $soloConSaldo,
        array $perfil,
    ): array {
        $tipos = array_values(array_filter((array) ($perfil['tipos_credito_sin_venta'] ?? ['COA'])));
        if ($tipos === []) {
            return [];
        }
        $in = [];
        foreach ($tipos as $tipo) {
            $t = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) $tipo);
            if ($t !== '') {
                $in[] = "'".$this->esc($t)."'";
            }
        }
        if ($in === []) {
            return [];
        }

        $where = ' WHERE cliv_tipo IN ('.implode(',', $in).')'
            ." AND (cliv_estado IS NULL OR cliv_estado = '')";
        $where .= $this->filtrosClimovComunes($clienteCodigo, $fechaDesdeYmd, $fechaHastaYmd, $empresaCodigo, $soloConSaldo, $perfil);

        return $this->listar(
            $perfil['tabla_climov'],
            $perfil['campos_climov'],
            $where,
            'cliv_fecha, cliv_tipo, cliv_sucursal, cliv_nro, cliv_nro_cuota',
            $perfil
        );
    }

    /**
     * @param  array<string, mixed>  $perfil
     */
    private function filtrosClimovComunes(
        ?string $clienteCodigo,
        ?int $fechaDesdeYmd,
        ?int $fechaHastaYmd,
        ?int $empresaCodigo,
        bool $soloConSaldo,
        array $perfil,
    ): string {
        $where = '';
        if ($soloConSaldo) {
            $where .= ' AND cliv_monto <> cliv_t_cobrado';
        }
        if ($clienteCodigo !== null && trim($clienteCodigo) !== '') {
            $cli = ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoAnita($clienteCodigo);
            $where .= " AND cliv_cliente = '".$this->esc($cli)."'";
        }
        if ($fechaDesdeYmd !== null && $fechaDesdeYmd > 0) {
            $where .= ' AND cliv_fecha >= '.(int) $fechaDesdeYmd;
        }
        if ($fechaHastaYmd !== null && $fechaHastaYmd > 0) {
            $where .= ' AND cliv_fecha <= '.(int) $fechaHastaYmd;
        }
        if ($perfil['climov_tiene_empresa'] && $empresaCodigo !== null && $empresaCodigo > 0) {
            $where .= ' AND cliv_empresa = '.(int) $empresaCodigo;
        }

        return $where;
    }

    /**
     * @param  list<string>  $clavesDocumento  tipo|letra|suc|nro
     * @return list<array<string, mixed>>
     */
    public function listarAplmovPorDeudas(array $clavesDocumento): array
    {
        $clavesDocumento = array_values(array_unique(array_filter($clavesDocumento)));
        if ($clavesDocumento === []) {
            return [];
        }

        $perfil = ClienteCuentacorrienteAnitaImportFormatoSupport::perfil();
        $out = [];
        foreach (array_chunk($clavesDocumento, 40) as $chunk) {
            $ors = [];
            foreach ($chunk as $clave) {
                $partes = explode('|', $clave);
                if (count($partes) < 4) {
                    continue;
                }
                [$tipo, $letra, $suc, $nro] = $partes;
                $ors[] = "(aplv_tipo = '".$this->esc($tipo)
                    ."' AND aplv_letra = '".$this->esc($letra)
                    ."' AND aplv_sucursal = ".(int) $suc
                    .' AND aplv_nro = '.(int) $nro.')';
            }
            if ($ors === []) {
                continue;
            }
            $where = ' WHERE ('.implode(' OR ', $ors).')';
            foreach ($this->listar(
                $perfil['tabla_aplmov'],
                $perfil['campos_aplmov'],
                $where,
                'aplv_fecha, aplv_tipo, aplv_nro',
                $perfil
            ) as $fila) {
                $out[] = $fila;
            }
        }

        return $out;
    }

    /**
     * climov de esos comprobantes, cerrados incluidos.
     * El listado de deuda abierta no los trae (cliv_estado = C).
     *
     * @param  list<string>  $clavesDocumento  tipo|letra|suc|nro
     * @return list<array<string, mixed>>
     */
    public function listarClimovPorDocumentos(array $clavesDocumento, ?string $clienteCodigo = null): array
    {
        $clavesDocumento = array_values(array_unique(array_filter($clavesDocumento)));
        if ($clavesDocumento === []) {
            return [];
        }

        $perfil = ClienteCuentacorrienteAnitaImportFormatoSupport::perfil();
        $cliente = trim((string) $clienteCodigo);
        $out = [];
        foreach (array_chunk($clavesDocumento, 40) as $chunk) {
            $ors = [];
            foreach ($chunk as $clave) {
                $partes = explode('|', $clave);
                if (count($partes) < 4) {
                    continue;
                }
                [$tipo, $letra, $suc, $nro] = $partes;
                $ors[] = "(cliv_tipo = '".$this->esc($tipo)
                    ."' AND cliv_letra = '".$this->esc($letra)
                    ."' AND cliv_sucursal = ".(int) $suc
                    .' AND cliv_nro = '.(int) $nro.')';
            }
            if ($ors === []) {
                continue;
            }
            $where = ' WHERE ('.implode(' OR ', $ors).')';
            if ($cliente !== '') {
                $where .= " AND cliv_cliente = '".$this->esc($cliente)."'";
            }
            foreach ($this->listar(
                $perfil['tabla_climov'],
                $perfil['campos_climov'],
                $where,
                'cliv_fecha, cliv_tipo, cliv_nro',
                $perfil
            ) as $fila) {
                $out[] = $fila;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $clavesDocumento  tipo|letra|suc|nro
     * @return array<string, array<string, mixed>>  clave => fila venta Anita
     */
    public function indexarVentaPorClaves(array $clavesDocumento): array
    {
        $perfil = ClienteCuentacorrienteAnitaImportFormatoSupport::perfil();
        $campos = implode(',', [
            'ven_cliente',
            'ven_tipo',
            'ven_letra',
            'ven_sucursal',
            'ven_nro',
            'ven_fecha',
            'ven_fecha_vto',
            'ven_monto',
            'ven_gravado',
            'ven_impuesto1',
            'ven_exento',
            'ven_cod_mon',
            'ven_cotizacion',
            'ven_nombre_cliente',
            'ven_direccion_cli',
            'ven_localidad_cli',
            'ven_provincia_cli',
            'ven_cod_postal_cli',
            'ven_cuit_cli',
            'ven_cond_iva_cli',
            'ven_cond_venta',
            'ven_vendedor',
            'ven_cta_cte',
            'ven_porc_desc',
            'ven_monto_desc',
        ]);

        $out = [];
        foreach ($this->listarPorClavesDocumento(
            'venta',
            $campos,
            'ven_tipo',
            'ven_letra',
            'ven_sucursal',
            'ven_nro',
            $clavesDocumento,
            $perfil
        ) as $fila) {
            $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento(
                (string) ($fila['ven_tipo'] ?? ''),
                (string) ($fila['ven_letra'] ?? ''),
                (int) ($fila['ven_sucursal'] ?? 0),
                (int) ($fila['ven_nro'] ?? 0),
            );
            $out[$clave] = $fila;
        }

        return $out;
    }

    /**
     * Lista cabeceras Anita `venta` por rango de ven_fecha (YYYYMMDD).
     *
     * @return list<array<string, mixed>>
     */
    public function listarVentaPorRangoFecha(int $desdeYmd, int $hastaYmd): array
    {
        $perfil = ClienteCuentacorrienteAnitaImportFormatoSupport::perfil();
        $campos = implode(',', [
            'ven_cliente',
            'ven_tipo',
            'ven_letra',
            'ven_sucursal',
            'ven_nro',
            'ven_fecha',
            'ven_fecha_vto',
            'ven_monto',
            'ven_gravado',
            'ven_impuesto1',
            'ven_exento',
            'ven_cod_mon',
            'ven_cotizacion',
            'ven_nombre_cliente',
            'ven_direccion_cli',
            'ven_localidad_cli',
            'ven_provincia_cli',
            'ven_cod_postal_cli',
            'ven_cuit_cli',
            'ven_cond_iva_cli',
            'ven_cond_venta',
            'ven_vendedor',
            'ven_cta_cte',
            'ven_porc_desc',
            'ven_monto_desc',
        ]);

        return $this->listar(
            'venta',
            $campos,
            ' WHERE ven_fecha >= '.(int) $desdeYmd.' AND ven_fecha <= '.(int) $hastaYmd,
            'ven_fecha, ven_tipo, ven_sucursal, ven_nro',
            $perfil
        );
    }

    /**
     * @param  list<string>  $clavesDocumento
     * @return array<string, array<string, mixed>>
     */
    public function indexarVencaePorClaves(array $clavesDocumento): array
    {
        $perfil = ClienteCuentacorrienteAnitaImportFormatoSupport::perfil();
        $campos = 'venc_tipo,venc_letra,venc_sucursal,venc_nro,venc_nro_cae,venc_fecha_vto';
        $out = [];
        foreach ($this->listarPorClavesDocumento(
            'vencae',
            $campos,
            'venc_tipo',
            'venc_letra',
            'venc_sucursal',
            'venc_nro',
            $clavesDocumento,
            $perfil
        ) as $fila) {
            $clave = ClienteCuentacorrienteAnitaImportClaveSupport::claveDocumento(
                (string) ($fila['venc_tipo'] ?? ''),
                (string) ($fila['venc_letra'] ?? ''),
                (int) ($fila['venc_sucursal'] ?? 0),
                (int) ($fila['venc_nro'] ?? 0),
            );
            $out[$clave] = $fila;
        }

        return $out;
    }

    /**
     * @param  list<string>  $clavesDocumento
     * @param  array<string, mixed>  $perfil
     * @return list<array<string, mixed>>
     */
    private function listarPorClavesDocumento(
        string $tabla,
        string $campos,
        string $colTipo,
        string $colLetra,
        string $colSuc,
        string $colNro,
        array $clavesDocumento,
        array $perfil,
    ): array {
        $clavesDocumento = array_values(array_unique(array_filter($clavesDocumento)));
        if ($clavesDocumento === []) {
            return [];
        }

        $out = [];
        foreach (array_chunk($clavesDocumento, 40) as $chunk) {
            $ors = [];
            foreach ($chunk as $clave) {
                $partes = explode('|', $clave);
                if (count($partes) < 4) {
                    continue;
                }
                [$tipo, $letra, $suc, $nro] = $partes;
                $ors[] = '('.$colTipo." = '".$this->esc($tipo)
                    ."' AND ".$colLetra." = '".$this->esc($letra)
                    ."' AND ".$colSuc.' = '.(int) $suc
                    .' AND '.$colNro.' = '.(int) $nro.')';
            }
            if ($ors === []) {
                continue;
            }
            foreach ($this->listar(
                $tabla,
                $campos,
                ' WHERE ('.implode(' OR ', $ors).')',
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
                $filas = $this->aArrays(ApiAnita::decodificarListaFilas($raw));
                if (count($filas) > count($mejor)) {
                    $mejor = $filas;
                }
                if ($filas !== [] || $i === $intentos) {
                    return $mejor !== [] ? $mejor : $filas;
                }
            } elseif ($i === $intentos) {
                throw new RuntimeException(
                    'Anita bridge '.$tabla.': '.$ultimoErr
                    .' (campos='.$campos.'; entorno='.$perfil['entorno'].')'
                );
            }
            usleep(200000);
        }

        if ($ultimoErr !== null) {
            throw new RuntimeException('Anita bridge '.$tabla.': '.$ultimoErr);
        }

        return $mejor;
    }

    /**
     * @param  list<object|array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    private function aArrays(array $filas): array
    {
        $out = [];
        foreach ($filas as $fila) {
            $out[] = (array) $fila;
        }

        return $out;
    }

    private function esc(string $valor): string
    {
        return str_replace("'", "''", $valor);
    }
}
