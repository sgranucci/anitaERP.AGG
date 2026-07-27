<?php

namespace App\Services\Solicitudpago;

use App\ApiAnita;
use App\Models\Solicitudpago\Solicitudpago;
use App\Support\Compras\AnitaSync\AnitaUsuarioBridgeSupport;
use App\Support\Solicitudpago\SolicitudpagoAnitaFechaSupport;
use App\Support\Solicitudpago\SolicitudpagoArchivoStorageSupport;
use App\Support\Solicitudpago\SolicitudpagoEstados;
use App\Support\Solicitudpago\SolicitudpagoTratamientos;
use App\Traits\AnitaBridgeEscritura;
use Illuminate\Support\Facades\Auth;

/**
 * Escritura ERP → Anita che_ban (desacoplable via config solicitudpago.anita_escritura).
 */
class SolicitudpagoAnitaEscrituraService
{
    use AnitaBridgeEscritura;

    public function habilitada(): bool
    {
        return (bool) config('solicitudpago.anita_escritura', true);
    }

    public function sistema(): string
    {
        return (string) config('solicitudpago.anita_sistema', 'che_ban');
    }

    public function insertar(Solicitudpago $sp): void
    {
        if (! $this->habilitada()) {
            return;
        }

        $sp->loadMissing(['cuentas.cuentacontables', 'cuotas', 'archivos', 'empresas', 'proveedores', 'monedas', 'sectores', 'conceptos', 'formapagosol', 'madre']);
        $api = new ApiAnita();
        $this->apiCallAnitaEscritura($api, [
            'acc' => 'insert',
            'sistema' => $this->sistema(),
            'tabla' => 'solpagomae',
            'campos' => 'solpm_id, solpm_empresa, solpm_fecha, solpm_tratamiento, solpm_proveedor, solpm_concepto, '
                .'solpm_formapago, solpm_cod_mon, solpm_beneficiario, solpm_endoso, solpm_fecha_ent, solpm_fecha_vto, '
                .'solpm_monto, solpm_observacion, solpm_usuario_umod, solpm_fecha_umod, solpm_hora_umod, solpm_estado, '
                .'solpm_sector, solpm_fecha_alfa, solpm_detalle, solpm_id_sp_orig',
            'valores' => $this->valoresCabecera($sp),
        ], 'solpagomae insert');

        $this->reemplazarHijos($api, $sp);
        $this->insertarEstado($api, $sp, null, $sp->estado, 'Alta');
    }

    public function actualizar(Solicitudpago $sp, ?string $estadoAnterior = null): void
    {
        if (! $this->habilitada()) {
            return;
        }

        $sp->loadMissing(['cuentas.cuentacontables', 'cuotas', 'archivos', 'empresas', 'proveedores', 'monedas', 'sectores', 'conceptos', 'formapagosol', 'madre']);
        $api = new ApiAnita();
        $this->apiCallAnitaEscritura($api, [
            'acc' => 'update',
            'sistema' => $this->sistema(),
            'tabla' => 'solpagomae',
            'valores' => $this->setCabecera($sp),
            'whereArmado' => ' WHERE solpm_id = '.(int) $sp->codigo.' ',
        ], 'solpagomae update');

        $this->borrarHijosAnita($api, (int) $sp->codigo);
        $this->reemplazarHijos($api, $sp);

        if ($estadoAnterior !== null && $estadoAnterior !== $sp->estado) {
            $this->insertarEstado($api, $sp, $estadoAnterior, $sp->estado, 'Actualiza estado');
        }
    }

    public function eliminar(int $codigoAnita): void
    {
        if (! $this->habilitada() || $codigoAnita <= 0) {
            return;
        }

        $api = new ApiAnita();
        $this->borrarHijosAnita($api, $codigoAnita);
        $this->apiCallAnitaEscritura($api, [
            'acc' => 'delete',
            'sistema' => $this->sistema(),
            'tabla' => 'solpagoest',
            'whereArmado' => ' WHERE solpe_id = '.$codigoAnita.' ',
        ], 'solpagoest delete');
        $this->apiCallAnitaEscritura($api, [
            'acc' => 'delete',
            'sistema' => $this->sistema(),
            'tabla' => 'solpagomae',
            'whereArmado' => ' WHERE solpm_id = '.$codigoAnita.' ',
        ], 'solpagomae delete');
    }

