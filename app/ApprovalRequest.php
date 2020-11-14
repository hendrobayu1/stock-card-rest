<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ApprovalRequest extends Model
{
    protected $table = "PAPP_APPROVAL";
    protected $primaryKey = "ID_APPROVAL";
    public $incrementing = true;
    public $timestamps = true;
    const CREATED_AT = "CRTDT";
    const UPDATED_AT = "MODIDT";
    protected $guards = [];
}
