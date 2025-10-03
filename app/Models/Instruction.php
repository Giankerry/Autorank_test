<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Instruction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'application_id',
        'criterion',
        'title',
        'category',
        'type',
        'role',
        'publication_date',
        'service_type',
        'student_or_competition',
        'completion_date',
        'level',
        'score',
        'google_drive_file_id',
        'proof_filename',
    ];

    /**
     * Get the user that owns the instruction record.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the application that this instruction submission belongs to.
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
