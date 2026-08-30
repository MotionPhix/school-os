<?php

declare(strict_types=1);

namespace App\Domains\People\Services;

use App\Domains\People\Events\PersonAvatarUpdated;
use App\Domains\People\Events\PersonDocumentAttached;
use App\Domains\People\Events\PersonDocumentRemoved;
use App\Enums\PersonSubject;
use App\Models\PersonDocument;
use App\Support\TenantContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Media service for People aggregates.
 *
 * Handles avatar upload/clear and document attach/remove for any
 * PersonSubject (students | guardians | staff). Files are stored under
 * `people/<subject>/<subject_id>/…` on the disk configured by
 * `config('people.media.disk')`. Removing a document deletes the
 * backing file — the DB is the source of truth for what exists.
 */
final class PersonMediaService
{
    public function __construct(private readonly TenantContext $tenants) {}

    public function setAvatar(PersonSubject $subject, Model $person, ?UploadedFile $file): Model
    {
        return DB::transaction(function () use ($subject, $person, $file): Model {
            $disk = $this->disk();

            if ($person->avatar_path) {
                Storage::disk($disk)->delete($person->avatar_path);
            }

            $path = null;
            if ($file !== null) {
                $path = $file->store($this->avatarDir($subject, $person->id), $disk);
            }

            $person->avatar_path = $path;
            $person->save();
            $person->refresh();

            PersonAvatarUpdated::dispatch(
                $person->tenant_id,
                $subject,
                $person->id,
                $path,
            );

            return $person;
        });
    }

    public function attachDocument(
        PersonSubject $subject,
        Model $person,
        UploadedFile $file,
        string $name,
        ?Authenticatable $uploader,
    ): PersonDocument {
        return DB::transaction(function () use ($subject, $person, $file, $name, $uploader): PersonDocument {
            $disk = $this->disk();
            $storagePath = $file->store($this->documentDir($subject, $person->id), $disk);

            $document = new PersonDocument([
                'tenant_id' => $this->tenants->id(),
                'subject_type' => $subject->value,
                'subject_id' => $person->id,
                'name' => $name,
                'mime' => $file->getClientMimeType(),
                'size' => (int) $file->getSize(),
                'storage_path' => $storagePath,
                'uploaded_by' => $uploader?->getAuthIdentifier(),
                'uploaded_at' => now(),
            ]);
            $document->save();

            PersonDocumentAttached::dispatch($document);

            return $document;
        });
    }

    public function removeDocument(PersonDocument $document): void
    {
        DB::transaction(function () use ($document): void {
            Storage::disk($this->disk())->delete($document->storage_path);

            $tenantId = $document->tenant_id;
            $documentId = $document->id;
            $subjectType = $document->subject_type;
            $subjectId = $document->subject_id;

            $document->delete();

            PersonDocumentRemoved::dispatch($tenantId, $documentId, $subjectType, $subjectId);
        });
    }

    private function disk(): string
    {
        return (string) config('people.media.disk', 'local');
    }

    private function avatarDir(PersonSubject $subject, string $personId): string
    {
        return "people/{$subject->value}/{$personId}/avatar";
    }

    private function documentDir(PersonSubject $subject, string $personId): string
    {
        return "people/{$subject->value}/{$personId}/documents";
    }
}
