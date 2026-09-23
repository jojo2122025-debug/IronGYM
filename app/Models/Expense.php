<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Expense extends Model
{
    protected $fillable = ['date', 'category', 'amount', 'method', 'recipient', 'note'];

    protected $casts = ['date' => 'datetime', 'amount' => 'decimal:2'];
}
