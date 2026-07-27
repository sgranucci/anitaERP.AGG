<?php

namespace App\Support\Contable\MayorPlanoCuenta;

use App\Support\Contable\MayorConcepto\MayorConceptoAnitaBridgeReader;
use App\Support\Contable\MayorConcepto\MayorConceptoMediopagoSupport;
use App\Support\Contable\MayorConcepto\MayorConceptoMemoriaMotor;

/**
 * Resuelve nro_oc en movimientos del mayor plano vía auxpag + aplicped (patrón mayor por concepto).
 */
class MayorPlanoCuentaOrdencompraResolver
{
    /** @var array<string, list<object>> */
    private array $auxpagPorOp = [];

    /** @var array<string, list<object>> */
    private array $auxpagPorDocumento = [];

    /** @var array<string, list<object>> */
    private array $aplicpedCache = [];

    /** @var array<string, array<string, int>> */
    private array $ordenesComPorFactura = [];

    /** @var list<string> */
    private array $erroresBridge = [];

    private int $consultasBridgeIndividuales = 0;

    private int $movimientosResueltos = 0;

    public function __construct(
        private readonly MayorConceptoAnitaBridgeReader $reader = new MayorConceptoAnitaBridgeReader(),
        private readonly MayorConceptoMediopagoSupport $mediopagoSupport = new MayorConceptoMediopagoSupport(),
    ) {
    }

    /**
     * @param  list<object>  $auxpag
     * @param  list<string>  $erroresBridge
     * @return array<string, int>
     */
    public function preparar(array $auxpag, array &$erroresBridge): array
    {
        $this->erroresBridge = &$erroresBridge;
        $this->auxpagPorOp = [];
        $this->auxpagPorDocumento = [];
        $this->aplicpedCache = [];
        $this->ordenesComPorFactura = [];
        $this->consultasBridgeIndividuales = 0;
        $this->movimientosResueltos = 0;

        foreach ($auxpag as $axp) {
            $claveOp = $this->claveOperacionPago(
                trim((string) ($axp->axp_tipo ?? '')),
                (int) ($axp->axp_rec ?? 0),
                (int) ($axp->axp_fecha ?? 0),
            );
            $this->auxpagPorOp[$claveOp][] = $axp;

            if (! $this->esFactura($axp)) {
                continue;
            }

            $claveDoc = $this->claveDocumentoCompras(
                trim((string) ($axp->axp_pro ?? '')),
                trim((string) ($axp->axp_tipo_ap ?? '')),
                trim((string) ($axp->axp_letra_comp ?? ' ')),
                (int) ($axp->axp_sucursal ?? 0),
                (int) ($axp->axp_nro ?? 0),
            );
            if ($claveDoc !== '') {
                $this->auxpagPorDocumento[$claveDoc][] = $axp;
            }
        }

        $statsPreload = $this->precargarCachesCompras($auxpag);

        return array_merge([
            'auxpag_filas' => count($auxpag),
            'movimientos_oc_resueltos' => 0,
        ], $statsPreload);
    }

    /**
     * @param  list<array<string, mixed>>  $movimientos
     * @return list<array<string, mixed>>
     */
    public function aplicarAMovimientos(array $movimientos): array
    {
        $this->movimientosResueltos = 0;

        foreach ($movimientos as $idx => $mov) {
            if ((int) ($mov['nro_oc'] ?? 0) > 0) {
                continue;
            }

            $nroOc = $this->resolverNroOc($mov);
            if ($nroOc > 0) {
                $movimientos[$idx]['nro_oc'] = $nroOc;
                $this->movimientosResueltos++;
            }
        }

        return $movimientos;
    }

    public function cantidadMovimientosResueltos(): int
    {
        return $this->movimientosResueltos;
    }

