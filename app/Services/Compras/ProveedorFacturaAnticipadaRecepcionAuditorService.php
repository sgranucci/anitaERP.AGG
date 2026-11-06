<?php

declare(strict_types=1);

namespace App\Services\Compras;

use App\Mail\Compras\ProveedorFacturaAnticipadaRecepcionAuditorMail;
use App\Models\Configuracion\Empresa;
use App\Support\Compras\ProveedorFacturaAnticipadaRecepcionSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Auditor diario: recepción en anticipos no puede superar la factura anticipada de la OC.
 */
final class ProveedorFacturaAnticipadaRecepcionAuditorService
{
    public function __construct(
        private readonly ProveedorFacturaAnticipadaRecepcionSupport $support,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function ejecutar(bool $enviarMail = true, ?int $empresaId = null): array
    {
        $config = config('compras.factura_anticipada_recepcion_auditor', []);
        $tolerancia = (float) ($config['tolerancia'] ?? 1);
        $limite = max(10, (int) ($config['limite_filas_mail'] ?? 120));
        $mailSiempre = filter_var($config['mail_siempre'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $fecha = Carbon::today()->toDateString();

        $empresasCfg = $this->empresasAAuditar($empresaId, $config);
        $salida = [
            'fecha' => $fecha,
            'fecha_fmt' => Carbon::parse($fecha)->format('d/m/Y'),
            'tolerancia' => $tolerancia,
            'empresas' => [],
            'mails_enviados' => 0,
            'mails_omitidos' => 0,
            'requiere_alerta' => false,
        ];

        foreach ($empresasCfg as $empresa) {
            $detalle = $this->support->auditar($tolerancia, (int) $empresa->id);
            $desvios = array_values($detalle['desvios'] ?? []);
            $pendientes = array_values($detalle['pendientes'] ?? []);
            $requiereAlerta = $desvios !== [];
            $paquete = [
                'fecha' => $fecha,
                'fecha_fmt' => $salida['fecha_fmt'],
                'empresa_id' => (int) $empresa->id,
                'empresa_nombre' => trim((string) $empresa->nombre),
                'tolerancia' => $tolerancia,
                'requiere_alerta' => $requiereAlerta,
                'ordenes_total' => (int) ($detalle['ordenes_total'] ?? 0),
                'desvios_total' => count($desvios),
                'pendientes_total' => count($pendientes),
                'desvios' => array_slice($desvios, 0, $limite),
                'desvios_truncados' => max(0, count($desvios) - $limite),
                'pendientes' => array_slice($pendientes, 0, min(20, $limite)),
                'pendientes_truncados' => max(0, count($pendientes) - min(20, $limite)),
                'exceso_recepcion' => round(array_sum(array_map(
                    static fn (array $f): float => (float) ($f['exceso_recepcion'] ?? 0),
                    $desvios
                )), 2),
                'mail_enviado' => false,
                'mail_destino' => null,
                'mail_error' => null,
            ];

            if ($requiereAlerta) {
                $salida['requiere_alerta'] = true;
            }

            if ($enviarMail && ($requiereAlerta || $mailSiempre)) {
                $paquete = array_merge($paquete, $this->enviarMail($paquete, $config));
                if (! empty($paquete['mail_enviado'])) {
                    $salida['mails_enviados']++;
                } else {
                    $salida['mails_omitidos']++;
                }
            } elseif (! $enviarMail) {
                $salida['mails_omitidos']++;
            }

            $salida['empresas'][] = $paquete;
        }

        return $salida;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return list<Empresa>
     */
    private function empresasAAuditar(?int $empresaId, array $config): array
    {
        $ids = array_values(array_unique(array_filter(array_map(
            'intval',
            (array) ($config['empresas'] ?? [])
        ), static fn (int $id): bool => $id > 0)));

        $query = Empresa::query()->orderBy('id');
        if ($empresaId !== null && $empresaId > 0) {
            $query->whereKey($empresaId);
        } elseif ($ids !== []) {
            $query->whereIn('id', $ids);
        } else {
            return [];
        }

        return $query->get()->all();
    }

    /**
     * @param  array<string, mixed>  $paquete
     * @param  array<string, mixed>  $config
     * @return array{mail_enviado: bool, mail_destino: ?string, mail_error: ?string}
     */
    private function enviarMail(array $paquete, array $config): array
    {
        $destino = trim((string) ($config['email'] ?? ''));
        if ($destino === '') {
            return [
                'mail_enviado' => false,
                'mail_destino' => null,
                'mail_error' => 'Sin destinatario (COMPRAS_FACTURA_ANTICIPADA_RECEPCION_AUDITOR_EMAIL)',
            ];
        }

        try {
            Mail::to($destino)->send(new ProveedorFacturaAnticipadaRecepcionAuditorMail($paquete));

            return [
                'mail_enviado' => true,
                'mail_destino' => $destino,
                'mail_error' => null,
            ];
        } catch (Throwable $e) {
            Log::warning('factura_anticipada_recepcion_auditor.mail_error', [
                'empresa_id' => $paquete['empresa_id'] ?? null,
                'error' => $e->getMessage(),
            ]);

            return [
                'mail_enviado' => false,
                'mail_destino' => $destino,
                'mail_error' => $e->getMessage(),
            ];
        }
    }
}
