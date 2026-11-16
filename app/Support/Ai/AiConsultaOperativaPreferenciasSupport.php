<?php

namespace App\Support\Ai;

use App\Models\Ai\AiCopilotoPreferencia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Session;
use Throwable;

/**
 * Preferencias del copiloto: sesión (UI) + BD (digest / sobrevive flush cache).
 */
final class AiConsultaOperativaPreferenciasSupport
{
    public const SESSION_KEY = 'ai_consulta_operativa_prefs';

    public const SESSION_BRIEF_AVISO = 'ai_consulta_brief_aviso_fecha';

    /**
     * @return array{
     *   empresa_id: int|null,
     *   centrocosto_codigo: string|null,
     *   deposito_codigo: string|null,
     *   asistente_rol: string,
     *   auto_brief: bool,
     *   notify_brief_campanita: bool,
     *   notify_brief_mail: bool,
     *   notify_alerta_umbral: bool,
     *   umbral_cheques_vencidos: float|null,
     *   umbral_deuda_ap: float|null
     * }
     */
    public static function leer(): array
    {
        $raw = Session::get(self::SESSION_KEY, []);
        if (! is_array($raw) || $raw === []) {
            $uid = Auth::id() ? (int) Auth::id() : 0;
            if ($uid > 0) {
                $persisted = self::leerPersistidas($uid);
                if ($persisted !== null) {
                    Session::put(self::SESSION_KEY, $persisted);

                    return $persisted;
                }
            }
            $raw = [];
        }

        return self::normalizarRaw($raw);
    }

