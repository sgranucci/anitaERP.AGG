<?php

namespace App\Support\Caja\Flash;

use App\Mail\Caja\Flash\FlashCajaModificacionHabilitacionMail;
use App\Models\Caja\Flash\FlashCaja;
use App\Models\Caja\Flash\FlashCajaModificacion;
use App\Models\Caja\Flash\FlashCajaModificacionDestinatario;
use App\Models\Caja\RendicionMaquina;
use App\Models\Seguridad\Usuario;
use App\Support\Seguridad\UsuarioOperativoSupport;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use InvalidArgumentException;

/**
 * Flash con tilde verde y cierre contable de máquinas: no se modifica
 * hasta que contaduría habilita el cambio desde el enlace del mail.
 */
final class FlashCajaModificacionSupport
{
    private const DIAS_LINK = 7;

    public static function diaTieneCierreContable(int $empresaId, string $fecha): bool
    {
        if ($empresaId <= 0 || trim($fecha) === '') {
            return false;
        }

        $fechaYmd = Carbon::parse($fecha)->toDateString();

        return RendicionMaquina::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha', $fechaYmd)
            ->where('turno', 'C')
            ->where('estado', RendicionMaquina::ESTADO_CONFIRMADA)
            ->where(function ($query) {
                $query->where('cierre_contable_legacy', true)
                    ->orWhere(function ($cerrado) {
                        $cerrado->where('asiento_id', '>', 0)
                            ->whereNotNull('cierre_contable_en');
                    });
            })
            ->exists();
    }

    public static function estaBloqueado(FlashCaja $flash): bool
    {
        if (! $flash->estaValidado()) {
            return false;
        }

        $fecha = $flash->fecha?->format('Y-m-d') ?? '';
        if (! self::diaTieneCierreContable((int) $flash->empresa_id, $fecha)) {
            return false;
        }

        return self::habilitacionVigente((int) $flash->id) === null;
    }

    public static function habilitacionVigente(int $flashId): ?FlashCajaModificacion
    {
        if ($flashId <= 0) {
            return null;
        }

        return FlashCajaModificacion::query()
            ->where('flash_caja_id', $flashId)
            ->where('estado', FlashCajaModificacion::ESTADO_HABILITADA)
            ->orderByDesc('id')
            ->first();
    }

    public static function solicitudPendiente(int $flashId): ?FlashCajaModificacion
    {
        if ($flashId <= 0) {
            return null;
        }

        return FlashCajaModificacion::query()
            ->where('flash_caja_id', $flashId)
            ->where('estado', FlashCajaModificacion::ESTADO_PENDIENTE)
            ->orderByDesc('id')
            ->first();
    }

    public static function assertPuedeModificar(FlashCaja $flash): void
    {
        if (! self::estaBloqueado($flash)) {
            return;
        }

        $fecha = $flash->fecha?->format('d/m/Y') ?? '';
        throw new InvalidArgumentException(
            'El flash del '.$fecha
            .' tiene tilde verde y la jornada ya tiene cierre contable de máquinas. '
            .'Hay que pedir la habilitación a contaduría antes de modificarlo.'
        );
    }

    public static function consumirHabilitacion(int $flashId): void
    {
        $vigente = self::habilitacionVigente($flashId);
        if ($vigente === null) {
            return;
        }

        $vigente->estado = FlashCajaModificacion::ESTADO_USADA;
        $vigente->usada_en = now();
        $vigente->save();
    }

    /**
     * @return list<Usuario>
     */
    public static function destinatarios(): array
    {
        if (! \Illuminate\Support\Facades\Schema::hasTable('flash_caja_modificacion_destinatario')) {
            return [];
        }

        $ids = FlashCajaModificacionDestinatario::query()
            ->orderBy('id')
            ->pluck('usuario_id')
            ->map(static fn ($id) => (int) $id)
            ->filter(static fn (int $id) => $id > 0)
            ->values()
            ->all();
        $ids = UsuarioOperativoSupport::filtrarIdsActivos($ids);
        if ($ids === []) {
            return [];
        }

        return UsuarioOperativoSupport::query()
            ->whereIn('id', $ids)
            ->orderBy('nombre')
            ->get()
            ->all();
    }