    /**
     * @param  array<string, mixed>  $mov
     */
    public function resolverNroOc(array $mov): int
    {
        $existente = (int) ($mov['nro_oc'] ?? 0);
        if ($existente > 0) {
            return $existente;
        }

        $tipo = strtoupper(trim((string) ($mov['tipo_comp'] ?? '')));
        $letra = trim((string) ($mov['letra'] ?? ' '));
        $sucursal = (int) ($mov['sucursal'] ?? 0);
        $nro = (int) ($mov['nro'] ?? 0);
        $fecha = (int) ($mov['fecha'] ?? 0);
        $emisor = trim((string) ($mov['emisor'] ?? ''));

        if ($tipo === 'COM' && $nro > 0) {
            $orden = $this->ordenDesdeDocumentoCompras($emisor, $tipo, $letra, $sucursal, $nro);
            if ($orden > 0) {
                return $orden;
            }
        }

        if (in_array($tipo, MayorConceptoMemoriaMotor::TIPOS_REF_IMPUTABLE, true) && $nro > 0) {
            $claveOp = $this->claveOperacionPago($tipo, $nro, $fecha);
            foreach ($this->auxpagPorOp[$claveOp] ?? [] as $axp) {
                if (! $this->esFactura($axp)) {
                    continue;
                }
                $orden = $this->ordenComDesdeAplicacion($axp);
                if ($orden > 0) {
                    return $orden;
                }
            }
        }

        if ($emisor !== '' && $nro > 0 && $tipo !== '') {
            $claveDoc = $this->claveDocumentoCompras($emisor, $tipo, $letra, $sucursal, $nro);
            if ($claveDoc !== '') {
                foreach ($this->auxpagPorDocumento[$claveDoc] ?? [] as $axp) {
                    $orden = $this->ordenComDesdeAplicacion($axp);
                    if ($orden > 0) {
                        return $orden;
                    }
                }
            }

            // Documento de compras en subdiario/ctamov: cadena aplicped (DNS→PEP→COM, FGA→PEP→COM, etc.).
            // No limitar a TIPOS_FACTURA_APLICADA: débitos como DNS tienen OC en aplicped
            // aunque no figuren como factura aplicada en auxpag.
            if (! MayorPlanoCuentaSupport::esTipoOrdenPago($tipo)
                && ! $this->mediopagoSupport->esMedioPagoAuxpag($tipo)
                && ! $this->mediopagoSupport->esAuxpagIgnorado($tipo)
            ) {
                $orden = $this->ordenDesdeDocumentoCompras($emisor, $tipo, $letra, $sucursal, $nro);
                if ($orden > 0) {
                    return $orden;
                }
            }
        }

        return 0;
    }