    /**
     * @param  array<string,mixed>  $input
     * @return array{
     *   empresa_id: int|null,
     *   centrocosto_codigo: string|null,
     *   deposito_codigo: string|null,
     *   asistente_rol: string,
     *   auto_brief: bool,
     *   notify_brief_campanita: bool,
     *   notify_brief_mail: bool,
     *   notify_alerta_umbral: bool,
     *   umbral_cheques_vencidos: float|null,
     *   umbral_deuda_ap: float|null
     * }
     */
    public static function guardar(array $input): array
    {
        $actual = self::leer();
        if (array_key_exists('empresa_id', $input)) {
            $v = $input['empresa_id'];
            $actual['empresa_id'] = (is_numeric($v) && (int) $v > 0) ? (int) $v : null;
        }
        if (array_key_exists('centrocosto_codigo', $input)) {
            $actual['centrocosto_codigo'] = self::textoOpcional($input['centrocosto_codigo']);
        }
        if (array_key_exists('deposito_codigo', $input)) {
            $actual['deposito_codigo'] = self::textoOpcional($input['deposito_codigo']);
        }
        if (array_key_exists('asistente_rol', $input)) {
            $actual['asistente_rol'] = AiConsultaOperativaAsistenteRolSupport::normalizar(
                is_scalar($input['asistente_rol']) ? (string) $input['asistente_rol'] : null
            );
        }
        if (array_key_exists('auto_brief', $input)) {
            $actual['auto_brief'] = filter_var($input['auto_brief'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('notify_brief_campanita', $input)) {
            $actual['notify_brief_campanita'] = filter_var($input['notify_brief_campanita'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('notify_brief_mail', $input)) {
            $actual['notify_brief_mail'] = filter_var($input['notify_brief_mail'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('notify_alerta_umbral', $input)) {
            $actual['notify_alerta_umbral'] = filter_var($input['notify_alerta_umbral'], FILTER_VALIDATE_BOOLEAN);
        }
        if (array_key_exists('umbral_cheques_vencidos', $input)) {
            $actual['umbral_cheques_vencidos'] = self::montoOpcional($input['umbral_cheques_vencidos']);
        }
        if (array_key_exists('umbral_deuda_ap', $input)) {
            $actual['umbral_deuda_ap'] = self::montoOpcional($input['umbral_deuda_ap']);
        }
        if (array_key_exists('umbral_tickets_abiertos', $input)) {
            $actual['umbral_tickets_abiertos'] = self::enteroOpcional($input['umbral_tickets_abiertos']);
        }
        if (array_key_exists('umbral_stock_critico', $input)) {
            $actual['umbral_stock_critico'] = self::enteroOpcional($input['umbral_stock_critico']);
        }

        Session::put(self::SESSION_KEY, $actual);

        $uid = Auth::id() ? (int) Auth::id() : 0;
        if ($uid > 0) {
            self::persistir($uid, $actual);
        }

        return $actual;
    }

    /**
     * @return array{
     *   empresa_id: int|null,
     *   centrocosto_codigo: string|null,
     *   deposito_codigo: string|null,
     *   asistente_rol: string,
     *   auto_brief: bool,
     *   notify_brief_campanita: bool,
     *   notify_brief_mail: bool,
     *   notify_alerta_umbral: bool,
     *   umbral_cheques_vencidos: float|null,
     *   umbral_deuda_ap: float|null
     * }|null
     */
    public static function leerPersistidas(int $usuarioId): ?array
    {
        if ($usuarioId <= 0 || ! self::tablaLista()) {
            return null;
        }
        try {
            $row = AiCopilotoPreferencia::query()->where('usuario_id', $usuarioId)->first();
        } catch (Throwable) {
            return null;
        }
        if (! $row) {
            return null;
        }

        $raw = [
            'empresa_id' => $row->empresa_id,
            'centrocosto_codigo' => $row->centrocosto_codigo,
            'deposito_codigo' => $row->deposito_codigo,
            'asistente_rol' => $row->asistente_rol,
            'auto_brief' => $row->auto_brief,
            'notify_brief_campanita' => $row->notify_brief_campanita,
            'notify_brief_mail' => $row->notify_brief_mail,
        ];
        if (AiConsultaAlertaUmbralSupport::columnasListas()) {
            $raw['notify_alerta_umbral'] = $row->notify_alerta_umbral ?? false;
            $raw['umbral_cheques_vencidos'] = $row->umbral_cheques_vencidos ?? null;
            $raw['umbral_deuda_ap'] = $row->umbral_deuda_ap ?? null;
            $raw['umbral_tickets_abiertos'] = $row->umbral_tickets_abiertos ?? null;
            $raw['umbral_stock_critico'] = $row->umbral_stock_critico ?? null;
        }

        return self::normalizarRaw($raw);
    }

    /**
     * IDs con mail de brief opt-in (digest).
     *
     * @return list<int>
     */
    public static function usuariosDigestMail(): array
    {
        if (! self::tablaLista()) {
            return [];
        }
        try {
            return AiCopilotoPreferencia::query()
                ->where('notify_brief_mail', true)
                ->orderBy('usuario_id')
                ->pluck('usuario_id')
                ->map(fn ($id) => (int) $id)
                ->filter(fn (int $id) => $id > 0)
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    public static function debeSugerirBrief(): bool
    {
        $prefs = self::leer();
        if (! $prefs['auto_brief']) {
            return false;
        }
        $hoy = date('Y-m-d');
        $marcado = Session::get(self::SESSION_BRIEF_AVISO);

        return $marcado !== $hoy;
    }

    public static function marcarBriefSugerido(): void
    {
        Session::put(self::SESSION_BRIEF_AVISO, date('Y-m-d'));
    }

    /**
     * @param  array<string,mixed>  $params
     * @return array<string,mixed>
     */
    public static function aplicarAParams(array $params): array
    {
        $prefs = self::leer();
        if (empty($params['empresa_id']) && ! empty($prefs['empresa_id'])) {
            $params['empresa_id'] = $prefs['empresa_id'];
        }
        if (empty($params['centrocosto_codigo']) && ! empty($prefs['centrocosto_codigo'])) {
            $params['centrocosto_codigo'] = $prefs['centrocosto_codigo'];
        }
        if (empty($params['deposito_codigo']) && ! empty($prefs['deposito_codigo'])) {
            $params['deposito_codigo'] = $prefs['deposito_codigo'];
        }
        if (empty($params['asistente_rol'])) {
            $params['asistente_rol'] = $prefs['asistente_rol'];
        }

        return $params;
    }

    /**
     * @param  array{
     *   empresa_id: int|null,
     *   centrocosto_codigo: string|null,
     *   deposito_codigo: string|null,
     *   asistente_rol: string,
     *   auto_brief: bool,
     *   notify_brief_campanita: bool,
     *   notify_brief_mail: bool,
     *   notify_alerta_umbral: bool,
     *   umbral_cheques_vencidos: float|null,
     *   umbral_deuda_ap: float|null
     * }  $prefs
     */
    private static function persistir(int $usuarioId, array $prefs): void
    {
        if (! self::tablaLista()) {
            return;
        }
        try {
            $data = [
                'empresa_id' => $prefs['empresa_id'],
                'centrocosto_codigo' => $prefs['centrocosto_codigo'],
                'deposito_codigo' => $prefs['deposito_codigo'],
                'asistente_rol' => $prefs['asistente_rol'],
                'auto_brief' => $prefs['auto_brief'],
                'notify_brief_campanita' => $prefs['notify_brief_campanita'],
                'notify_brief_mail' => $prefs['notify_brief_mail'],
            ];
            if (AiConsultaAlertaUmbralSupport::columnasListas()) {
                $data['notify_alerta_umbral'] = $prefs['notify_alerta_umbral'];
                $data['umbral_cheques_vencidos'] = $prefs['umbral_cheques_vencidos'];
                $data['umbral_deuda_ap'] = $prefs['umbral_deuda_ap'];
                if (Schema::hasColumn('ai_copiloto_preferencia', 'umbral_tickets_abiertos')) {
                    $data['umbral_tickets_abiertos'] = $prefs['umbral_tickets_abiertos'];
                    $data['umbral_stock_critico'] = $prefs['umbral_stock_critico'];
                }
            }
            AiCopilotoPreferencia::query()->updateOrCreate(
                ['usuario_id' => $usuarioId],
                $data
            );
        } catch (Throwable) {
            // no romper FAB si falta migración
        }
    }

    private static function tablaLista(): bool
    {
        try {
            return Schema::hasTable('ai_copiloto_preferencia');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @param  array<string,mixed>  $raw
     * @return array{
     *   empresa_id: int|null,
     *   centrocosto_codigo: string|null,
     *   deposito_codigo: string|null,
     *   asistente_rol: string,
     *   auto_brief: bool,
     *   notify_brief_campanita: bool,
     *   notify_brief_mail: bool,
     *   notify_alerta_umbral: bool,
     *   umbral_cheques_vencidos: float|null,
     *   umbral_deuda_ap: float|null
     * }
     */
    private static function normalizarRaw(array $raw): array
    {
        $empresaId = isset($raw['empresa_id']) && is_numeric($raw['empresa_id']) ? (int) $raw['empresa_id'] : null;
        if ($empresaId !== null && $empresaId <= 0) {
            $empresaId = null;
        }

        return [
            'empresa_id' => $empresaId,
            'centrocosto_codigo' => self::textoOpcional($raw['centrocosto_codigo'] ?? null),
            'deposito_codigo' => self::textoOpcional($raw['deposito_codigo'] ?? null),
            'asistente_rol' => AiConsultaOperativaAsistenteRolSupport::normalizar(
                isset($raw['asistente_rol']) ? (string) $raw['asistente_rol'] : AiConsultaOperativaAsistenteRolSupport::AUTO
            ),
            'auto_brief' => array_key_exists('auto_brief', $raw)
                ? (bool) $raw['auto_brief']
                : true,
            'notify_brief_campanita' => array_key_exists('notify_brief_campanita', $raw)
                ? (bool) $raw['notify_brief_campanita']
                : true,
            'notify_brief_mail' => array_key_exists('notify_brief_mail', $raw)
                ? (bool) $raw['notify_brief_mail']
                : false,
            'notify_alerta_umbral' => array_key_exists('notify_alerta_umbral', $raw)
                ? (bool) $raw['notify_alerta_umbral']
                : false,
            'umbral_cheques_vencidos' => self::montoOpcional($raw['umbral_cheques_vencidos'] ?? null),
            'umbral_deuda_ap' => self::montoOpcional($raw['umbral_deuda_ap'] ?? null),
            'umbral_tickets_abiertos' => self::enteroOpcional($raw['umbral_tickets_abiertos'] ?? null),
            'umbral_stock_critico' => self::enteroOpcional($raw['umbral_stock_critico'] ?? null),
        ];
    }

    private static function textoOpcional(mixed $v): ?string
    {
        if (! is_scalar($v)) {
            return null;
        }
        $t = trim((string) $v);

        return $t !== '' ? $t : null;
    }

    private static function montoOpcional(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (! is_numeric($v)) {
            return null;
        }
        $n = (float) $v;

        return $n > 0 ? $n : null;
    }

    private static function enteroOpcional(mixed $v): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (! is_numeric($v)) {
            return null;
        }
        $n = (int) $v;

        return $n > 0 ? $n : null;
    }
}
