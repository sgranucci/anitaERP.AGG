<?php

declare(strict_types=1);

namespace App\Support\Ventas;

use App\ApiAnita;
use App\Support\Compras\AnitaImport\PagoproveedorAnitaImportEmpresaSupport;
use App\Support\Contable\Sicore\SicoreEmpresaAnitaSupport;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportClaveSupport;
use App\Support\Ventas\AnitaImport\ClienteCuentacorrienteAnitaImportFormatoSupport;
use Illuminate\Support\Facades\Log;

/**
 * Lectura Anita de venta + pago/auxpag para Facturación y cobranzas (l-factycobr).
 */
final class FacturacionCobranzasAnitaBridgeReader
{
    private const VENTA_CAMPOS = 'ven_cliente,ven_tipo,ven_letra,ven_sucursal,ven_nro,ven_fecha,ven_monto,'
        .'ven_gravado,ven_gravado_ot,ven_tot_abasto,ven_cod_mon,ven_cotizacion,ven_nombre_cliente,'
        .'ven_direccion_cli,ven_transporte,ven_empresa';

    private const PAGO_CAMPOS = 'pag_empresa,pag_fecha,pag_tipo,pag_letra,pag_sucursal,pag_rec,pag_pro,'
        .'pag_entregado_a,pag_trec,pag_cotizacion,pag_transporte';

    /** axp_tipo_ap = tipo aplicación (CHT/BFR/…); campos mínimos compatibles El Bierzo. */
    private const AUXPAG_CAMPOS = 'axp_pro,axp_fecha,axp_rec,axp_tipo,axp_tipo_ap,axp_monto_ap,axp_sucursal';

    public function __construct(
        private readonly ApiAnita $api = new ApiAnita,
    ) {}

    /**
     * @param  list<string>  $errores
     * @return list<array<string, mixed>>
     */
    public function listarVentas(
        int $empresaId,
        string $fechaDesdeYmd,
        string $fechaHastaYmd,
        array &$errores = [],
    ): array {
        $desde = ClienteCuentacorrienteAnitaImportClaveSupport::fechaAnitaDesdeIso($fechaDesdeYmd);
        $hasta = ClienteCuentacorrienteAnitaImportClaveSupport::fechaAnitaDesdeIso($fechaHastaYmd);
        if ($desde <= 0 || $hasta <= 0) {
            return [];
        }

        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        $where = ' WHERE ven_fecha BETWEEN '.$desde.' AND '.$hasta
            .' AND '.$this->whereEmpresaCampo('ven_empresa', $empresaAnita);

        $perfil = ClienteCuentacorrienteAnitaImportFormatoSupport::perfil();
        $filas = $this->listar(
            (string) ($perfil['sistema'] ?? 'ventas'),
            'venta',
            self::VENTA_CAMPOS,
            $where,
            'ven_fecha, ven_tipo, ven_sucursal, ven_nro',
            $errores,
            'factycobr-venta',
        );

        $out = [];
        foreach ($filas as $fila) {
            $tipo = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($fila['ven_tipo'] ?? ''));
            if ($tipo === '' || $tipo === 'PRE') {
                continue;
            }
            $direccion = strtoupper(trim((string) ($fila['ven_direccion_cli'] ?? '')));
            if (str_starts_with($direccion, 'MOVIMIENTO ANULADO')) {
                continue;
            }
            $out[] = $fila;
        }