    /**
     * @param  list<object>  $auxpagLista
     * @return array<string, int>
     */
    private function precargarCachesCompras(array $auxpagLista): array
    {
        $seeds = [];

        foreach ($auxpagLista as $axp) {
            if (! $this->esFactura($axp)) {
                continue;
            }

            $prov = trim((string) ($axp->axp_pro ?? ''));
            $tipoAp = trim((string) ($axp->axp_tipo_ap ?? ''));
            $letraAp = trim((string) ($axp->axp_letra_comp ?? ' '));
            $sucAp = (int) ($axp->axp_sucursal ?? 0);
            $nroAp = (int) ($axp->axp_nro ?? 0);

            if ($prov === '' || $tipoAp === '' || $nroAp <= 0) {
                continue;
            }

            $clave = $prov.'|'.$tipoAp.'|'.$letraAp.'|'.$sucAp.'|'.$nroAp;
            $seeds[$clave] = [$prov, $tipoAp, $letraAp, $sucAp, $nroAp];
        }

        if ($seeds === []) {
            return [
                'aplicped_precargadas' => 0,
                'bridge_consultas_individuales' => 0,
            ];
        }

        $visitados = [];
        $pendientes = array_values($seeds);

        while ($pendientes !== []) {
            $lote = [];
            while ($pendientes !== [] && count($lote) < 30) {
                $doc = array_shift($pendientes);
                $claveDoc = $doc[0].'|'.$doc[1].'|'.$doc[2].'|'.$doc[3].'|'.$doc[4];
                if (isset($visitados[$claveDoc])) {
                    continue;
                }
                $visitados[$claveDoc] = true;
                $lote[] = $doc;
            }

            if ($lote === []) {
                break;
            }

            foreach ($this->reader->cargarAplicpedPorFacturas($lote, $this->erroresBridge) as $apl) {
                $clave = $this->claveDocumentoCompras(
                    trim((string) ($apl->aplp_proveedor ?? '')),
                    trim((string) ($apl->aplp_tipo ?? '')),
                    trim((string) ($apl->aplp_letra ?? ' ')),
                    (int) ($apl->aplp_sucursal ?? 0),
                    (int) ($apl->aplp_nro ?? 0),
                );
                if ($clave === '') {
                    continue;
                }
                $this->aplicpedCache[$clave][] = $apl;

                $refTipo = trim((string) ($apl->aplp_ref_tipo ?? ''));
                $refNro = (int) ($apl->aplp_ref_nro ?? 0);
                if ($refTipo === '' || $refNro <= 0 || $refTipo === 'COM') {
                    continue;
                }

                $prov = trim((string) ($apl->aplp_proveedor ?? ''));
                $refLetra = trim((string) ($apl->aplp_ref_letra ?? ' '));
                $refSuc = (int) ($apl->aplp_ref_sucursal ?? 0);
                $claveRef = $prov.'|'.$refTipo.'|'.$refLetra.'|'.$refSuc.'|'.$refNro;
                if (! isset($visitados[$claveRef])) {
                    $pendientes[] = [$prov, $refTipo, $refLetra, $refSuc, $refNro];
                }
            }
        }

        return [
            'aplicped_precargadas' => array_sum(array_map('count', $this->aplicpedCache)),
            'bridge_consultas_individuales' => $this->consultasBridgeIndividuales,
        ];
    }

    private function ordenDesdeDocumentoCompras(
        string $proveedor,
        string $tipo,
        string $letra,
        int $sucursal,
        int $nro,
    ): int {
        if ($proveedor === '' || $tipo === '' || $nro <= 0) {
            return 0;
        }

        $axp = (object) [
            'axp_pro' => $proveedor,
            'axp_tipo_ap' => $tipo,
            'axp_letra_comp' => $letra,
            'axp_sucursal' => $sucursal,
            'axp_nro' => $nro,
        ];

        return $this->ordenComDesdeAplicacion($axp);
    }

    private function ordenComDesdeAplicacion(object $aplicacion): int
    {
        $prov = trim((string) ($aplicacion->axp_pro ?? ''));
        $tipoAp = trim((string) ($aplicacion->axp_tipo_ap ?? ''));
        $letraAp = trim((string) ($aplicacion->axp_letra_comp ?? ' '));
        $sucAp = (int) ($aplicacion->axp_sucursal ?? 0);
        $nroAp = (int) ($aplicacion->axp_nro ?? 0);
        $claveFac = $prov.'|'.$tipoAp.'|'.$letraAp.'|'.$sucAp.'|'.$nroAp;

        $this->resolverClavesComDesdeFactura($aplicacion);

        foreach ($this->ordenesComPorFactura[$claveFac] ?? [] as $orden) {
            if ($orden > 0) {
                return (int) $orden;
            }
        }

        if (! isset($this->aplicpedCache[$claveFac])) {
            $this->consultasBridgeIndividuales++;
            $this->aplicpedCache[$claveFac] = $this->reader->cargarAplicpedFactura(
                $prov, $tipoAp, $letraAp, $sucAp, $nroAp, $this->erroresBridge,
            );
        }

        foreach ($this->aplicpedCache[$claveFac] as $apl) {
            $orden = (int) ($apl->aplp_orden ?? 0);
            if ($orden > 0) {
                return $orden;
            }

            $refTipo = trim((string) ($apl->aplp_ref_tipo ?? ''));
            $refNro = (int) ($apl->aplp_ref_nro ?? 0);
            if (in_array($refTipo, ['PEP', 'COM'], true) && $refNro > 0) {
                return $refNro;
            }
        }

        return 0;
    }

