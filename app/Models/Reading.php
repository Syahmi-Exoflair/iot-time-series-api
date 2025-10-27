<?php

namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class Reading extends Model
{
    protected $connection = 'mongodb';

    protected $collection = 'readings';
}
