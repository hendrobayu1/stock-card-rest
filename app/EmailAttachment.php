<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class EmailAttachment extends Model
{
    protected $table = "rirj_email_attachment";
    protected $primaryKey = 'id_attachment';
    public $incrementing = true;
    public $timestamps=false;
}
