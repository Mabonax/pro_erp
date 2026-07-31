<?php

namespace App\Domains\CitizenAccess\Services;

use App\Domains\CitizenAccess\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class CitizenAccessAuditService
{
    public function record(string $eventType, Model $subject, ?User $actor = null, array $properties = []): AuditEvent
    {
        return AuditEvent::query()->create([
            'event_type' => $eventType,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'actor_user_id' => $actor?->id,
            'public_reference' => $subject->public_reference ?? null,
            'correlation_id' => $subject->correlation_id ?? null,
            'properties' => $this->maskProperties($properties),
        ]);
    }

    private function maskProperties(array $properties): array
    {
        unset($properties['id_number'], $properties['identity_number']);

        return $properties;
    }
}
