<?php

namespace App\Services\Compras;

use App\Models\Compras\Ordencompra_Contrato_Aviso;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Compras\OrdencompraContratoVencimientoSupport;
use Illuminate\Support\Facades\Log;

/**
 * Envío de avisos de vencimiento de contratos / OC abiertas.
 *
 * El log `ordencompra_contrato_aviso` garantiza que cada umbral se avise una sola vez:
 * sin él, el cron reenviaría el mismo mail todos los días hasta el vencimiento y el
 * aviso dejaría de leerse. Al renovar el contrato cambia la fecha de referencia de la
 * clave y los umbrales vuelven a dispararse.
 */
class OrdencompraContratoAvisoService
{
    public const MODULO = 'compras';

    /** Preventivo: por vencer, preaviso de no renovación y consumo del tope. */
    public const CODIGO_VENCIMIENTO = 'ordencompra_contrato_vencimiento';

    /** Escalamiento: contratos ya vencidos que siguen abiertos. */
    public const CODIGO_VENCIDO = 'ordencompra_contrato_vencido';

    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    /**
     * @return array{
     *   enviados: int,
     *   contratos_preventivos: int,
     *   contratos_vencidos: int,
     *   avisos_registrados: int,
     *   omitido: string|null,
     * }
     */
    public function procesar(?int $empresaId = null, bool $simular = false): array
    {
        $novedades = OrdencompraContratoVencimientoSupport::novedades($empresaId);

        $base = [
            'enviados' => 0,
            'contratos_preventivos' => $novedades['total_preventivos'],
            'contratos_vencidos' => $novedades['total_vencidos'],
            'avisos_registrados' => 0,
            'omitido' => null,
        ];

        if ($novedades['total_preventivos'] === 0 && $novedades['total_vencidos'] === 0) {
            return array_merge($base, ['omitido' => 'sin_novedades']);
        }

        if ($simular) {
            return array_merge($base, ['omitido' => 'simulacion']);
        }

        $enviados = 0;
        $notificados = [];

        foreach ([
            self::CODIGO_VENCIMIENTO => $novedades['preventivos'],
            self::CODIGO_VENCIDO => $novedades['vencidos'],
        ] as $codigo => $contratos) {
            if ($contratos === []) {
                continue;
            }

            $resultado = $this->despachar($codigo, $contratos);
            $enviados += $resultado['enviados'];
            foreach ($resultado['contratos'] as $contrato) {
                $notificados[] = $contrato;
            }
        }

        if ($enviados === 0) {
            Log::info('OrdencompraContratoAvisoService: novedades sin destinatarios', [
                'preventivos' => $novedades['total_preventivos'],
                'vencidos' => $novedades['total_vencidos'],
            ]);

            return array_merge($base, ['omitido' => 'sin_destinatarios']);
        }

        $registrados = $this->registrarAvisos($notificados);

        return array_merge($base, [
            'enviados' => $enviados,
            'avisos_registrados' => $registrados,
        ]);
    }

    /**
     * Un mail por destinatario con solo los contratos que le corresponden.
     *
     * @param  list<array<string, mixed>>  $contratos
     * @return array{enviados: int, contratos: list<array<string, mixed>>}
     */
    private function despachar(string $codigo, array $contratos): array
    {
        $tipo = ModuloAvisoTipo::query()
            ->where('modulo', self::MODULO)
            ->where('codigo', $codigo)
            ->where('activo', true)
            ->first();

        if (! $tipo) {
            return ['enviados' => 0, 'contratos' => []];
        }

        /** @var array<string, array<int, array<string, mixed>>> $porEmail */
        $porEmail = [];

        foreach ($tipo->destinatarios()->where('activo', true)->with('usuarios')->get() as $destinatario) {
            $email = $destinatario->emailResuelto();
            if ($email === null || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $email = strtolower($email);
            $empresaDest = $destinatario->empresa_id ? (int) $destinatario->empresa_id : null;

            foreach ($contratos as $contrato) {
                if ($empresaDest !== null && $empresaDest !== (int) $contrato['empresa_id']) {
                    continue;
                }
                $porEmail[$email][(int) $contrato['id']] = $contrato;
            }
        }

        // El responsable nominado del contrato siempre recibe los suyos, esté o no
        // configurado como destinatario del evento.
        foreach ($contratos as $contrato) {
            $email = strtolower(trim((string) ($contrato['responsable_email'] ?? '')));
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $porEmail[$email][(int) $contrato['id']] = $contrato;
        }

        $enviados = 0;
        $notificados = [];

        foreach ($porEmail as $email => $contratosEmail) {
            if ($contratosEmail === []) {
                continue;
            }

            $this->moduloAvisoService->enviar(self::MODULO, $codigo, 0, [
                'contratos' => array_values($contratosEmail),
                'emails' => [$email],
                'empresa_id' => null,
            ]);

            $enviados++;
            foreach ($contratosEmail as $contratoId => $contrato) {
                $notificados[$contratoId] = $contrato;
            }
        }

        return ['enviados' => $enviados, 'contratos' => array_values($notificados)];
    }

    /**
     * @param  list<array<string, mixed>>  $contratos
     */
    private function registrarAvisos(array $contratos): int
    {
        $registrados = 0;
        $ahora = now();

        foreach ($contratos as $contrato) {
            foreach ($contrato['avisos'] ?? [] as $aviso) {
                try {
                    Ordencompra_Contrato_Aviso::query()->firstOrCreate(
                        [
                            'ordencompra_id' => (int) $contrato['id'],
                            'clave' => (string) $aviso['clave'],
                        ],
                        [
                            'tipo_aviso' => (string) $aviso['tipo'],
                            'umbral' => (int) $aviso['umbral'],
                            'fecha_referencia' => $aviso['fecha_referencia'] ?? null,
                            'monto_consumido' => $aviso['monto_consumido'] ?? null,
                            'porcentaje_consumido' => $aviso['porcentaje_consumido'] ?? null,
                            'destinatarios' => null,
                            'enviado_at' => $ahora,
                        ]
                    );
                    $registrados++;
                } catch (\Throwable $e) {
                    Log::warning('OrdencompraContratoAvisoService: no se pudo registrar el aviso', [
                        'ordencompra_id' => $contrato['id'] ?? null,
                        'clave' => $aviso['clave'] ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return $registrados;
    }
}