    private function borrarHijosAnita(ApiAnita $api, int $codigo): void
    {
        foreach (['solpagocta' => 'solpc_id', 'solpagocuota' => 'solpcu_id', 'solpagoarch' => 'solpa_nro_sol'] as $tabla => $campo) {
            $this->apiCallAnitaEscritura($api, [
                'acc' => 'delete',
                'sistema' => $this->sistema(),
                'tabla' => $tabla,
                'whereArmado' => ' WHERE '.$campo.' = '.$codigo.' ',
            ], $tabla.' delete');
        }
    }

    private function reemplazarHijos(ApiAnita $api, Solicitudpago $sp): void
    {
        $codigo = (int) $sp->codigo;
        $usuarioAnita = AnitaUsuarioBridgeSupport::usuUsuarioDesdeErpId(Auth::id(), 'compras');
        $fechaAnita = SolicitudpagoAnitaFechaSupport::fechaAnitaDesde(now());
        $hora = now()->format('H:i');

        foreach ($sp->cuentas as $cta) {
            $cuentaCodigo = (int) ltrim((string) optional($cta->cuentacontables)->codigo, '0');
            $empresaCodigo = (int) optional($cta->empresas)->codigo;
            $ccCodigo = (int) optional($cta->centrocostos)->codigo;
            $dh = $cta->debe_haber === 'H' ? 'H' : 'D';
            $this->apiCallAnitaEscritura($api, [
                'acc' => 'insert',
                'sistema' => $this->sistema(),
                'tabla' => 'solpagocta',
                'campos' => 'solpc_id, solpc_empresa, solpc_cuenta, solpc_ccosto, solpc_d_h, solpc_monto, '
                    .'solpc_usuario_umod, solpc_fecha_umod, solpc_hora_umod',
                'valores' => $codigo.', '.$empresaCodigo.', '.$cuentaCodigo.', '.$ccCodigo.", '".$dh."', "
                    .(float) $cta->monto.', '.$usuarioAnita.', '.$fechaAnita.", '".$hora."'",
            ], 'solpagocta insert');
        }

        foreach ($sp->cuotas as $cuota) {
            $hijaCodigo = (int) optional($cuota->hijas)->codigo;
            $this->apiCallAnitaEscritura($api, [
                'acc' => 'insert',
                'sistema' => $this->sistema(),
                'tabla' => 'solpagocuota',
                'campos' => 'solpcu_id, solpcu_cuota, solpcu_fecha_vto, solpcu_monto, solpcu_id_sp',
                'valores' => $codigo.', '.(int) $cuota->nro_cuota.', '
                    .SolicitudpagoAnitaFechaSupport::fechaAnitaDesde($cuota->fecha_vencimiento).', '
                    .(float) $cuota->monto.', '.$hijaCodigo,
            ], 'solpagocuota insert');
        }

        foreach ($sp->archivos as $arch) {
            $nombre = $this->escapar(SolicitudpagoArchivoStorageSupport::nombreParaAnita($arch));
            $logname = $this->escapar(trim((string) (optional($arch->usuarios)->usuario ?? Auth::user()->usuario ?? '')));
            $this->apiCallAnitaEscritura($api, [
                'acc' => 'insert',
                'sistema' => $this->sistema(),
                'tabla' => 'solpagoarch',
                'campos' => 'solpa_nro_sol, solpa_nro_linea, solpa_archivo, solpa_usuario, solpa_fecha_act, solpa_hora_act',
                'valores' => $codigo.', '.(int) $arch->nro_linea.", '".$nombre."', '".$logname."', "
                    .SolicitudpagoAnitaFechaSupport::fechaAnitaDesde($arch->fecha ?? now()).", '"
                    .$this->escapar((string) ($arch->hora ?: $hora))."'",
            ], 'solpagoarch insert');
        }
    }

    private function insertarEstado(
        ApiAnita $api,
        Solicitudpago $sp,
        ?string $estadoAnterior,
        string $estadoActual,
        string $leyenda
    ): void {
        $usuarioAnita = AnitaUsuarioBridgeSupport::usuUsuarioDesdeErpId(Auth::id(), 'compras');
        $ant = $estadoAnterior ? SolicitudpagoEstados::haciaAnita($estadoAnterior) : ' ';
        $act = SolicitudpagoEstados::haciaAnita($estadoActual);
        $this->apiCallAnitaEscritura($api, [
            'acc' => 'insert',
            'sistema' => $this->sistema(),
            'tabla' => 'solpagoest',
            'campos' => 'solpe_id, solpe_fecha, solpe_hora, solpe_usuario, solpe_estado_ant, solpe_estado_act, solpe_leyenda',
            'valores' => (int) $sp->codigo.', '
                .SolicitudpagoAnitaFechaSupport::fechaAnitaDesde(now()).", '"
                .now()->format('H:i')."', ".$usuarioAnita.", '".$ant."', '".$act."', '"
                .$this->escapar($this->recortar($leyenda, 80))."'",
        ], 'solpagoest insert');
    }

