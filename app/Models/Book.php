<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Book extends Model
{
    use HasFactory;

    protected $fillable = [
        'acc_no',
        'title',
        'author',
        'category',
        'shelf',
        'total_copies',
        'available_copies',
    ];

    public function issues()
    {
        return $this->hasMany(BookIssue::class);
    }
}
