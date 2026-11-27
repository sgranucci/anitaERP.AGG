<?php

declare(strict_types=1);

namespace App\Console\Commands\Compras;

use App\Services\Compras\ComprobanteProveedorReversoContableService;
use App\Support\Compras\ComprobanteProveedorAnitaBridgeGate;
use App\Support\Compras\ComprobanteProveedorReversoFiscalSupport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/**
 * Reverso contable fiscal de comprobantes de proveedor, en lote y con plan explícito.
 *
 * Sin --ejecutar solo simula (dry-run): no crea comprobantes, asientos ni aplicaciones.
 * Con --ejecutar graba, de a uno, y se detiene ante el primer error.
 */
class ReversoFiscalComprobantesCommand extends Command
{
    protected $signature = 'compras:reverso-fiscal
                            {--plan= : JSON: [{"id":34512,"tipo":"DDS","iva":"copiar"}] (iva: copiar | no_computable)}
                            {--fecha= : Fecha contable Y-m-d (default hoy, según el período abierto)}
                            {--ejecutar : Graba. Sin este flag solo simula}
                            {--usuario= : ID de usuario que figura como creador (obligatorio con --ejecutar)}';

    protected $description = 'Reverso contable fiscal (NC/ND que entran al IVA compras y al Libro IVA Digital)';

    public function handle(ComprobanteProveedorReversoContableService $service): int
    {
        $plan = json_decode((string) $this->option('plan'), true);
        if (! is_array($plan) || $plan === []) {
            $this->error('Falta --plan con un JSON de la forma [{"id":..,"tipo":"CNB","iva":"no_computable"}].');

            return self::FAILURE;
        }

        foreach ($plan as $i => $item) {
            $id = (int) ($item['id'] ?? 0);
            $tipo = strtoupper(trim((string) ($item['tipo'] ?? '')));
            $iva = (string) ($item['iva'] ?? '');
            if ($id <= 0 || $tipo === '' || ! in_array($iva, [
                ComprobanteProveedorReversoFiscalSupport::TRATAMIENTO_COPIAR,
                ComprobanteProveedorReversoFiscalSupport::TRATAMIENTO_NO_COMPUTABLE,
            ], true)) {
                $this->error('Entrada '.($i + 1).' del plan inválida: se necesita id, tipo e iva (copiar | no_computable).');

                return self::FAILURE;
            }
        }

        $fecha = $this->option('fecha') ? (string) $this->option('fecha') : null;
        if ($fecha !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) !== 1) {
            $this->error('--fecha tiene que ser Y-m-d.');

            return self::FAILURE;
        }

        $ejecutar = (bool) $this->option('ejecutar');
        if ($ejecutar) {
            $usuarioId = (int) $this->option('usuario');
            if ($usuarioId <= 0 || ! \App\Models\Seguridad\Usuario::query()->whereKey($usuarioId)->exists()) {
                $this->error('Con --ejecutar hace falta --usuario=ID de un usuario existente (creousuario_id).');

                return self::FAILURE;
            }
            // Consola sin sesión: el persistence toma creousuario_id de Auth::id().
            Auth::onceUsingId($usuarioId);
        }

        $this->line(sprintf(
            'Modo: %s · %d comprobante(s)',
            $ejecutar ? 'GRABA' : 'dry-run (no escribe)',
            count($plan)
        ));
        $this->line(sprintf(
            'Anita: escritura habilitada=%s · en línea=%s',
            ComprobanteProveedorAnitaBridgeGate::escrituraHabilitada() ? 'sí' : 'no',
            ComprobanteProveedorAnitaBridgeGate::grabacionEnLineaHabilitada()
                ? 'sí'
                : 'no (queda pendiente para el barrido programado)'
        ));

        $reservados = [];
        $conError = 0;

        foreach ($plan as $item) {
            $id = (int) $item['id'];
            $tipo = strtoupper(trim((string) $item['tipo']));
            $iva = (string) $item['iva'];

            if ($ejecutar) {
                try {
                    $resultado = $service->revertirFiscal($id, $tipo, $iva, $fecha);
                    $this->info($resultado['mensaje']);
                } catch (\Throwable $e) {
                    $this->error('CP#'.$id.': '.$e->getMessage());
                    $conError++;
                    break;
                }

                continue;
            }

            $p = $service->previsualizarFiscal($id, $tipo, $iva, $fecha, $reservados);
            $this->mostrarPrevisualizacion($p);
            if ($p['errores'] !== []) {
                $conError++;
            }
        }

        $this->newLine();
        if ($ejecutar) {
            $this->line($conError === 0 ? 'Listo: todos los reversos grabados.' : 'Se detuvo en el primer error.');
        } else {
            $this->line($conError === 0
                ? 'Simulación sin errores. Nada se grabó.'
                : 'Simulación con '.$conError.' comprobante(s) con error. Nada se grabó.');
        }

        return $conError === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $p
     */
    private function mostrarPrevisualizacion(array $p): void
    {
        $this->newLine();
        $this->line('== '.$p['origen_etiqueta'].' (CP#'.$p['origen_id'].', empresa '.$p['empresa_id'].')');
        $this->line(sprintf(
            '   Reverso: %s %s-%04d-%s · fecha %s · total %s · modo %s',
            $p['tipo'],
            $p['letra'],
            $p['sucursal'],
            $p['numero'],
            $p['fecha'],
            number_format((float) $p['total'], 2, ',', '.'),
            $p['modo'] !== '' ? $p['modo'] : '?'
        ));
        if ($p['modo_texto'] !== '') {
            $this->line('   '.$p['modo_texto']);
        }

        if ($p['conceptos'] !== []) {
            $this->table(
                ['Código', 'Concepto', 'Monto'],
                array_map(static fn (array $c): array => [
                    $c['codigo'],
                    $c['nombre'],
                    number_format((float) $c['monto'], 2, ',', '.'),
                ], $p['conceptos'])
            );
        }

        if ($p['espejo'] !== []) {
            $this->line('   Asiento (espejo del original):');
            $this->table(
                ['Cuenta', 'Nombre', 'Debe', 'Haber'],
                array_map(static fn (array $l): array => [
                    $l['cuenta_codigo'],
                    $l['cuenta_nombre'],
                    $l['debe'] !== null ? number_format((float) $l['debe'], 2, ',', '.') : '',
                    $l['haber'] !== null ? number_format((float) $l['haber'], 2, ',', '.') : '',
                ], $p['espejo'])
            );
        }

        $this->line(sprintf(
            '   IVA compras: %s · Período: %s',
            $p['informable_iva'] ? 'sí' : 'no',
            $p['periodo_ok'] ? 'abierto' : 'cerrado'
        ));
        foreach ($p['errores'] as $error) {
            $this->warn('   ! '.$error);
        }
    }
}