    private function valoresCabecera(Solicitudpago $sp): string
    {
        return implode(', ', [
            (int) $sp->codigo,
            (int) optional($sp->empresas)->codigo,
            SolicitudpagoAnitaFechaSupport::fechaAnitaDesde($sp->fecha),
            "'".SolicitudpagoTratamientos::haciaAnita($sp->tratamiento)."'",
            "'".$this->escapar(str_pad((string) (optional($sp->proveedores)->codigo ?? ''), 6, '0', STR_PAD_LEFT))."'",
            (int) optional($sp->conceptos)->codigo,
            (int) optional($sp->formapagosol)->codigo,
            "'".(int) optional($sp->monedas)->codigo."'",
            "'".$this->escapar($this->recortar((string) $sp->beneficiario, 80))."'",
            "'".$this->escapar($this->recortar((string) $sp->endoso, 80))."'",
            SolicitudpagoAnitaFechaSupport::fechaAnitaDesde($sp->fecha_entrega),
            SolicitudpagoAnitaFechaSupport::fechaAnitaDesde($sp->fecha_vencimiento),
            (float) $sp->monto,
            "'".$this->escapar($this->recortar((string) $sp->observacion, 160))."'",
            AnitaUsuarioBridgeSupport::usuUsuarioDesdeErpId(Auth::id(), 'compras'),
            SolicitudpagoAnitaFechaSupport::fechaAnitaDesde(now()),
            "'".now()->format('H:i')."'",
            "'".SolicitudpagoEstados::haciaAnita($sp->estado)."'",
            (int) optional($sp->sectores)->codigo,
            "'".SolicitudpagoAnitaFechaSupport::fechaAlfaDesde($sp->fecha)."'",
            "'".$this->escapar($this->recortar((string) $sp->detalle, 180))."'",
            (int) optional($sp->madre)->codigo,
        ]);
    }

    private function setCabecera(Solicitudpago $sp): string
    {
        return implode(', ', [
            'solpm_empresa = '.(int) optional($sp->empresas)->codigo,
            'solpm_fecha = '.SolicitudpagoAnitaFechaSupport::fechaAnitaDesde($sp->fecha),
            "solpm_tratamiento = '".SolicitudpagoTratamientos::haciaAnita($sp->tratamiento)."'",
            "solpm_proveedor = '".$this->escapar(str_pad((string) (optional($sp->proveedores)->codigo ?? ''), 6, '0', STR_PAD_LEFT))."'",
            'solpm_concepto = '.(int) optional($sp->conceptos)->codigo,
            'solpm_formapago = '.(int) optional($sp->formapagosol)->codigo,
            "solpm_cod_mon = '".(int) optional($sp->monedas)->codigo."'",
            "solpm_beneficiario = '".$this->escapar($this->recortar((string) $sp->beneficiario, 80))."'",
            "solpm_endoso = '".$this->escapar($this->recortar((string) $sp->endoso, 80))."'",
            'solpm_fecha_ent = '.SolicitudpagoAnitaFechaSupport::fechaAnitaDesde($sp->fecha_entrega),
            'solpm_fecha_vto = '.SolicitudpagoAnitaFechaSupport::fechaAnitaDesde($sp->fecha_vencimiento),
            'solpm_monto = '.(float) $sp->monto,
            "solpm_observacion = '".$this->escapar($this->recortar((string) $sp->observacion, 160))."'",
            'solpm_usuario_umod = '.AnitaUsuarioBridgeSupport::usuUsuarioDesdeErpId(Auth::id(), 'compras'),
            'solpm_fecha_umod = '.SolicitudpagoAnitaFechaSupport::fechaAnitaDesde(now()),
            "solpm_hora_umod = '".now()->format('H:i')."'",
            "solpm_estado = '".SolicitudpagoEstados::haciaAnita($sp->estado)."'",
            'solpm_sector = '.(int) optional($sp->sectores)->codigo,
            "solpm_fecha_alfa = '".SolicitudpagoAnitaFechaSupport::fechaAlfaDesde($sp->fecha)."'",
            "solpm_detalle = '".$this->escapar($this->recortar((string) $sp->detalle, 180))."'",
            'solpm_id_sp_orig = '.(int) optional($sp->madre)->codigo,
        ]);
    }

    private function escapar(string $valor): string
    {
        return str_replace("'", "''", $valor);
    }

    private function recortar(string $valor, int $len): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($valor, 0, $len);
        }

        return substr($valor, 0, $len);
    }
}
