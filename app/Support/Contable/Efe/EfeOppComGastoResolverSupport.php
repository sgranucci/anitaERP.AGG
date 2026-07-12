<?php

namespace App\Support\Contable\Efe;

use App\Support\Contable\MayorConcepto\MayorConceptoAnitaBridgeReader;
use App\Support\Contable\MayorConcepto\MayorConceptoMemoriaMotor;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Resuelve cuenta/concepto de gasto de un OPP vía auxpag → aplicped → COM → subdiario (patrón Anita PERFORM).
 */
class EfeOppComGastoResolverSupport
{
    private const CONCEPTO_PUBLICIDAD = 13;

    private const CONCEPTO_GAMING_SUPPLIES = 12;

    private const CONCEPTO_HONORARIOS = 7;

    /** @var array<string, list<object>> */
    private array $auxpagPorRec = [];

    /** @var array<string, list<object>> */
    private array $aplicpedCache = [];

    /** @var array<string, list<object>> */
    private array $comSubdiarioCache = [];

    /** @var array<int, array<int, int>> */
    private array $conceptoPorCuenta = [];

    /** @var array<int, array{nombre: string, codigo: string}> */
    private array $datosCuenta = [];

    private int $empresaId = 0;

    public function __construct(
        private readonly MayorConceptoAnitaBridgeReader $bridgeReader = new MayorConceptoAnitaBridgeReader(),
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function preparar(array $filtros): void
    {
        $this->empresaId = (int) ($filtros['empresa_id'] ?? 0);
        $mes = (int) ($filtros['mes'] ?? 0);
        $anio = (int) ($filtros['anio'] ?? 0);
        if ($this->empresaId <= 0 || $mes <= 0 || $anio <= 0) {
            return;
        }

        $this->auxpagPorRec = [];
        $this->aplicpedCache = [];
        $this->comSubdiarioCache = [];
        $this->conceptoPorCuenta = [];
        $this->datosCuenta = [];

        $inicio = Carbon::createFromDate($anio, $mes, 1);
        $errores = [];
        $bridge = $this->bridgeReader->cargarPeriodo(
            $this->empresaId,
            (int) $inicio->format('Ymd'),
            (int) $inicio->copy()->endOfMonth()->format('Ymd'),
        );

        foreach ($bridge['auxpag'] ?? [] as $aplicacion) {
            $rec = trim((string) ($aplicacion->axp_rec ?? ''));
            if ($rec === '') {
                continue;
            }

            $this->auxpagPorRec[$rec][] = $aplicacion;
        }

        $this->indexarConceptosAnita($bridge['ctaconc'] ?? []);
        $this->precargarAplicpedYCom($errores);
    }

    /**
     * @return array{cuenta: int, concepto_id: int, cuenta_codigo: string, cuenta_nombre: string}|null
     */
    public function resolverPorRec(string $rec): ?array
    {
        $rec = trim($rec);
        if ($rec === '' || ! isset($this->auxpagPorRec[$rec])) {
            return null;
        }

        $mejor = null;
        $mejorMonto = 0.0;

        foreach ($this->auxpagPorRec[$rec] as $aplicacion) {
            $tipoAp = strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? '')));
            if (! in_array($tipoAp, MayorConceptoMemoriaMotor::TIPOS_FACTURA_APLICADA, true)) {
                continue;
            }

            if (in_array($tipoAp, MayorConceptoMemoriaMotor::TIPOS_AUXPAG_IGNORAR, true)
                || in_array($tipoAp, MayorConceptoMemoriaMotor::TIPOS_MEDIO_PAGO_AUXPAG, true)) {
                continue;
            }

            $resuelto = $this->resolverDesdeAplicacion($aplicacion);
            if ($resuelto === null) {
                continue;
            }

