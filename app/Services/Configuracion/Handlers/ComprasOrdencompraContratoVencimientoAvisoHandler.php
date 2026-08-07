<?php

namespace App\Services\Configuracion\Handlers;

use App\Contracts\Configuracion\ModuloAvisoDespachoHandlerInterface;
use App\Mail\Configuracion\ModuloAvisoMail;
use App\Models\Compras\Ordencompra_Contrato_Aviso;
use App\Models\Configuracion\ModuloAvisoTipo;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Compras\OrdencompraContratoVencimientoSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Aviso de vencimiento de contratos / OC abiertas.
 *
 * Sirve tanto al evento preventivo (por vencer, preaviso de no renovación, consumo del tope)
 * como al de escalamiento (ya vencidos). El servicio arma la lista de contratos por
 * destinatario; acá solo se renderiza y envía.
 */
class ComprasOrdencompraContratoVencimientoAvisoHandler implements ModuloAvisoDespachoHandlerInterface
{
    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    public function despachar(ModuloAvisoTipo $tipo, int $entityId, array $opciones = []): void
    {
        $contratos = $opciones['contratos'] ?? null;
        if (! is_array($contratos) || $contratos === []) {
            return;
        }

        $emails = $opciones['emails'] ?? null;
        if (! is_array($emails) || $emails === []) {
            $emails = $this->moduloAvisoService->resolverEmailsDestinatarios($tipo, [
                'empresa_id' => $opciones['empresa_id'] ?? null,
                'centrocosto_id' => null,
            ]);
        }

        $emails = array_values(array_unique(array_filter(array_map(
            static fn ($e) => strtolower(trim((string) $e)),
            $emails
        ))));
        if ($emails === []) {
            return;
        }

        $placeholders = $this->placeholdersDesdeContratos($contratos);
        $linkConsulta = $tipo->incluir_link_consulta ? $this->linkConsulta(0) : null;
        $asunto = $this->aplicarPlaceholders((string) $tipo->mail_asunto, $placeholders, $linkConsulta);
        $texto = $this->aplicarPlaceholders((string) ($tipo->mail_texto ?? ''), $placeholders, $linkConsulta);

        foreach ($emails as $email) {
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            try {
                $mailable = new ModuloAvisoMail($asunto, $texto, $tipo->nombre, $linkConsulta, null);
                if (! empty($tipo->mail_remitente)) {
                    $mailable->from($tipo->mail_remitente);
                }
                Mail::to($email)->queue($mailable);
            } catch (\Throwable $e) {
                Log::warning('ComprasOrdencompraContratoVencimientoAviso: error envío', [
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function contextoFiltro(int $entityId): array
    {
        return [
            'empresa_id' => null,
            'centrocosto_id' => null,
        ];
    }

    public function placeholders(int $entityId): array
    {
        $novedades = OrdencompraContratoVencimientoSupport::novedades();

        return $this->placeholdersDesdeContratos(array_merge(
            $novedades['preventivos'],
            $novedades['vencidos']
        ));
    }

    public function linkConsulta(int $entityId): ?string
    {
        return url('compras/contrato-vencimiento-reporte');
    }

    public function generarPdf(int $entityId): ?array
    {
        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $contratos
     * @return array<string, string>
     */
    private function placeholdersDesdeContratos(array $contratos): array
    {
        $buckets = [
            Ordencompra_Contrato_Aviso::TIPO_VIGENCIA => [],
            Ordencompra_Contrato_Aviso::TIPO_PREAVISO => [],
            Ordencompra_Contrato_Aviso::TIPO_CONSUMO => [],
            Ordencompra_Contrato_Aviso::TIPO_VENCIDO => [],
        ];

        foreach ($contratos as $contrato) {
            $tipo = $contrato['aviso_principal']['tipo'] ?? Ordencompra_Contrato_Aviso::TIPO_VIGENCIA;
            $buckets[$tipo][] = $contrato;
        }

        return [
            'fecha' => now()->format('d/m/Y'),
            'cantidad_contratos' => (string) count($contratos),
            'cantidad_por_vencer' => (string) count($buckets[Ordencompra_Contrato_Aviso::TIPO_VIGENCIA]),
            'cantidad_preaviso' => (string) count($buckets[Ordencompra_Contrato_Aviso::TIPO_PREAVISO]),
            'cantidad_consumo' => (string) count($buckets[Ordencompra_Contrato_Aviso::TIPO_CONSUMO]),
            'cantidad_vencidos' => (string) count($buckets[Ordencompra_Contrato_Aviso::TIPO_VENCIDO]),
            'contratos' => OrdencompraContratoVencimientoSupport::formatearLista($contratos),
            'contratos_por_vencer' => OrdencompraContratoVencimientoSupport::formatearLista(
                $buckets[Ordencompra_Contrato_Aviso::TIPO_VIGENCIA]
            ),
            'contratos_preaviso' => OrdencompraContratoVencimientoSupport::formatearLista(
                $buckets[Ordencompra_Contrato_Aviso::TIPO_PREAVISO]
            ),
            'contratos_consumo' => OrdencompraContratoVencimientoSupport::formatearLista(
                $buckets[Ordencompra_Contrato_Aviso::TIPO_CONSUMO]
            ),
            'contratos_vencidos' => OrdencompraContratoVencimientoSupport::formatearLista(
                $buckets[Ordencompra_Contrato_Aviso::TIPO_VENCIDO]
            ),
        ];
    }

    /** @param  array<string, string>  $placeholders */
    private function aplicarPlaceholders(string $plantilla, array $placeholders, ?string $linkConsulta): string
    {
        $mapa = array_merge($placeholders, ['link_consulta' => $linkConsulta ?? '']);
        $resultado = preg_replace_callback('/\{([a-z0-9_]+)\}/i', function (array $m) use ($mapa) {
            return $mapa[strtolower($m[1])] ?? $m[0];
        }, $plantilla);

        return is_string($resultado) ? $resultado : $plantilla;
    }
}
