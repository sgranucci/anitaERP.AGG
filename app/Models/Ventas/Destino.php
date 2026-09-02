<?php

namespace App\Models\Ventas;

use App\ApiAnita;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Maestro destino SENASA (Anita `destino`, clave = zona de venta).
 */
class Destino extends Model
{
    protected $table = 'destino';

    protected $fillable = [
        'codigo',
        'zonavta_id',
        'localidad',
        'provincia',
        'pais_codigo',
        'patagonico',
        'codigo_localidad_senasa',
    ];

    protected $casts = [
        'patagonico' => 'boolean',
        'codigo' => 'integer',
        'pais_codigo' => 'integer',
        'codigo_localidad_senasa' => 'integer',
    ];

    public function zonavta(): BelongsTo
    {
        return $this->belongsTo(Zonavta::class, 'zonavta_id');
    }

    public static function tablaLista(): bool
    {
        return Schema::hasTable('destino');
    }

    public static function porCodigo(?int $codigo): ?self
    {
        $codigo = (int) $codigo;
        if ($codigo <= 0 || ! self::tablaLista()) {
            return null;
        }

        return self::query()->where('codigo', $codigo)->first();
    }

    /**
     * @return array{localidad: string, provincia: string, patagonico: bool, senasa: ?int}|null
     */
    public function aArraySenasa(): ?array
    {
        $localidad = trim((string) ($this->localidad ?? ''));
        $senasa = (int) ($this->codigo_localidad_senasa ?? 0);
        if ($localidad === '' && $senasa <= 0) {
            return null;
        }

        return [
            'localidad' => $localidad,
            'provincia' => trim((string) ($this->provincia ?? '')),
            'patagonico' => (bool) $this->patagonico,
            'senasa' => $senasa > 0 ? $senasa : null,
        ];
    }

    /**
     * @return array{insertados: int, actualizados: int, omitidos: int}
     */
    public static function sincronizarConAnita(): array
    {
        $stats = ['insertados' => 0, 'actualizados' => 0, 'omitidos' => 0];
        if (! self::tablaLista()) {
            return $stats;
        }

        try {
            $api = new ApiAnita();
            $raw = $api->apiCall([
                'acc' => 'list',
                'sistema' => 'ventas',
                'tabla' => 'destino',
                'campos' => 'dest_destino, dest_localidad, dest_provincia, dest_pais, dest_patagonico, dest_cod_localidad',
            ]);
            $filas = ApiAnita::decodificarListaFilas(is_string($raw) ? $raw : json_encode($raw));
        } catch (\Throwable $e) {
            Log::warning('destino.sincronizar_anita', ['mensaje' => $e->getMessage()]);

            return $stats;
        }

        foreach ($filas as $row) {
            $resultado = self::upsertDesdeFilaAnita($row);
            if ($resultado === 'insertado') {
                $stats['insertados']++;
            } elseif ($resultado === 'actualizado') {
                $stats['actualizados']++;
            } else {
                $stats['omitidos']++;
            }
        }

        return $stats;
    }

    /**
     * @return 'insertado'|'actualizado'|null
     */
    public static function upsertDesdeFilaAnita(object $row): ?string
    {
        if (! self::tablaLista()) {
            return null;
        }

        $codigo = (int) ($row->dest_destino ?? 0);
        $localidad = trim((string) ($row->dest_localidad ?? ''));
        if ($codigo <= 0 || $localidad === '') {
            return null;
        }

        $senasa = (int) ($row->dest_cod_localidad ?? 0);
        $payload = [
            'codigo' => $codigo,
            'zonavta_id' => Zonavta::query()->where('codigo', (string) $codigo)->value('id'),
            'localidad' => mb_substr($localidad, 0, 80),
            'provincia' => mb_substr(trim((string) ($row->dest_provincia ?? '')), 0, 80),
            'pais_codigo' => ($p = (int) ($row->dest_pais ?? 0)) > 0 ? $p : null,
            'patagonico' => strtoupper(substr(trim((string) ($row->dest_patagonico ?? 'N')), 0, 1)) === 'S',
            'codigo_localidad_senasa' => $senasa > 0 ? $senasa : null,
        ];

        $existente = self::query()->where('codigo', $codigo)->first();
        if ($existente) {
            $existente->update($payload);

            return 'actualizado';
        }

        self::create($payload);

        return 'insertado';
    }
}
