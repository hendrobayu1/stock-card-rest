<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class FileUpload extends Model
{
    protected $table = 'rj_file_upload';
    protected $fillable = ['karcis','nama_file','ekstensi','ukuran'];

    public function getUkuranAttribute($value){
        return number_format($value/1024,2);
    }
}
