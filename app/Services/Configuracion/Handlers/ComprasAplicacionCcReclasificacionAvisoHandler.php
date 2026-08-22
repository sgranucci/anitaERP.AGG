<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoHandlerInterface;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Proveedor_Cuentacorriente_Aplicacion;
use App\Models\Contable\Asiento;
use App\Models\Contable\Cuentacontable;
use App\Support\Compras\ProveedorCuentacorrienteAplicacionFilaSupport;
use App\Support\Navegacion\ModoConsultaUrlSupport;

/**
 * Aviso cuando una aplicación de CC reclasifica entre dos cuentas de proveedores
 * sin ser un anticipo (descalce MN/ME entre la OC y el comprobante).
 *
 * entityId = id de `proveedor_cuentacorriente_aplicacion` del lado deuda.
 */
class ComprasAplicacionCcReclasificacionAvisoHandler implements ModuloAvisoHandlerInterface
{
    public function contextoFiltro(int $entityId): array
    {
        $aplicacion = $this->aplicacion($entityId);

        return [
            'empresa_id' => (int) ($aplicacion->empresa_id ?? 0) ?: null,
            'centrocosto_id' => null,
        ];
    }

    public function placeholders(int $entityId): array
    {
        $aplicacion = $this->aplicacion($entityId);
        $deuda = $aplicacion?->proveedor_cuentacorrientes;
        $credito = $aplicacion?->proveedor_cuentacorriente_aplicados;
        $asiento = $this->asiento($aplicacion);

        return [
            'empresa' => (string) (optional($deuda?->empresas)->nombre ?? '—'),
            'proveedor' => (string) (optional($deuda?->proveedores)->nombre
                ?? optional($credito?->proveedores)->nombre
                ?? '—'),
            'fecha' => $aplicacion?->fecha ? $aplicacion->fecha->format('d/m/Y') : '—',
            'deuda' => $this->etiqueta($deuda, 'deuda'),
            'credito' => $this->etiqueta($credito, 'credito'),
            'importe' => $this->importe($aplicacion),
            'moneda' => (string) (optional($aplicacion?->monedas)->nombre
                ?? optional($aplicacion?->monedas)->abreviatura
                ?? '—'),
            'asiento' => $asiento ? (string) $asiento->numeroasiento : '—',
            'detalle_asiento' => $this->detalleAsiento($asiento),
        ];
    }

    public function linkConsulta(int $entityId): ?string
    {
        $asiento = $this->asiento($this->aplicacion($entityId));
        if ($asiento === null) {
            return null;
        }

        return ModoConsultaUrlSupport::urlAbsolutaConConsulta(
            'contable/asiento/'.(int) $asiento->id.'/editar'
        );
    }

    public function generarPdf(int $entityId): ?array
    {
        return null;
    }

    private function aplicacion(int $entityId): ?Proveedor_Cuentacorriente_Aplicacion
    {
        return Proveedor_Cuentacorriente_Aplicacion::query()
            ->with([
                'monedas',
                'proveedor_cuentacorrientes.proveedores',
                'proveedor_cuentacorrientes.empresas',
                'proveedor_cuentacorrientes.comprobante_proveedores.tipotransaccion_compras',
                'proveedor_cuentacorrientes.pagoproveedores',
                'proveedor_cuentacorriente_aplicados.proveedores',
                'proveedor_cuentacorriente_aplicados.comprobante_proveedores.tipotransaccion_compras',
                'proveedor_cuentacorriente_aplicados.pagoproveedores',
            ])
            ->find($entityId);
    }

    private function asiento(?Proveedor_Cuentacorriente_Aplicacion $aplicacion): ?Asiento
    {
        $asientoId = (int) ($aplicacion->asiento_id ?? 0);
        if ($asientoId <= 0) {
            return null;
        }

        return Asiento::query()->with('asiento_movimientos')->find($asientoId);
    }

    private function etiqueta(?Proveedor_Cuentacorriente $cc, string $ladoDefault): string
    {
        if ($cc === null) {
            return '—';
        }

        return ProveedorCuentacorrienteAplicacionFilaSupport::etiqueta(
            $cc,
            ProveedorCuentacorrienteAplicacionFilaSupport::tipo($cc, $ladoDefault)
        );
    }

    private function importe(?Proveedor_Cuentacorriente_Aplicacion $aplicacion): string
    {
        return number_format(abs((float) ($aplicacion->total ?? 0)), 2, ',', '.');
    }

    private function detalleAsiento(?Asiento $asiento): string
    {
        if ($asiento === null) {
            return '—';
        }

        $cuentas = Cuentacontable::query()
            ->whereIn('id', $asiento->asiento_movimientos->pluck('cuentacontable_id')->filter()->all())
            ->get(['id', 'codigo', 'nombre'])
            ->keyBy('id');

        $lineas = [];
        foreach ($asiento->asiento_movimientos as $mov) {
            $cuenta = $cuentas->get((int) $mov->cuentacontable_id);
            $monto = (float) ($mov->monto ?? 0);
            $lineas[] = sprintf(
                '  %s %s  %s %s',
                (string) ($cuenta->codigo ?? $mov->cuentacontable_id),
                (string) ($cuenta->nombre ?? ''),
                $monto >= 0 ? 'Debe' : 'Haber',
                number_format(abs($monto), 2, ',', '.')
            );
        }

        return $lineas === [] ? '—' : implode("\n", $lineas);
    }
}
