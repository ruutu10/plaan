<?php

namespace App\Models;

use App\Enums\PerformanceStaffRole;
use App\Services\PerformanceStaffSync;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * One person's role at one performance, as read off a Planka card — see
 * {@see PerformanceStaffSync}. Never entered by hand and never
 * corrected in place: the whole row is thrown away and rewritten every time
 * the performance's card is imported again, so there is nothing here worth an
 * audit trail of its own.
 *
 * @property int $id
 * @property int $performance_id
 * @property int $user_id
 * @property PerformanceStaffRole $role
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Performance $performance
 * @property-read User $user
 */
#[Fillable(['performance_id', 'user_id', 'role'])]
class PerformanceStaff extends Pivot
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'performance_staff';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The performance this row staffs.
     *
     * @return BelongsTo<Performance, $this>
     */
    public function performance(): BelongsTo
    {
        return $this->belongsTo(Performance::class);
    }

    /**
     * The account this row names.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'role' => PerformanceStaffRole::class,
        ];
    }
}
