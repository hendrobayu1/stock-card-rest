<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class KartuBarangDetilTransaksi extends Model
{
    protected $table = "if_kartu_stok_dtransaksi";
    protected $primaryKey = 'id_detil_transaksi';
    public $incrementing = true;
    const CREATED_AT = 'inputdate';
    const UPDATED_AT = 'modidate';

    public function transaksi(){
        return $this->belongsTo('App\KartuBarangTransaksi');
    }

    public function kartustok(){
        return $this->belongsTo('App\KartuBarang');
    }
}