    public static function esDestinatario(int $usuarioId): bool
    {
        if ($usuarioId <= 0) {
            return false;
        }

        foreach (self::destinatarios() as $usuario) {
            if ((int) $usuario->id === $usuarioId) {
                return true;
            }
        }

        return false;
    }

    public static function solicitar(FlashCaja $flash, int $solicitanteId, string $motivo): FlashCajaModificacion
    {
        if (! $flash->estaValidado() || ! self::diaTieneCierreContable(
            (int) $flash->empresa_id,
            (string) ($flash->fecha?->format('Y-m-d') ?? ''),
        )) {
            throw new InvalidArgumentException('Este flash no está bloqueado por tilde y cierre contable.');
        }

        if (self::habilitacionVigente((int) $flash->id) !== null) {
            throw new InvalidArgumentException('Contaduría ya habilitó la modificación de este flash.');
        }

        $motivo = trim($motivo);
        if (mb_strlen($motivo) < 10) {
            throw new InvalidArgumentException('Indique el motivo del cambio (al menos 10 caracteres).');
        }

        $destinatarios = self::destinatarios();
        if ($destinatarios === []) {
            throw new InvalidArgumentException('No hay destinatarios de contaduría con mail para habilitar el cambio.');
        }

        return DB::transaction(function () use ($flash, $solicitanteId, $motivo, $destinatarios) {
            $pendiente = self::solicitudPendiente((int) $flash->id);
            if ($pendiente === null) {
                $pendiente = FlashCajaModificacion::query()->create([
                    'flash_caja_id' => (int) $flash->id,
                    'estado' => FlashCajaModificacion::ESTADO_PENDIENTE,
                    'motivo' => $motivo,
                    'solicitante_usuario_id' => $solicitanteId,
                    'solicitada_en' => now(),
                ]);
            } else {
                $pendiente->motivo = $motivo;
                $pendiente->solicitante_usuario_id = $solicitanteId;
                $pendiente->solicitada_en = now();
                $pendiente->save();
            }

            $pendiente->load(['flash.empresa', 'solicitante']);
            self::enviarMail($pendiente, $destinatarios);

            return $pendiente;
        });
    }

    public static function habilitar(int $solicitudId, int $usuarioId): FlashCajaModificacion
    {
        if (! self::esDestinatario($usuarioId)) {
            throw new InvalidArgumentException('Este enlace es para contaduría.');
        }

        $solicitud = FlashCajaModificacion::query()->find($solicitudId);
        if ($solicitud === null) {
            throw new InvalidArgumentException('No se encontró el pedido de modificación.');
        }

        if ($solicitud->estado === FlashCajaModificacion::ESTADO_HABILITADA) {
            return $solicitud;
        }

        if ($solicitud->estado === FlashCajaModificacion::ESTADO_USADA) {
            throw new InvalidArgumentException('Esa habilitación ya se usó.');
        }

        if ($solicitud->estado !== FlashCajaModificacion::ESTADO_PENDIENTE) {
            throw new InvalidArgumentException('El pedido de modificación no está pendiente.');
        }

        $solicitud->estado = FlashCajaModificacion::ESTADO_HABILITADA;
        $solicitud->habilitada_usuario_id = $usuarioId;
        $solicitud->habilitada_en = now();
        $solicitud->save();

        return $solicitud;
    }

    public static function urlHabilitacion(int $solicitudId): string
    {
        return URL::temporarySignedRoute(
            'habilitar_flash_caja_modificacion',
            now()->addDays(self::DIAS_LINK),
            ['id' => $solicitudId],
        );
    }

    /**
     * @param  list<Usuario>  $destinatarios
     */
    private static function enviarMail(FlashCajaModificacion $solicitud, array $destinatarios): void
    {
        $url = self::urlHabilitacion((int) $solicitud->id);
        $enviados = 0;
        foreach ($destinatarios as $usuario) {
            $email = trim((string) ($usuario->email ?? ''));
            if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                continue;
            }
            try {
                Mail::to($email)->send(new FlashCajaModificacionHabilitacionMail($solicitud, $url));
                $enviados++;
            } catch (\Throwable $e) {
                Log::warning('flash.modificacion.mail', [
                    'solicitud_id' => $solicitud->id,
                    'usuario' => $usuario->usuario,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($enviados === 0) {
            throw new InvalidArgumentException('No se pudo enviar el mail a contaduría.');
        }
    }
}