            $monto = (float) ($aplicacion->axp_monto_ap ?? 0);
            if ($monto >= $mejorMonto) {
                $mejorMonto = $monto;
                $mejor = $resuelto;
            }
        }

        return $this->ajustarConceptoPorRec($rec, $mejor);
    }

    /**
     * @param  array{cuenta: int, concepto_id: int, cuenta_codigo: string, cuenta_nombre: string}|null  $resuelto
     * @return array{cuenta: int, concepto_id: int, cuenta_codigo: string, cuenta_nombre: string}|null
     */
    private function ajustarConceptoPorRec(string $rec, ?array $resuelto): ?array
    {
        if ($resuelto === null) {
            return null;
        }

        $conceptoId = (int) ($resuelto['concepto_id'] ?? 0);
        if ($conceptoId === self::CONCEPTO_GAMING_SUPPLIES && $this->recEsChequePublicidad13($rec)) {
            $resuelto['concepto_id'] = self::CONCEPTO_PUBLICIDAD;
        }

        if ($conceptoId === 65 && $this->recTieneFis65ConTmb($rec)) {
            $resuelto['concepto_id'] = 24;
        }

        return $resuelto;
    }

    private function recTieneFis65ConTmb(string $rec): bool
    {
        $tieneTmb = false;
        $tieneFis65 = false;

        foreach ($this->auxpagPorRec[$rec] ?? [] as $aplicacion) {
            $tipoAp = strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? '')));
            if ($tipoAp === 'TMB') {
                $tieneTmb = true;
            }

            if ($tipoAp === 'FIS' && (int) ($aplicacion->axp_concepto ?? 0) === 65) {
                $tieneFis65 = true;
            }
        }

        return $tieneTmb && $tieneFis65;
    }

    private function recEsChequePublicidad13(string $rec): bool
    {
        $tieneChp = false;
        $tieneFib12 = false;
        $tieneFis = false;

        foreach ($this->auxpagPorRec[$rec] ?? [] as $aplicacion) {
            $tipoAp = strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? '')));
            if ($tipoAp === 'CHP') {
                $tieneChp = true;
            }

            if ($tipoAp === 'FIB' && (int) ($aplicacion->axp_concepto ?? 0) === self::CONCEPTO_GAMING_SUPPLIES) {
                $tieneFib12 = true;
            }

            if ($tipoAp === 'FIS') {
                $tieneFis = true;
            }
        }

        return $tieneChp && $tieneFib12 && ! $tieneFis;
    }

    /**
     * @return array{cuenta: int, concepto_id: int, cuenta_codigo: string, cuenta_nombre: string}|null
     */
    private function resolverDesdeAplicacion(object $aplicacion): ?array
    {
        $conceptoAuxpag = (int) ($aplicacion->axp_concepto ?? 0);
        $cuentaCom = $this->resolverCuentaGastoCom($aplicacion);

        if ($cuentaCom <= 0 && $conceptoAuxpag <= 0) {
            return null;
        }

        $cuenta = $cuentaCom > 0 ? $cuentaCom : 0;
        $conceptoId = $this->resolverConceptoGasto($cuenta, $conceptoAuxpag);

        if ($conceptoId <= 0) {
            return null;
        }

        if ($cuenta <= 0) {
            return [
                'cuenta' => 0,
                'concepto_id' => $conceptoId,
                'cuenta_codigo' => '',
                'cuenta_nombre' => '',
            ];
        }

        $datos = $this->datosCuenta[$cuenta] ?? ['nombre' => '', 'codigo' => $this->formatearCodigoCuenta($cuenta)];

        return [
            'cuenta' => $cuenta,
            'concepto_id' => $conceptoId,
            'cuenta_codigo' => $datos['codigo'],
            'cuenta_nombre' => $datos['nombre'],
        ];
    }

    private function resolverCuentaGastoCom(object $aplicacion): int
    {
        $mejorCuenta = 0;
        $mejorImporte = 0.0;

        foreach ($this->resolverClavesComDesdeFactura($aplicacion) as $claveCom) {
            foreach ($this->comSubdiarioCache[$claveCom] ?? [] as $linea) {
                $cuenta = (int) ($linea->subd_cuenta ?? 0);
                $mov = strtoupper(trim((string) ($linea->subd_tipo_mov ?? '')));
                $importe = (float) ($linea->subd_importe ?? 0);

                if ($mov !== 'D' || $importe <= 0) {
                    continue;
                }

                if ($cuenta >= 214010000 && $cuenta < 215000000) {
                    continue;
                }

                if ($this->esCuentaPuenteAnticipo($cuenta)) {
                    continue;
                }

                if ($this->esCuentaGastoCom($cuenta) && $importe >= $mejorImporte) {
                    $mejorImporte = $importe;
                    $mejorCuenta = $cuenta;
                }
            }
        }

        return $mejorCuenta;
    }

    private function esCuentaPuenteAnticipo(int $cuenta): bool
    {
        if ($cuenta >= 114020000 && $cuenta < 114021000) {
            return false;
        }

        return $cuenta >= 114000000 && $cuenta < 115000000;
    }

    private function esCuentaGastoCom(int $cuenta): bool
    {
        if ($cuenta >= 114020000 && $cuenta < 114021000) {
            return true;
        }

        return $cuenta >= 521000000 && $cuenta < 600000000 && $cuenta !== 521130001;
    }

    private function resolverConceptoGasto(int $cuenta, int $conceptoAuxpag): int
    {
        if ($cuenta >= 114020000 && $cuenta < 114021000) {
            return self::CONCEPTO_HONORARIOS;
        }

        if ($conceptoAuxpag > 0) {
            return $conceptoAuxpag;
        }

        return (int) ($this->conceptoPorCuenta[$this->empresaId][$cuenta] ?? 0);
    }

    /**
     * @return list<string>
     */
    private function resolverClavesComDesdeFactura(object $aplicacion): array
    {
        $prov = trim((string) ($aplicacion->axp_pro ?? ''));
        $tipoAp = trim((string) ($aplicacion->axp_tipo_ap ?? ''));
        $letraAp = trim((string) ($aplicacion->axp_letra_comp ?? ' '));
        $sucAp = (int) ($aplicacion->axp_sucursal ?? 0);
        $nroAp = (int) ($aplicacion->axp_nro ?? 0);

        if ($prov === '' || $tipoAp === '' || $nroAp <= 0) {
            return [];
        }

        $claveFac = $prov.'|'.$tipoAp.'|'.$letraAp.'|'.$sucAp.'|'.$nroAp;
        $aplicaciones = $this->aplicpedCache[$claveFac] ?? [];

        $visitados = [];
        $pendientes = [[$tipoAp, $letraAp, $sucAp, $nroAp]];
        $clavesCom = [];

        while ($pendientes !== []) {
            [$tipo, $letra, $suc, $nro] = array_shift($pendientes);
            $claveDoc = $prov.'|'.$tipo.'|'.$letra.'|'.$suc.'|'.$nro;
            if (isset($visitados[$claveDoc])) {
                continue;
            }
            $visitados[$claveDoc] = true;

            foreach ($this->aplicpedCache[$claveDoc] ?? [] as $apl) {
                $refTipo = trim((string) ($apl->aplp_ref_tipo ?? ''));
                $refLetra = trim((string) ($apl->aplp_ref_letra ?? ' '));
                $refSuc = (int) ($apl->aplp_ref_sucursal ?? 0);
                $refNro = (int) ($apl->aplp_ref_nro ?? 0);

                if ($refTipo === 'COM' && $refNro > 0) {
                    $clavesCom[$refTipo.'|'.$refLetra.'|'.$refSuc.'|'.$refNro] = true;

                    continue;
                }

                if ($refTipo !== '' && $refNro > 0) {
                    $pendientes[] = [$refTipo, $refLetra, $refSuc, $refNro];
                }
            }
        }

        return array_keys($clavesCom);
    }

    /**
     * @param  list<string>  $errores
     */
    private function precargarAplicpedYCom(array &$errores): void
    {
        $facturas = [];

        foreach ($this->auxpagPorRec as $aplicaciones) {
            foreach ($aplicaciones as $aplicacion) {
                $tipoAp = strtoupper(trim((string) ($aplicacion->axp_tipo_ap ?? '')));
                if (! in_array($tipoAp, MayorConceptoMemoriaMotor::TIPOS_FACTURA_APLICADA, true)) {
                    continue;
                }

                if (in_array($tipoAp, MayorConceptoMemoriaMotor::TIPOS_AUXPAG_IGNORAR, true)
                    || in_array($tipoAp, MayorConceptoMemoriaMotor::TIPOS_MEDIO_PAGO_AUXPAG, true)) {
                    continue;
                }

                $prov = trim((string) ($aplicacion->axp_pro ?? ''));
                $letraAp = trim((string) ($aplicacion->axp_letra_comp ?? ' '));
                $sucAp = (int) ($aplicacion->axp_sucursal ?? 0);
                $nroAp = (int) ($aplicacion->axp_nro ?? 0);
                if ($prov === '' || $nroAp <= 0) {
                    continue;
                }

                $facturas[] = [$prov, $tipoAp, $letraAp, $sucAp, $nroAp];
            }
        }

        if ($facturas === []) {
            return;
        }

        foreach ($this->bridgeReader->cargarAplicpedPorFacturas($facturas, $errores) as $apl) {
            $prov = trim((string) ($apl->aplp_proveedor ?? ''));
            $tipo = trim((string) ($apl->aplp_tipo ?? ''));
            $letra = trim((string) ($apl->aplp_letra ?? ' '));
            $suc = (int) ($apl->aplp_sucursal ?? 0);
            $nro = (int) ($apl->aplp_nro ?? 0);
            if ($prov === '' || $tipo === '' || $nro <= 0) {
                continue;
            }

            $this->aplicpedCache[$prov.'|'.$tipo.'|'.$letra.'|'.$suc.'|'.$nro][] = $apl;
        }

        $clavesCom = [];
        foreach ($this->auxpagPorRec as $aplicaciones) {
            foreach ($aplicaciones as $aplicacion) {
                foreach ($this->resolverClavesComDesdeFactura($aplicacion) as $claveCom) {
                    $clavesCom[$claveCom] = true;
                }
            }
        }

        $this->comSubdiarioCache = $this->bridgeReader->cargarComSubdiarioLote($this->empresaId, array_keys($clavesCom), $errores);
    }

    /**
     * @param  list<object>  $ctaconc
     */
    private function indexarConceptosAnita(array $ctaconc): void
    {
        foreach ($ctaconc as $fila) {
            $empresa = (int) ($fila->ctaco_empresa ?? 0);
            $cuenta = (int) ($fila->ctaco_cuenta ?? 0);
            $concepto = (int) ($fila->ctaco_concepto ?? 0);
            if ($empresa <= 0 || $cuenta <= 0 || $concepto <= 0) {
                continue;
            }

            $this->conceptoPorCuenta[$empresa][$cuenta] = $concepto;
        }

        foreach (DB::table('cuentacontable')
            ->where('empresa_id', $this->empresaId)
            ->whereNotNull('conceptogasto_id')
            ->where('conceptogasto_id', '>', 0)
            ->get(['codigo', 'nombre', 'conceptogasto_id']) as $row) {
            $codigo = (int) $row->codigo;
            if (! isset($this->conceptoPorCuenta[$this->empresaId][$codigo])) {
                $this->conceptoPorCuenta[$this->empresaId][$codigo] = (int) $row->conceptogasto_id;
            }

            if (! isset($this->datosCuenta[$codigo])) {
                $this->datosCuenta[$codigo] = [
                    'nombre' => trim((string) ($row->nombre ?? '')),
                    'codigo' => $this->formatearCodigoCuenta($codigo),
                ];
            }
        }

        $cuentas = [];
        foreach ($this->conceptoPorCuenta[$this->empresaId] ?? [] as $cuenta => $_) {
            $cuentas[] = $cuenta;
        }

        if ($cuentas === []) {
            return;
        }

        foreach (DB::table('cuentacontable')
            ->where('empresa_id', $this->empresaId)
            ->whereIn('codigo', array_unique($cuentas))
            ->get(['codigo', 'nombre']) as $row) {
            $codigo = (int) $row->codigo;
            $this->datosCuenta[$codigo] = [
                'nombre' => trim((string) ($row->nombre ?? '')),
                'codigo' => $this->formatearCodigoCuenta($codigo),
            ];
        }
    }

    private function formatearCodigoCuenta(int $cuenta): string
    {
        $s = str_pad((string) $cuenta, 9, '0', STR_PAD_LEFT);

        return substr($s, 0, 6).'-'.substr($s, 6);
    }
}
