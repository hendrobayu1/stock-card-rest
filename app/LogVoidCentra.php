<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LogVoidCentra extends Model
{
    protected $table = 'RIRJ_LOG_VOID_CENTRA';
    public $incrementing = true;
    public $timestamps = false;
    protected $fillable = ['noreg','nota_jalan','id_trans_farmasi','doc_no','tgl_void','alasan','user_proses'];
}