    private function resolverClavesComDesdeFactura(object $aplicacion): void
    {
        $prov = trim((string) ($aplicacion->axp_pro ?? ''));
        $tipoAp = trim((string) ($aplicacion->axp_tipo_ap ?? ''));
        $letraAp = trim((string) ($aplicacion->axp_letra_comp ?? ' '));
        $sucAp = (int) ($aplicacion->axp_sucursal ?? 0);
        $nroAp = (int) ($aplicacion->axp_nro ?? 0);

        $claveFac = $prov.'|'.$tipoAp.'|'.$letraAp.'|'.$sucAp.'|'.$nroAp;
        if (! isset($this->aplicpedCache[$claveFac])) {
            $this->consultasBridgeIndividuales++;
            $this->aplicpedCache[$claveFac] = $this->reader->cargarAplicpedFactura(
                $prov, $tipoAp, $letraAp, $sucAp, $nroAp, $this->erroresBridge,
            );
        }

        if (! isset($this->ordenesComPorFactura[$claveFac])) {
            $this->ordenesComPorFactura[$claveFac] = [];
        }

        $visitados = [];
        $pendientes = [[$tipoAp, $letraAp, $sucAp, $nroAp]];

        while ($pendientes !== []) {
            [$tipo, $letra, $suc, $nro] = array_shift($pendientes);
            $claveDoc = $prov.'|'.$tipo.'|'.$letra.'|'.$suc.'|'.$nro;
            if (isset($visitados[$claveDoc])) {
                continue;
            }
            $visitados[$claveDoc] = true;

            if (! isset($this->aplicpedCache[$claveDoc])) {
                $this->consultasBridgeIndividuales++;
                $this->aplicpedCache[$claveDoc] = $this->reader->cargarAplicpedFactura(
                    $prov, $tipo, $letra, $suc, $nro, $this->erroresBridge,
                );
            }

            foreach ($this->aplicpedCache[$claveDoc] as $apl) {
                $refTipo = trim((string) ($apl->aplp_ref_tipo ?? ''));
                $refLetra = trim((string) ($apl->aplp_ref_letra ?? ' '));
                $refSuc = (int) ($apl->aplp_ref_sucursal ?? 0);
                $refNro = (int) ($apl->aplp_ref_nro ?? 0);
                $orden = (int) ($apl->aplp_orden ?? 0);

                if ($refTipo === 'COM') {
                    $claveCom = $refTipo.'|'.$refLetra.'|'.$refSuc.'|'.$refNro;
                    $this->ordenesComPorFactura[$claveFac][$claveCom] = $orden > 0 ? $orden : $refNro;

                    continue;
                }

                if ($refTipo !== '' && $refNro > 0) {
                    $pendientes[] = [$refTipo, $refLetra, $refSuc, $refNro];
                    if ($orden <= 0 && in_array($refTipo, ['PEP', 'COM'], true)) {
                        $this->ordenesComPorFactura[$claveFac]['orden|'.$refTipo.'|'.$refNro] = $refNro;
                    }
                }
            }
        }
    }

    private function claveOperacionPago(string $tipo, int $nro, int $fecha): string
    {
        return strtoupper(trim($tipo)).'|'.$nro.'|'.$fecha;
    }

    private function claveDocumentoCompras(
        string $proveedor,
        string $tipo,
        string $letra,
        int $sucursal,
        int $nro,
    ): string {
        if ($proveedor === '' || $tipo === '' || $nro <= 0) {
            return '';
        }

        return $proveedor.'|'.$tipo.'|'.$letra.'|'.$sucursal.'|'.$nro;
    }

    private function esFactura(object $fila): bool
    {
        $t = strtoupper(trim((string) ($fila->axp_tipo_ap ?? '')));

        if ($this->mediopagoSupport->esAuxpagIgnorado($t)
            || $this->mediopagoSupport->esMedioPagoAuxpag($t)) {
            return false;
        }

        return in_array($t, MayorConceptoMemoriaMotor::TIPOS_FACTURA_APLICADA, true);
    }
}
