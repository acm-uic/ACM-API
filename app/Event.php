<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    //
    public $startTime = null;
    public $endTime = null;
    protected $table = 'events';
}
