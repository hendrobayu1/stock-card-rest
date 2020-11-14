<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UDDTransDetilTemp extends Model
{
    protected $table = 'if_udd_trans_detil_temp';
    // protected $primaryKey = "ID_TRANS";
    // protected $keyType = "string";
    public $incrementing = false;
    public $timestamps=false;
    protected $fillable = ['id_trans','idx_pemberian','jadwal_tgl_pemberian','jadwal_jam_pemberian',
                        'no','id','kdbrg','jumlah','active','inputby','inputdate'];
}
