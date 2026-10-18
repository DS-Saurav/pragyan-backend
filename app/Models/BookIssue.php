<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BookIssue extends Model
{
    use HasFactory;

    protected $fillable = [
        'book_id',
        'member_type',
        'member_name',
        'issue_date',
        'due_date',
        'returned_date',
        'fine',
        'status',
    ];

    public function book()
    {
        return $this->belongsTo(Book::class);
    }
}
