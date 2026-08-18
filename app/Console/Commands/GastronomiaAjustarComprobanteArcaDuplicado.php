<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Seguridad\Usuario;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Venta;
use App\Models\Ventas\VentaGastronomiaEmision;
use App\Services\Arca\ArcaWsfeFacturaElectronicaService;
use App\Services\Ventas\Gastronomia\GastronomiaAjusteFiscalZService;
use App\Services\Ventas\Gastronomia\GastronomiaNotaCreditoService;
use App\Services\Ventas\Gastronomia\GastronomiaRecuperarComprobanteArcaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class GastronomiaAjustarComprobanteArcaDuplicado extends Command
{
    protected $signature = 'gastronomia:ajustar-comprobante-arca-duplicado
                            {--empresa=1 : empresa_id}
                            {--pv=00003 : Código de punto de venta ARCA}
                            {--numero= : Factura autorizada en ARCA a recuperar}
                            {--cuenta= : cuenta_gastronomia_id de referencia}
                            {--fecha-nc= : Fecha fiscal de la nota de crédito (Y-m-d)}
                            {--fecha-jornada= : Jornada operativa a corregir (Y-m-d)}
                            {--ultima-nc-esperada= : Última NC autorizada antes del ajuste}
                            {--usuario= : usuario_id para auditoría}
                            {--ejecutar : Recupera la FAC, emite la NC y actualiza Z ERP}';

    protected $description = 'Recupera una FAC ARCA duplicada por failover, la anula con NC y reaplica Z sin Anita ni stock';

    public function handle(
        GastronomiaRecuperarComprobanteArcaService $recuperarService,
        GastronomiaNotaCreditoService $notaCreditoService,
        GastronomiaAjusteFiscalZService $ajusteZService,
        ArcaWsfeFacturaElectronicaService $arcaService,
    ): int {
        $empresaId = (int) $this->option('empresa');
        $pvCodigo = str_pad(trim((string) $this->option('pv')), 5, '0', STR_PAD_LEFT);
        $numero = (int) $this->option('numero');
        $cuentaId = (int) $this->option('cuenta');
        $fechaNc = trim((string) $this->option('fecha-nc'));
        $fechaJornada = trim((string) $this->option('fecha-jornada'));
        $ultimaNcEsperada = (int) $this->option('ultima-nc-esperada');
        $usuarioId = (int) $this->option('usuario');
        $ejecutar = (bool) $this->option('ejecutar');

        try {
            $this->validarArgumentos(
                $empresaId,
                $numero,
                $cuentaId,
                $fechaNc,
                $fechaJornada,
                $ultimaNcEsperada,
                $usuarioId,
            );
            $usuario = Usuario::query()->findOrFail($usuarioId);
            if (! Auth::loginUsingId((int) $usuario->id)) {
                throw new RuntimeException('No se pudo autenticar el usuario operativo.');
            }

            $pv = Puntoventa::query()
                ->where('empresa_id', $empresaId)
                ->where('codigo', $pvCodigo)
                ->firstOrFail();

            $ultimaNcArca = $arcaService->feCompUltimoAutorizado($empresaId, (int) $pvCodigo, 8);
            if ($ultimaNcArca !== $ultimaNcEsperada) {
                throw new RuntimeException(
                    'La última NC B de ARCA cambió: se esperaba '.$ultimaNcEsperada
                    .' y ARCA informa '.$ultimaNcArca.'.',
                );
            }

            $ncJornadaActual = VentaGastronomiaEmision::query()
                ->whereHas('venta', fn ($venta) => $venta
                    ->where('tipotransaccion_id', 2)
                    ->whereDate('fechajornada', $fechaNc)
                    ->whereHas('puntoventas', fn ($pvQ) => $pvQ->where('empresa_id', $empresaId)))
                ->count();
            if ($ncJornadaActual > 0) {
                throw new RuntimeException(
                    'Ya existen '.$ncJornadaActual.' NC en la jornada '.$fechaNc
                    .'; se aborta para no alterar su correlatividad.',
                );
            }

            $ventaFactura = Venta::query()
                ->where('puntoventa_id', $pv->id)
                ->where('numerocomprobante', $numero)
                ->first();

            if (! $ejecutar) {
                $dryRun = $ventaFactura !== null
                    ? ['venta_existente_id' => (int) $ventaFactura->id]
                    : $recuperarService->recuperar(
                        $empresaId,
                        $pvCodigo,
                        $numero,
                        1,
                        $cuentaId,
                        null,
                        true,
                    );
                $this->info('Preflight OK; no se realizaron escrituras.');
                $this->line(json_encode([
                    'factura' => $dryRun,
                    'proxima_nc_arca' => $ultimaNcArca + 1,
                    'fecha_nc' => $fechaNc,
                    'fecha_jornada' => $fechaJornada,
                    'usuario' => ['id' => $usuario->id, 'nombre' => $usuario->nombre],
                    'anita' => 'omitido',
                    'stock' => 'omitido',
                    'cobranza' => 'efectivo + devolucion efectivo',
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

                return self::SUCCESS;
            }

            if ($ventaFactura === null) {
                $recuperada = $recuperarService->recuperar(
                    $empresaId,
                    $pvCodigo,
                    $numero,
                    1,
                    $cuentaId,
                    null,
                    false,
                );
                $ventaFactura = Venta::query()->findOrFail((int) $recuperada['venta_id']);
                $this->info('Factura recuperada: '.$ventaFactura->codigo.' (id '.$ventaFactura->id.').');
            } else {
                $this->line('Factura ya recuperada: '.$ventaFactura->codigo.' (id '.$ventaFactura->id.').');
            }

            $emisionFactura = VentaGastronomiaEmision::query()
                ->where('venta_id', $ventaFactura->id)
                ->firstOrFail();
            if ((int) $emisionFactura->cuenta_gastronomia_id !== $cuentaId
                || (string) $emisionFactura->origen_pos !== 'recuperacion_arca') {
                throw new RuntimeException('La factura existente no coincide con la recuperación fiscal esperada.');
            }

            $ventaNcId = GastronomiaNotaCreditoService::notaCreditoExistenteParaFactura((int) $ventaFactura->id);
            if ($ventaNcId === null) {
                $resultadoNc = $notaCreditoService->generarDesdeFactura(
                    (int) $ventaFactura->id,
                    null,
                    'Anulación FAC ARCA recuperada por failover CAE/CAEA',
                    [
                        'ajuste_fiscal' => true,
                        'fecha_factura' => $fechaNc,
                        'fecha_jornada' => $fechaJornada,
                        'identificador_pc' => (string) $emisionFactura->identificador_pc,
                        'omitir_stock' => true,
                        'omitir_impresion' => true,
                    ],
                );
                if (empty($resultadoNc['ok']) || empty($resultadoNc['venta_id'])) {
                    throw new RuntimeException((string) ($resultadoNc['error'] ?? 'No se pudo emitir la NC fiscal.'));
                }
                $ventaNcId = (int) $resultadoNc['venta_id'];
                $this->info('Nota de crédito emitida: '.($resultadoNc['factura'] ?? '').' (id '.$ventaNcId.').');
            } else {
                $this->line('Nota de crédito ya existente: venta id '.$ventaNcId.'.');
            }

            $resultadoZ = $ajusteZService->actualizar((int) $ventaFactura->id, $ventaNcId);
            $this->info('Z de turno, jornada y presentación de tesorería actualizados en ERP.');
            $this->line(json_encode([
                'venta_factura_id' => (int) $ventaFactura->id,
                'venta_nc_id' => $ventaNcId,
                'z' => $resultadoZ,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }

    private function validarArgumentos(
        int $empresaId,
        int $numero,
        int $cuentaId,
        string $fechaNc,
        string $fechaJornada,
        int $ultimaNcEsperada,
        int $usuarioId,
    ): void {
        if ($empresaId <= 0 || $numero <= 0 || $cuentaId <= 0 || $ultimaNcEsperada <= 0 || $usuarioId <= 0) {
            throw new InvalidArgumentException('Empresa, número, cuenta, última NC y usuario son obligatorios.');
        }
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaNc)
            || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaJornada)) {
            throw new InvalidArgumentException('Las fechas deben informarse como Y-m-d.');
        }
    }
}
