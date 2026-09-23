<?php

declare(strict_types=1);

namespace App\Services\Caja\Ai;

use App\Services\Ai\AiGateway;
use App\Services\Ai\AiPrompt;
use App\Services\Ai\Skills\AiSkillContext;
use App\Services\Ai\Skills\AiSkillInterface;
use App\Services\Ai\Skills\AiSkillResult;
use App\Support\Caja\IngresoEgresoCanjeChequeSupport;

/**
 * Frase corta de detalle para un canje/reemplazo de cheques (sin inventar datos).
 */
final class RedactarDetalleCanjeChequeSkill implements AiSkillInterface
{
    public const NOMBRE = 'redactar_detalle_canje_cheque';

    public const ENTIDAD = 'caja_movimiento_canje_cheque';

    public function __construct(
        private readonly AiGateway $gateway,
    ) {
    }

    public function nombre(): string
    {
        return self::NOMBRE;
    }

    public function ejecutar(AiSkillContext $contexto): AiSkillResult
    {
        $cheques = $contexto->entrada('cheques');
        if (! is_array($cheques) || $cheques === []) {
            return AiSkillResult::fallo('La skill requiere cheques a canjear.');
        }

        $fallback = IngresoEgresoCanjeChequeSupport::detalleDeterministico($cheques);
        $lineas = [];
        foreach ($cheques as $i => $ch) {
            if (! is_array($ch)) {
                continue;
            }
            $lineas[] = sprintf(
                '%d) origen=%s nro=%s banco=%s monto=%s a_nombre=%s fechapago=%s cuenta=%s',
                $i + 1,
                strtoupper(trim((string) ($ch['origen'] ?? ''))) === 'E' ? 'emitido' : 'recibido',
                trim((string) ($ch['numerocheque'] ?? '')),
                trim((string) ($ch['banco'] ?? '')),
                trim((string) ($ch['monto'] ?? '')),
                trim((string) ($ch['anombrede'] ?? '')),
                trim((string) ($ch['fechapago'] ?? '')),
                trim((string) ($ch['cuentacaja_nombre'] ?? $ch['cuentacaja_codigo'] ?? '')),
            );
        }

        if ($lineas === []) {
            return AiSkillResult::fallo('No hay cheques válidos para redactar.');
        }

        $system = <<<'SYS'
Sos un tesorero de un ERP argentino.
Con los datos de cheques a canjear/reemplazar, redactá UNA sola frase corta en español para el campo Detalle del movimiento de caja.
Reglas:
- No inventes números, bancos, montos ni beneficiarios que no estén en el listado.
- Empezá con "Canje" o "Canje de cheque(s)".
- Incluí nro. de cheque y, si hay, banco y monto.
- Máximo 220 caracteres.
- Devolvé JSON estricto: {"detalle":"..."}
SYS;

        $result = $this->gateway->generar(new AiPrompt(
            prompt: "Cheques a canjear:\n".implode("\n", $lineas)."\n\nRespondé SOLO JSON.",
            system: $system,
            esperaJson: true,
            temperature: 0.2,
            maxTokens: 200,
            timeout: (int) config('ai.skills.redactar_detalle_canje_cheque.timeout', 25),
            meta: ['skill' => self::NOMBRE, 'n' => count($lineas)],
        ));

        if (! $result->ok || ! is_array($result->json)) {
            return AiSkillResult::sugerencia(['detalle' => $fallback, 'fuente' => 'deterministico'], 0.4, [
                $result->error ?? 'El modelo no devolvió JSON; se usó detalle determinístico.',
            ]);
        }

        $texto = trim((string) ($result->json['detalle'] ?? ''));
        if ($texto === '') {
            return AiSkillResult::sugerencia(['detalle' => $fallback, 'fuente' => 'deterministico'], 0.4, [
                'Detalle vacío del modelo; se usó fallback.',
            ]);
        }
        if (mb_strlen($texto) > 255) {
            $texto = mb_substr($texto, 0, 252).'…';
        }

        return AiSkillResult::sugerencia(['detalle' => $texto, 'fuente' => 'ia'], 0.75);
    }
}
