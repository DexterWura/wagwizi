<?php

namespace App\Services\Notifications;

use App\Models\EmailTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class EmailTemplateService
{
    public function listAllOrderedByKey(): Collection
    {
        return EmailTemplate::query()->orderBy('key')->get();
    }

    public function findByKey(string $key): ?EmailTemplate
    {
        return EmailTemplate::query()->where('key', $key)->first();
    }

    public function findOrFail(int $id): EmailTemplate
    {
        return EmailTemplate::query()->findOrFail($id);
    }

    /**
     * @param  array{name?: string, subject: string, body_html: string, body_text?: string|null, description?: string|null}  $data
     */
    public function update(int $id, array $data): EmailTemplate
    {
        $t = $this->findOrFail($id);

        $t->fill([
            'name'        => $data['name'] ?? $t->name,
            'subject'     => $data['subject'],
            'body_html'   => $data['body_html'],
            'body_text'   => $data['body_text'] ?? null,
            'description' => $data['description'] ?? $t->description,
        ]);
        $t->save();

        return $t->fresh();
    }

    public function paginateForAdmin(int $perPage = 30): LengthAwarePaginator
    {
        return EmailTemplate::query()->orderBy('key')->paginate($perPage);
    }
}
