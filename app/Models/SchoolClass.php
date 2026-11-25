<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SchoolClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'section',
    ];

    /**
     * Label shown in dropdowns / stored on students: "<name> <section>"
     * (e.g. "Class 9 A"), trimmed — same format StudentController::index
     * builds for the class filter.
     */
    public static function findByLabel(?string $label): ?self
    {
        $label = trim((string) $label);

        if ($label === '') {
            return null;
        }

        foreach (static::all() as $class) {
            if (strcasecmp(trim($class->name.' '.$class->section), $label) === 0) {
                return $class;
            }
        }

        // Fallback: the label may be the bare class name (only valid when
        // that name is unambiguous, i.e. has a single section).
        $matches = static::where('name', $label)->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }
}