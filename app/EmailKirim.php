<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EmailKirim extends Model
{
    protected $table = 'rirj_email_kirim';
    protected $primaryKey = 'id_sent';
    public $incrementing = true;
    public $timestamps=false;
}
