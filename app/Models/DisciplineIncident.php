<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisciplineIncident extends Model
{
    public const CATEGORIES = [
        'late_absent' => 'Late / Skipping class',
        'homework' => 'Homework / Classwork',
        'disrespect' => 'Disrespect / Misbehaviour',
        'disruption' => 'Disrupting class',
        'fighting' => 'Fighting / Violence',
        'bullying' => 'Bullying / Harassment',
        'cheating' => 'Cheating / Dishonesty',
        'uniform' => 'Uniform / Appearance',
        'mobile' => 'Mobile phone / Devices',
        'property' => 'Property damage / Theft',
        'other' => 'Other',
    ];

    public const SEVERITIES = [
        'low' => 'Low',
        'medium' => 'Medium',
        'high' => 'High',
        'critical' => 'Critical',
    ];

    public const ACTIONS = [
        'verbal_warning' => 'Verbal warning',
        'written_warning' => 'Written warning',
        'extra_work' => 'Extra work / Practice',
        'detention' => 'Detention',
        'parent_meeting' => 'Parent meeting',
        'counselling' => 'Counselling',
        'suspension' => 'Suspension',
        'other' => 'Other',
    ];

    public const NOTIFY_METHODS = [
        'call' => 'Phone call',
        'sms' => 'SMS',
        'meeting' => 'In-person meeting',
        'letter' => 'Written notice',
    ];

    public const STATUSES = ['open', 'in_review', 'closed'];

    protected $fillable = [
        'student_id', 'reported_by', 'incident_date', 'category', 'severity', 'title', 'description',
        'location', 'witnesses', 'action_type', 'action_taken', 'parent_notified', 'parent_notified_on',
        'notified_via', 'follow_up_date', 'status', 'resolution', 'closed_at',
    ];

    protected $casts = [
        'incident_date' => 'date',
        'parent_notified' => 'boolean',
        'parent_notified_on' => 'date',
        'follow_up_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(Teacher::class, 'reported_by');
    }
}
