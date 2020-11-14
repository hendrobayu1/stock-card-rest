<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LogKartuStok extends Model
{
    protected $table = 'if_kartu_stok_log';
    public $incrementing = true;
    public $timestamps = false;
    protected $fillable = ['userid','route','process_name','params','response_message','device_info','created_date'];
}
