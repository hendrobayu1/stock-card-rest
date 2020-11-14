<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EmailHistory extends Model
{
    protected $table = 'rirj_email_log';
    protected $primaryKey = 'id_log';
    public $incrementing = true;
    public $timestamps=false;
}
