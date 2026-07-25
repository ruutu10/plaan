<?php

namespace App\Http\Resources;

use App\Models\TechnicalPlan as TechnicalPlanModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The receipt the wizard gets back after saving a plan: where the plan now
 * stands, the link to share it, and the canonical list of files it ended up
 * with (the client replaces its own staged handles with these).
 *
 * @property-read TechnicalPlanModel $resource
 */
class SavedTechnicalPlan extends JsonResource
{
    /** @var string|null */
    public static $wrap = null;

    /**
     * Saving is an upsert of the wizard's single in-progress plan, so the
     * client always gets a plain 200 — not the 201 a first save would
     * otherwise produce.
     */
    public function withResponse(Request $request, JsonResponse $response): void
    {
        $response->setStatusCode(200);
    }

    /**
     * Transform the saved plan into a save confirmation.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $plan = $this->resource;

        return [
            'token' => $plan->token,
            'status' => $plan->status->value,
            'publicUrl' => route('technical-plan.public', $plan),
            'files' => Attachment::collection($plan->attachments())->resolve($request),
        ];
    }
}
