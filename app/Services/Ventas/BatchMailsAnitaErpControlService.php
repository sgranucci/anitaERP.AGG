<?php

namespace App\Services\Ventas;

use App\Mail\Ventas\BatchMailsAnitaErpControlMail;
use App\Services\Stock\StkmovIntMailMergeService;
use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Robot El Bierzo: control de paridad ERP↔Anita de los batch mails (kilos / NC / stkmov).
 * No va a socios: solo alerta interna si hay DIFF o falla Anita.
 */
class BatchMailsAnitaErpControlService
{
    public function __construct(
        private readonly KilosPedidosMailMergeService $kilosMerge,
        private readonly ControlNcDescuentoMergeService $ncMerge,
        private readonly StkmovIntMailMergeService $stkmovMerge,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(?string $fecha = null, bool $enviarMail = true, bool $dryRun = false): array
    {
        $base = [
            'omitido' => false,
            'motivo' => null,
            'fecha' => null,
            'bloques' => [],
            'hay_problemas' => false,
            'destinos' => [],
            'mail_enviado' => false,
            'mail_destino' => null,
            'mail_error' => null,
            'dry_run' => $dryRun,
        ];

        if (! EntornoEmpresaSupport::esElBierzo()) {
            return array_merge($base, [
                'omitido' => true,
                'motivo' => 'Solo aplica en entorno EL BIERZO.',
            ]);
        }

        if (! (bool) config('pedido.batch_mails_anita_erp_control.habilitado', true)) {
            return array_merge($base, [
                'omitido' => true,
                'motivo' => 'Control batch mails Anita/ERP deshabilitado.',
            ]);
        }

        $fecha = $fecha && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) ? $fecha : date('Y-m-d');
        $base['fecha'] = $fecha;

        $bloques = [];
        $hayProblemas = false;

        // Kilos: entrega del día, todos los repartos.
        try {
            $kilos = $this->kilosMerge->generar([
                'fecha_desde' => $fecha,
                'fecha_hasta' => $fecha,
                'reparto_desde' => '',
                'reparto_hasta' => '',
                'tipolistado' => 'ARTICULO',
                'estado' => 'TODO',
            ]);
            $diffs = count($kilos['diffs'] ?? []);
            $okAnita = (bool) ($kilos['anita_ok'] ?? true);
            $problema = $diffs > 0 || ! $okAnita;
            $hayProblemas = $hayProblemas || $problema;
            $bloques[] = [
                'clave' => 'kilos_pedidos',
                'nombre' => 'Kilos pedidos',
                'diffs' => $diffs,
                'cuadran' => (bool) ($kilos['cuadran'] ?? false),
                'anita_ok' => $okAnita,
                'anita_error' => $kilos['anita_error'] ?? null,
                'detalle' => array_slice($kilos['diffs'] ?? [], 0, 30),
                'problema' => $problema,
            ];
        } catch (\Throwable $e) {
            $hayProblemas = true;
            $bloques[] = [
                'clave' => 'kilos_pedidos',
                'nombre' => 'Kilos pedidos',
                'diffs' => 0,
                'cuadran' => false,
                'anita_ok' => false,
                'anita_error' => $e->getMessage(),
                'detalle' => [],
                'problema' => true,
            ];
            Log::warning('BatchMailsAnitaErpControl kilos', ['error' => $e->getMessage()]);
        }

        try {
            $nc = $this->ncMerge->generar($fecha);
            $diffs = count($nc['diffs'] ?? []);
            $okAnita = (bool) ($nc['anita_ok'] ?? true);
            $problema = $diffs > 0 || ! $okAnita;
            $hayProblemas = $hayProblemas || $problema;
            $bloques[] = [
                'clave' => 'control_nc',
                'nombre' => 'Control NC descuento',
                'diffs' => $diffs,
                'cuadran' => (bool) ($nc['cuadran'] ?? false),
                'anita_ok' => $okAnita,
                'anita_error' => $nc['anita_error'] ?? null,
                'detalle' => array_slice($nc['diffs'] ?? [], 0, 30),
                'problema' => $problema,
            ];
        } catch (\Throwable $e) {
            $hayProblemas = true;
            $bloques[] = [
                'clave' => 'control_nc',
                'nombre' => 'Control NC descuento',
                'diffs' => 0,
                'cuadran' => false,
                'anita_ok' => false,
                'anita_error' => $e->getMessage(),
                'detalle' => [],
                'problema' => true,
            ];
            Log::warning('BatchMailsAnitaErpControl NC', ['error' => $e->getMessage()]);
        }

        try {
            $stk = $this->stkmovMerge->generar($fecha);
            $diffs = count($stk['diffs'] ?? []);
            $okAnita = (bool) ($stk['anita_ok'] ?? true);
            $problema = $diffs > 0 || ! $okAnita;
            $hayProblemas = $hayProblemas || $problema;
            $bloques[] = [
                'clave' => 'stkmov_int',
                'nombre' => 'Stkmov int (G1–G6)',
                'diffs' => $diffs,
                'cuadran' => (bool) ($stk['cuadran'] ?? false),
                'anita_ok' => $okAnita,
                'anita_error' => $stk['anita_error'] ?? null,
                'detalle' => array_slice($stk['diffs'] ?? [], 0, 30),
                'problema' => $problema,
            ];
        } catch (\Throwable $e) {
            $hayProblemas = true;
            $bloques[] = [
                'clave' => 'stkmov_int',
                'nombre' => 'Stkmov int (G1–G6)',
                'diffs' => 0,
                'cuadran' => false,
                'anita_ok' => false,
                'anita_error' => $e->getMessage(),
                'detalle' => [],
                'problema' => true,
            ];
            Log::warning('BatchMailsAnitaErpControl stkmov', ['error' => $e->getMessage()]);
        }

        $base['bloques'] = $bloques;
        $base['hay_problemas'] = $hayProblemas;
        $destinos = $this->destinos();
        $base['destinos'] = $destinos;

        if ($dryRun || ! $enviarMail) {
            return $base;
        }

        if (! $hayProblemas) {
            return $base;
        }

        if ($destinos === []) {
            return array_merge($base, [
                'omitido' => true,
                'motivo' => 'Hay diferencias pero sin destinatarios de control (PEDIDO_BATCH_MAILS_ANITA_ERP_CONTROL_EMAIL).',
            ]);
        }

        try {
            Mail::to($destinos)->send(new BatchMailsAnitaErpControlMail(
                fecha: $fecha,
                bloques: $bloques,
            ));
            $base['mail_enviado'] = true;
            $base['mail_destino'] = implode(', ', $destinos);
        } catch (\Throwable $e) {
            Log::error('ventas:control-batch-mails-anita-erp falló', [
                'fecha' => $fecha,
                'error' => $e->getMessage(),
            ]);
            $base['mail_error'] = $e->getMessage();
        }

        return $base;
    }

    /**
     * @return list<string>
     */
    private function destinos(): array
    {
        $raw = (string) config('pedido.batch_mails_anita_erp_control.email', '');
        $partes = preg_split('/[,\s;]+/', $raw) ?: [];
        $out = [];
        foreach ($partes as $email) {
            $email = strtolower(trim((string) $email));
            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $out[] = $email;
            }
        }

        return array_values(array_unique($out));
    }
}
