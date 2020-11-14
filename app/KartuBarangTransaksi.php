<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class KartuBarangTransaksi extends Model
{
    protected $table='if_kartu_stok_htransaksi';
    protected $primaryKey = 'id_transaksi';
    public $incrementing = true;
    const CREATED_AT = 'inputdate';
    const UPDATED_AT = 'modidate';

    public function detiltransaksi(){
        return $this->hasMany('App\KartuBarangDetilTransaksi','id_transaksi_kartu_stok','id_transaksi');
    }


}
