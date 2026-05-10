<?php

namespace App\Models\Uif;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class Cliente_Premio_Archivo_Uif extends Model
{
    protected $fillable = ['cliente_premio_uif_id', 'nombrearchivo'];
    protected $table = 'cliente_premio_archivo_uif';

	public function cliente_premio_uifs()
	{
    	return $this->belongsTo(Cliente_Premio_Uif::class, 'cliente_premio_uif_id', 'id');
	}

    public static function setFoto($foto, $actual = false)
    {
        if ($foto) {
            if ($actual) {
                Storage::disk('public')->delete("imagenes/fotos_uif/$actual");
            }
            $imageName = Str::random(20) . '.jpg';
            $image = Image::decode($foto)
                ->resizeDown(300, 300);

            Storage::disk('public')->put(
                "imagenes/fotos_uif/$imageName",
                $image->encodeUsingFileExtension('jpg', quality: 75)
            );
            return $imageName;
        } else {
            return false;
        }
    }

}
