<?php

namespace App\Domains\CitizenAccess\Data;

class OpportunityPublicationReadiness
{
    public function __construct(
        public readonly bool $ready,
        public readonly array $checks,
        public readonly array $errors,
    ) {}

    public function statusLabel(): string
    {
        return $this->ready ? 'READY FOR PUBLIC WEBSITE' : 'CANNOT PUBLISH';
    }

    public function validationMessages(): array
    {
        $messages = [];

        foreach ($this->errors as $error) {
            $messages[$error['field']][] = $error['message'];
        }

        return $messages;
    }

    public function toArray(): array
    {
        return [
            'ready' => $this->ready,
            'status' => $this->statusLabel(),
            'checks' => $this->checks,
            'errors' => $this->errors,
        ];
    }
}
