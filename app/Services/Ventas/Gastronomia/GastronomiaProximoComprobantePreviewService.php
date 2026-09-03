<?php

namespace App\Services\Ventas\Gastronomia;

use App\Models\Configuracion\Condicioniva;
use App\Models\Configuracion\Empresa;
use App\Models\Ventas\ConfiguracionPuntoventaGastronomia;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Services\Ventas\FacturaelectronicaService;
use App\Support\Ventas\ArcaWsfeEmisionResiliencia;
use App\Models\Ventas\Venta;
use App\Support\Ventas\TipotransaccionCodigoAfipSupport;
use App\Support\Ventas\VentaNumeracionEmpresaSupport;
use InvalidArgumentException;
use Throwable;

/**
 * Lectura informativa del próximo número (no reserva ni reemplaza la numeración al emitir).
 */
final class GastronomiaProximoComprobantePreviewService
{
    public function __construct(
        private readonly FacturaelectronicaService $facturaelectronicaService,
    ) {
    }

    /**
     * @return array{
     *   ok:bool,
     *   etiqueta:?string,
     *   proximo:?int,
     *   ultimo:?int,
     *   fuente:string,
     *   letra:string,
     *   puntoventa_codigo:?string,
     *   usa_caea:bool,
     *   salto_caea:bool,
     *   forzar_caea_al_emitir:bool,
     *   ms:int,
     *   error:?string,
     *   warn:?string
     * }
     */
    public function consultar(ConfiguracionPuntoventaGastronomia $cfg): array
    {
        $t0 = microtime(true);
        $cfg->loadMissing(['tipotransaccion', 'puntoventaCae', 'puntoventaCaea']);

        $tipoFacturaId = (int) ($cfg->tipotransaccion_id ?? 0);
        if ($tipoFacturaId <= 0) {
            $tipoFacturaId = (int) config('gastronomia.tipotransaccion_factura_id', 0);
        }
        if ($tipoFacturaId <= 0) {
            throw new InvalidArgumentException('Configure tipotransaccion_id en el PV gastronomía.');
        }

        $tt = $cfg->tipotransaccion ?? Tipotransaccion::query()->find($tipoFacturaId);
        if ($tt === null) {
            throw new InvalidArgumentException('Tipo de transacción factura inexistente.');
        }

        $pvResolucion = ArcaWsfeEmisionResiliencia::resolverPuntoventaEmision(
            (int) ($cfg->puntoventa_cae_id ?? 0),
            (int) ($cfg->puntoventa_caea_id ?? 0),
            false,
        );
        $puntoventaId = (int) ($pvResolucion['puntoventa_id'] ?? 0);
        $usaCaea = ! empty($pvResolucion['usa_caea']);
        if ($puntoventaId <= 0) {
            throw new InvalidArgumentException('Configure punto de venta CAE/CAEA en el PV gastronomía.');
        }

        $puntoventa = Puntoventa::query()->find($puntoventaId);
        if ($puntoventa === null) {
            throw new InvalidArgumentException('Punto de venta #'.$puntoventaId.' inexistente.');
        }

        $letra = $this->letraConsumidorFinal();
        $abrev = substr(trim((string) ($tt->abreviatura ?? 'FAC')), 0, 3);
        $cbteTipo = TipotransaccionCodigoAfipSupport::codigoAfipParaEmision((string) ($tt->codigo ?? ''), $letra);
        if ($cbteTipo <= 0) {
            throw new InvalidArgumentException('No se pudo resolver el tipo AFIP de la factura gastronomía.');
        }

        $webservice = (string) ($puntoventa->webservice ?? '');
        $modoPv = strtoupper(trim((string) ($puntoventa->modofacturacion ?? '')));
        $usaCaea = $usaCaea || $modoPv === 'A';

        if ($usaCaea) {
            return $this->respuestaErp(
                $puntoventa,
                $abrev,
                $letra,
                $cbteTipo,
                $t0,
                saltoCaea: false,
                warn: ArcaWsfeEmisionResiliencia::mensajeAvisoModoCaeaForzado($webservice),
            );
        }

        $empresa = Empresa::query()->find((int) ($puntoventa->empresa_id ?? 0));
        $nroinscripcion = $empresa !== null ? (string) ($empresa->nroinscripcion ?? '') : '';
        $timeoutPreview = max(5, (int) config('gastronomia.preview_arca_soap_timeout', 10));
        $opciones = [
            'emision_pos_arca' => true,
            'aplicar_timeout_pos_arca' => true,
            'soap_timeout_arca_pos' => $timeoutPreview,
            'notificar_failover_transporte_en_capa_superior' => true,
        ];

        try {
            $raw = $this->facturaelectronicaService->traeUltimoNumeroComprobante(
                $nroinscripcion,
                $cbteTipo,
                $puntoventa,
                $opciones,
            );
        } catch (Throwable $e) {
            return $this->trasFalloArca($cfg, $puntoventa, $abrev, $letra, $cbteTipo, $t0, $e->getMessage());
        }

        if ($raw === -1 || $raw === '-1') {
            return $this->trasFalloArca(
                $cfg,
                $puntoventa,
                $abrev,
                $letra,
                $cbteTipo,
                $t0,
                'ARCA no devolvió el último número autorizado.',
            );
        }

        $ultimo = (int) $raw;
        $pvNuevo = $ultimo <= 0 && ! $this->pvTieneVentas((int) $puntoventa->id);
        $usable = $ultimo > 0 || $pvNuevo;
        $proximo = $ultimo > 0 ? $ultimo + 1 : ($pvNuevo ? 1 : 0);
        $pvCodigo = (string) ($puntoventa->codigo ?? '');

        return $this->ok([
            'etiqueta' => $proximo > 0 ? $this->etiqueta($abrev, $letra, $pvCodigo, $proximo) : null,
            'proximo' => $proximo > 0 ? $proximo : null,
            'ultimo' => $ultimo,
            'fuente' => 'arca',
            'letra' => $letra,
            'puntoventa_codigo' => $pvCodigo,
            'usa_caea' => false,
            'salto_caea' => false,
            'forzar_caea_al_emitir' => false,
            'usable_al_emitir' => $usable,
            'pv_nuevo' => $pvNuevo,
            'ms' => (int) round((microtime(true) - $t0) * 1000),
            'error' => null,
            'warn' => $usable ? null : 'Último ARCA en 0 y el PV ya tiene ventas; al facturar se vuelve a consultar.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function trasFalloArca(
        ConfiguracionPuntoventaGastronomia $cfg,
        Puntoventa $puntoventaCae,
        string $abrev,
        string $letra,
        int $cbteTipo,
        float $t0,
        string $mensaje,
    ): array {
        $webservice = (string) ($puntoventaCae->webservice ?? '');
        $esTransporte = ArcaWsfeEmisionResiliencia::esErrorTransporte($mensaje)
            || str_contains(strtolower($mensaje), 'no devolvió el último número');

        if ($esTransporte) {
            ArcaWsfeEmisionResiliencia::notificarFallaTransporteEmision(
                $mensaje !== '' ? $mensaje : 'FECompUltimoAutorizado: timeout o sin respuesta',
                $webservice,
                ['probe' => 'gastronomia.proximo_comprobante'],
            );
        }

        $puedeCaea = $esTransporte
            && ArcaWsfeEmisionResiliencia::reintentarCaeaSiFallaComunicacion($webservice)
            && (int) ($cfg->puntoventa_caea_id ?? 0) > 0;

        if (! $puedeCaea) {
            return $this->fallo($mensaje, false, $t0);
        }

        $pvCaea = Puntoventa::query()->find((int) $cfg->puntoventa_caea_id);
        if ($pvCaea === null) {
            return $this->fallo($mensaje.' (sin PV CAEA para saltar).', false, $t0);
        }

        $warn = 'ARCA no respondió a tiempo ('.$this->timeoutPreviewSegundos().' s). Próxima factura en modo CAEA. '
            .'Al facturar se usa CAEA; el timeout normal de emisión no cambia.';

        return $this->respuestaErp(
            $pvCaea,
            $abrev,
            $letra,
            $cbteTipo,
            $t0,
            saltoCaea: true,
            warn: $warn,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function respuestaErp(
        Puntoventa $puntoventa,
        string $abrev,
        string $letra,
        int $cbteTipo,
        float $t0,
        bool $saltoCaea,
        ?string $warn,
    ): array {
        $ultimo = VentaNumeracionEmpresaSupport::maxNumerocomprobanteErpDesdeTipotransaccion(
            (int) $puntoventa->id,
            $cbteTipo,
            $letra,
            (int) ($puntoventa->empresa_id ?? 0) ?: null,
        );
        $proximo = $ultimo > 0 ? $ultimo + 1 : 1;
        $pvCodigo = (string) ($puntoventa->codigo ?? '');

        return $this->ok([
            'etiqueta' => $this->etiqueta($abrev, $letra, $pvCodigo, $proximo),
            'proximo' => $proximo,
            'ultimo' => $ultimo,
            'fuente' => 'erp',
            'letra' => $letra,
            'puntoventa_codigo' => $pvCodigo,
            'usa_caea' => true,
            'salto_caea' => $saltoCaea,
            'forzar_caea_al_emitir' => $saltoCaea,
            'usable_al_emitir' => false,
            'pv_nuevo' => false,
            'ms' => (int) round((microtime(true) - $t0) * 1000),
            'error' => null,
            'warn' => $warn,
        ]);
    }

    private function pvTieneVentas(int $puntoventaId): bool
    {
        if ($puntoventaId <= 0) {
            return false;
        }

        return Venta::query()->where('puntoventa_id', $puntoventaId)->exists();
    }

    private function etiqueta(string $abrev, string $letra, string $pvCodigo, int $proximo): string
    {
        $pv = str_pad(preg_replace('/\D+/', '', $pvCodigo) ?: $pvCodigo, 5, '0', STR_PAD_LEFT);

        return trim($abrev).' '.$letra.'-'.$pv.'-'.str_pad((string) $proximo, 8, '0', STR_PAD_LEFT);
    }

    private function timeoutPreviewSegundos(): int
    {
        return max(5, (int) config('gastronomia.preview_arca_soap_timeout', 10));
    }

    private function letraConsumidorFinal(): string
    {
        $letra = 'B';
        $cfCondicionId = (int) config('gastronomia.consumidor_final_condicioniva_id', 3);
        $condicion = Condicioniva::query()->find($cfCondicionId);
        if ($condicion && trim((string) ($condicion->letra ?? '')) !== '') {
            $letra = (string) $condicion->letra;
        }

        return $letra;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function ok(array $payload): array
    {
        return array_merge(['ok' => true], $payload);
    }

    /**
     * @return array<string, mixed>
     */
    private function fallo(string $msg, bool $usaCaea, float $t0): array
    {
        return [
            'ok' => false,
            'etiqueta' => null,
            'proximo' => null,
            'ultimo' => null,
            'fuente' => $usaCaea ? 'erp' : 'arca',
            'letra' => '',
            'puntoventa_codigo' => null,
            'usa_caea' => $usaCaea,
            'salto_caea' => false,
            'forzar_caea_al_emitir' => false,
            'usable_al_emitir' => false,
            'pv_nuevo' => false,
            'ms' => (int) round((microtime(true) - $t0) * 1000),
            'error' => $msg,
            'warn' => null,
        ];
    }
}
