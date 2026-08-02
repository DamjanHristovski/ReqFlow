<?php

namespace App\Services;

use App\Models\Specification;
use App\Models\SpecificationVersion;
use App\Models\User;
use App\Models\UserStory;
use Illuminate\Support\Arr;

class SpecificationVersionService
{
    public function snapshot(Specification|UserStory $subject): array
    {
        return Arr::only($subject->toArray(), $subject::VERSIONED_FIELDS);
    }

    public function recordInitialVersion(Specification|UserStory $subject, User $changedBy): SpecificationVersion
    {
        return $this->createVersion($subject, $changedBy, 1);
    }

    public function recordVersionIfChanged(Specification|UserStory $subject, array $originalContent, User $changedBy): ?SpecificationVersion
    {
        if ($this->snapshot($subject) === $originalContent) {
            return null;
        }

        return $this->createVersion($subject, $changedBy, $this->nextVersionNumber($subject));
    }

    /**
     * Find an existing version whose content exactly matches the given content, if any.
     */
    public function findMatchingVersion(Specification|UserStory $subject, array $content): ?SpecificationVersion
    {
        return $subject->versions()->get()->first(
            fn (SpecificationVersion $version) => $version->content === $content
        );
    }

    /**
     * Rewind the subject back to an existing version's content. Nothing is
     * created or deleted — only the current_version pointer moves.
     */
    public function restore(SpecificationVersion $version): Specification|UserStory
    {
        $subject = $version->subject();
        $subject->fill($version->content);
        $subject->forceFill(['current_version' => $version->version_number]);
        $subject->save();

        return $subject;
    }

    private function nextVersionNumber(Specification|UserStory $subject): int
    {
        return $subject->versions()->max('version_number') + 1;
    }

    private function createVersion(Specification|UserStory $subject, User $changedBy, int $versionNumber): SpecificationVersion
    {
        $version = $subject->versions()->create([
            'version_number' => $versionNumber,
            'content' => $this->snapshot($subject),
            'changed_by' => $changedBy->id,
        ]);

        $subject->forceFill(['current_version' => $versionNumber])->save();

        return $version;
    }
}
