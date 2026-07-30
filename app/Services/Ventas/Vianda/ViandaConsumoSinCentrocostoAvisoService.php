<?php

namespace App\Services\Ventas\Vianda;

use App\Mail\Ventas\ViandaConsumoSinCentrocostoMail;
use App\Models\Ventas\ViandaConsumo;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Aviso a gerentes de gastronomía cuando se marcha una vianda sin centro de costo.
 * No bloquea la operación: solo notifica.
 */
final class ViandaConsumoSinCentrocostoAvisoService
{
    public function avisarSiCorresponde(ViandaConsumo $consumo): void
    {
        if (! filter_var(config('vianda.aviso_sin_centrocosto.habilitado', true), FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        if ((int) ($consumo->centrocosto_id ?? 0) > 0) {
            return;
        }

        $empresaId = (int) ($consumo->empresa_id ?? 0);
        $destinatarios = $this->destinatariosPorEmpresa($empresaId);
        if ($destinatarios === []) {
            Log::warning('vianda.consumo_sin_centrocosto.mail_sin_destinatarios', [
                'consumo_id' => $consumo->id,
                'empresa_id' => $empresaId,
            ]);

            return;
        }

        $consumo->loadMissing(['empresa', 'terminal', 'viandaUsuario']);

        $fecha = $consumo->fecha?->format('d/m/Y') ?? '';
        $hora = trim((string) ($consumo->hora ?? ''));
        $datos = [
            'empresa_id' => $empresaId,
            'empresa_nombre' => (string) ($consumo->empresa->nombre ?? ('Empresa #'.$empresaId)),
            'consumo_id' => (int) $consumo->id,
            'codigo_retiro' => (string) ($consumo->codigo_retiro ?? ''),
            'fecha_hora' => trim($fecha.($hora !== '' ? ' '.$hora : '')),
            'codigo_usuario' => (string) ($consumo->login_usuario ?? ($consumo->viandaUsuario->codigo_usuario ?? '')),
            'nombre_usuario' => (string) ($consumo->nombre_usuario ?? ($consumo->viandaUsuario->nombre ?? '')),
            'vianda_usuario_id' => (int) ($consumo->vianda_usuario_id ?? 0),
            'cantidad_items' => (int) ($consumo->cantidad_items ?? 0),
            'total_costo' => (float) ($consumo->total_costo ?? 0),
            'total_venta' => (float) ($consumo->total_venta ?? 0),
            'terminal' => (string) ($consumo->terminal->identificador_pc ?? '—'),
            'link_usuario' => $this->linkEditarUsuario((int) ($consumo->vianda_usuario_id ?? 0)),
        ];

        try {
            Mail::to($destinatarios)->send(new ViandaConsumoSinCentrocostoMail($datos));
            Log::info('vianda.consumo_sin_centrocosto.mail_enviado', [
                'consumo_id' => $consumo->id,
                'empresa_id' => $empresaId,
                'destinatarios' => $destinatarios,
            ]);
        } catch (Throwable $e) {
            Log::error('vianda.consumo_sin_centrocosto.mail_fallo', [
                'consumo_id' => $consumo->id,
                'empresa_id' => $empresaId,
                'destinatarios' => $destinatarios,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return list<string>
     */
    public function destinatariosPorEmpresa(int $empresaId): array
    {
        if ($empresaId <= 0) {
            return [];
        }

        /** @var array<int|string, list<string>|string> $porEmpresa */
        $porEmpresa = (array) config('vianda.aviso_sin_centrocosto.destinatarios_por_empresa', []);
        $raw = $porEmpresa[$empresaId] ?? $porEmpresa[(string) $empresaId] ?? [];
        $tokens = $this->normalizarTokens($raw);
        if ($tokens === []) {
            return [];
        }

        $emailsDirectos = [];
        $logins = [];
        foreach ($tokens as $token) {
            if (filter_var($token, FILTER_VALIDATE_EMAIL)) {
                $emailsDirectos[] = $token;
            } else {
                $logins[] = $token;
            }
        }

        $emailsPorLogin = [];
        if ($logins !== []) {
            $emailsPorLogin = UsuarioOperativoSupport::query()
                ->whereIn('usuario', $logins)
                ->whereNotNull('email')
                ->pluck('email')
                ->map(static fn ($e) => trim((string) $e))
                ->filter(static fn (string $e) => $e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL))
                ->all();
        }

        $vistos = [];
        $validos = [];
        foreach (array_merge($emailsDirectos, $emailsPorLogin) as $email) {
            $email = trim((string) $email);
            $clave = strtolower($email);
            if ($email === '' || isset($vistos[$clave]) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }
            $vistos[$clave] = true;
            $validos[] = $email;
        }

        return $validos;
    }

    /**
     * @param  list<string>|string  $raw
     * @return list<string>
     */
    private function normalizarTokens(array|string $raw): array
    {
        if (is_string($raw)) {
            $raw = preg_split('/[,;]+/', $raw) ?: [];
        }

        $out = [];
        foreach ($raw as $item) {
            $item = trim((string) $item);
            if ($item !== '') {
                $out[] = $item;
            }
        }

        return $out;
    }

    private function linkEditarUsuario(int $viandaUsuarioId): ?string
    {
        if ($viandaUsuarioId <= 0) {
            return null;
        }

        try {
            return route('editar_vianda_usuario_gastronomia', [
                'id' => $viandaUsuarioId,
                'origen' => 'mail_aviso',
                'vista' => 'consulta',
            ]);
        } catch (Throwable) {
            return null;
        }
    }
}
