<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Candidate extends Model
{
    protected $fillable = ['name', 'group', 'photo', 'program', 'active'];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'votes' => 'integer', 'adjustment' => 'integer'];
    }
}
