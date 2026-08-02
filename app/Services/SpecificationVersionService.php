<?php

namespace App\Services;

use App\Models\Specification;
use App\Models\SpecificationVersion;
use App\Models\User;
use Illuminate\Support\Arr;

class SpecificationVersionService
{
    public function snapshot(Specification $specification): array
    {
        return Arr::only($specification->toArray(), Specification::VERSIONED_FIELDS);
    }

    public function recordInitialVersion(Specification $specification, User $changedBy): SpecificationVersion
    {
        return $this->createVersion($specification, $changedBy, 1);
    }

    public function recordVersionIfChanged(Specification $specification, array $originalContent, User $changedBy): ?SpecificationVersion
    {
        if ($this->snapshot($specification) === $originalContent) {
            return null;
        }

        return $this->createVersion($specification, $changedBy, $this->nextVersionNumber($specification));
    }

    /**
     * Find an existing version whose content exactly matches the given content, if any.
     */
    public function findMatchingVersion(Specification $specification, array $content): ?SpecificationVersion
    {
        return $specification->versions()->get()->first(
            fn (SpecificationVersion $version) => $version->content === $content
        );
    }

    /**
     * Rewind the specification back to an existing version's content. Nothing is
     * created or deleted — only the current_version pointer moves.
     */
    public function restore(SpecificationVersion $version): Specification
    {
        $specification = $version->specification;
        $specification->fill($version->content);
        $specification->forceFill(['current_version' => $version->version_number]);
        $specification->save();

        return $specification;
    }

    private function nextVersionNumber(Specification $specification): int
    {
        return $specification->versions()->max('version_number') + 1;
    }

    private function createVersion(Specification $specification, User $changedBy, int $versionNumber): SpecificationVersion
    {
        $version = $specification->versions()->create([
            'version_number' => $versionNumber,
            'content' => $this->snapshot($specification),
            'changed_by' => $changedBy->id,
        ]);

        $specification->forceFill(['current_version' => $versionNumber])->save();

        return $version;
    }
}