        return $out;
    }

    /**
     * Cobranzas Anita (COB / COC / COA) del período.
     *
     * @param  list<string>  $errores
     * @return list<array<string, mixed>>
     */
    public function listarCobranzas(
        int $empresaId,
        string $fechaDesdeYmd,
        string $fechaHastaYmd,
        array &$errores = [],
    ): array {
        $desde = ClienteCuentacorrienteAnitaImportClaveSupport::fechaAnitaDesdeIso($fechaDesdeYmd);
        $hasta = ClienteCuentacorrienteAnitaImportClaveSupport::fechaAnitaDesdeIso($fechaHastaYmd);
        if ($desde <= 0 || $hasta <= 0) {
            return [];
        }

        $empresaAnita = SicoreEmpresaAnitaSupport::codigoEmpresaAnita($empresaId);
        $where = " WHERE pag_fecha BETWEEN {$desde} AND {$hasta}"
            ." AND (pag_tipo = 'COB' OR pag_tipo = 'COC' OR pag_tipo = 'COA')"
            .' AND '.$this->whereEmpresaCampo('pag_empresa', $empresaAnita);

        $pagos = $this->listar(
            'che_ban',
            'pago',
            self::PAGO_CAMPOS,
            $where,
            'pag_fecha, pag_tipo, pag_rec',
            $errores,
            'factycobr-pago',
        );
        if ($pagos === []) {
            return [];
        }

        $auxIndex = $this->indexarAuxpag($empresaAnita, $desde, $hasta, $errores);

        $out = [];
        foreach ($pagos as $pago) {
            // Clave sin sucursal: en El Bierzo axp_sucursal del efectivo suele ser 0
            // aunque pag_sucursal sea 1 (l-factycobr filtra por sucursal, pero muchas
            // líneas de medio quedan en 0 y se perderían).
            $claveMedios = $this->claveAuxpag(
                (string) ($pago['pag_pro'] ?? ''),
                (int) ($pago['pag_fecha'] ?? 0),
                (int) ($pago['pag_rec'] ?? 0),
                (string) ($pago['pag_tipo'] ?? ''),
            );
            $medios = $auxIndex[$claveMedios] ?? FacturacionCobranzasMediosSupport::vacios();
            $total = (float) ($pago['pag_trec'] ?? 0);
            $pago['_medios'] = FacturacionCobranzasMediosSupport::cerrarConTotalRecibo($medios, $total);
            $out[] = $pago;
        }

        return $out;
    }

    /**
     * @param  list<string>  $errores
     * @return array<string, array{efectivo: float, cheques: float, valores: float, varios: float, total: float}>
     */
    private function indexarAuxpag(int $empresaAnita, int $desde, int $hasta, array &$errores): array
    {
        $campos = self::AUXPAG_CAMPOS;
        // Sin filtrar axp_tipo en Informix (OR en rango grande falla en el bridge);
        // se descarta OPP/OPA en PHP.
        $where = ' WHERE axp_fecha BETWEEN '.$desde.' AND '.$hasta;
        if (PagoproveedorAnitaImportEmpresaSupport::auxpagTieneEmpresa()) {
            $where .= ' AND '.$this->whereEmpresaCampo('axp_empresa', $empresaAnita);
            $campos .= ',axp_empresa';
        }

        $filas = $this->listar(
            'che_ban',
            'auxpag',
            $campos,
            $where,
            'axp_fecha, axp_rec',
            $errores,
            'factycobr-auxpag',
        );

        $index = [];
        foreach ($filas as $fila) {
            $tipoRec = ClienteCuentacorrienteAnitaImportClaveSupport::tipo((string) ($fila['axp_tipo'] ?? ''));
            if (! in_array($tipoRec, ['COB', 'COC', 'COA'], true)) {
                continue;
            }
            $tipoApli = (string) ($fila['axp_tipo_ap'] ?? $fila['axp_tipo_apli'] ?? '');
            $bucket = FacturacionCobranzasMediosSupport::bucketDesdeTipoApli($tipoApli);
            if ($bucket === null) {
                continue;
            }
            $clave = $this->claveAuxpag(
                (string) ($fila['axp_pro'] ?? ''),
                (int) ($fila['axp_fecha'] ?? 0),
                (int) ($fila['axp_rec'] ?? 0),
                (string) ($fila['axp_tipo'] ?? ''),
            );
            if (! isset($index[$clave])) {
                $index[$clave] = FacturacionCobranzasMediosSupport::vacios();
            }
            $index[$clave][$bucket] = round(
                (float) ($index[$clave][$bucket] ?? 0) + (float) ($fila['axp_monto_ap'] ?? 0),
                2,
            );
        }

        return $index;
    }

    private function claveAuxpag(string $pro, int $fecha, int $rec, string $tipo): string
    {
        return implode('|', [
            ClienteCuentacorrienteAnitaImportClaveSupport::clienteCodigoAnita($pro),
            (string) $fecha,
            (string) $rec,
            ClienteCuentacorrienteAnitaImportClaveSupport::tipo($tipo),
        ]);
    }

    /**
     * El Bierzo / monoempresa suelen grabar *_empresa = 0.
     * Siempre incluye 0 + el código Anita de la empresa ERP del filtro.
     */
    private function whereEmpresaCampo(string $campo, int $empresaAnita): string
    {
        $ids = [0];
        if ($empresaAnita > 0) {
            $ids[] = $empresaAnita;
        }
        $ids = array_values(array_unique(array_map('intval', $ids)));

        return $campo.' IN ('.implode(',', $ids).')';
    }

    /**
     * @param  list<string>  $errores
     * @return list<array<string, mixed>>
     */
    private function listar(
        string $sistema,
        string $tabla,
        string $campos,
        string $whereArmado,
        string $orderBy,
        array &$errores,
        string $contexto,
    ): array {
        try {
            $raw = $this->api->apiCall([
                'acc' => 'list',
                'sistema' => $sistema,
                'tabla' => $tabla,
                'campos' => $campos,
                'whereArmado' => $whereArmado,
                'orderBy' => $orderBy,
            ]);
        } catch (\Throwable $e) {
            $errores[] = "Anita {$contexto}: ".$e->getMessage();
            Log::warning('FacturacionCobranzasAnitaBridgeReader', [
                'contexto' => $contexto,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        $msg = ApiAnita::extraerMensajeError(is_string($raw) ? $raw : null);
        if ($msg !== null) {
            $errores[] = "Anita {$contexto}: {$msg}";

            return [];
        }

        $filas = ApiAnita::decodificarListaFilas(is_string($raw) ? $raw : '');
        $out = [];
        foreach ($filas as $fila) {
            if (is_array($fila)) {
                $out[] = $fila;
            } elseif (is_object($fila)) {
                $out[] = (array) $fila;
            }
        }

        return $out;
    }
}
