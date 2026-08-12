<?php

namespace App\Domains\Documents\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class DocumentFileResource extends JsonResource
{
    public function toArray($request): array
    {
        $user = $request->user();

        return [
            'id' => $this->id,
            'folder_id' => $this->folder_id,
            'title' => $this->title,
            'description' => $this->description,
            'original_name' => $this->original_name,
            'mime_type' => $this->mime_type,
            'size_bytes' => $this->size_bytes,
            'version' => $this->version,
            'uploaded_by_name' => $this->uploader?->name,
            'created_at' => $this->created_at?->toDateTimeString(),
            'download_url' => route('organization.document-library.files.download', $this->resource),
            'preview_url' => route('organization.document-library.files.preview', $this->resource),
            'can' => [
                'download' => $user?->can('view', $this->resource) ?? false,
                'manage' => $user?->can('update', $this->resource) ?? false,
            ],
        ];
    }
}
