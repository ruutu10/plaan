<?php

namespace App\Models;

use App\Enums\PerformanceStaffRole;
use App\Http\Controllers\PerformanceStaffController;
use App\Services\PerformanceStaffSync;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;

/**
 * One person's role at one performance. A row comes from one of two places,
 * and `manually_added` says which:
 *
 * - read off the Planka card — see {@see PerformanceStaffSync}, which throws
 *   these away and rewrites them every time the card is imported again;
 * - put there by hand on the performance's page — see
 *   {@see PerformanceStaffController}. The import never touches these; only
 *   whoever may edit the performance removes them.
 *
 * Either way the row means the same thing to everything that reads it: the
 * same notifications, the same access, the same place on the bill.
 *
 * @property int $id
 * @property int $performance_id
 * @property int $user_id
 * @property PerformanceStaffRole $role
 * @property bool $manually_added
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Performance $performance
 * @property-read User $user
 */
#[Fillable(['performance_id', 'user_id', 'role', 'manually_added'])]
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
     * Unless said otherwise, a row is the import's.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'manually_added' => false,
    ];

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
            'manually_added' => 'boolean',
        ];
    }
}
