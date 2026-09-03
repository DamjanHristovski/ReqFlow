<?php

namespace App\Services;

use App\Models\AcceptanceCriterion;
use App\Models\Project;
use App\Models\Specification;
use App\Models\User;
use App\Models\UserStory;

/**
 * Turns AI-generated payloads into real, editable records (specifications,
 * user stories, acceptance criteria). Everything created here is an ordinary
 * draft the user can edit or delete afterward — nothing is locked.
 */
class GeneratedContentService
{
    public function __construct(private SpecificationVersionService $versions) {}

    /**
     * @param  array<string, mixed>  $data  the "specification" object from AiService::extractSpecificationFromPdf
     */
    public function createSpecification(Project $project, array $data, User $creator): Specification
    {
        $specification = $project->specifications()->create([
            'title' => $this->text($data['title'] ?? null) ?? 'Imported specification',
            'description' => $this->text($data['description'] ?? null),
            'goals' => $this->text($data['goals'] ?? null),
            'scope' => $this->text($data['scope'] ?? null),
            'functional_requirements' => $this->bulletize($data['functional_requirements'] ?? null),
            'non_functional_requirements' => $this->bulletize($data['non_functional_requirements'] ?? null),
            'current_version' => 1,
            'created_by' => $creator->id,
        ]);

        $this->versions->recordInitialVersion($specification, $creator);

        return $specification;
    }

    /**
     * @param  array<int, array<string, mixed>>  $stories  the "user_stories" list from AiService::generateUserStories
     * @return int how many stories were created
     */
    public function createUserStories(Project $project, array $stories, User $creator): int
    {
        $created = 0;

        foreach ($stories as $story) {
            $title = $this->text($story['title'] ?? null);

            if ($title === null) {
                continue;
            }

            $userStory = $project->userStories()->create([
                'title' => $title,
                'description' => $this->text($story['description'] ?? null),
                'current_version' => 1,
                'created_by' => $creator->id,
            ]);

            $this->versions->recordInitialVersion($userStory, $creator);

            $this->createAcceptanceCriteria($userStory, $story['acceptance_criteria'] ?? [], $creator);

            $created++;
        }

        return $created;
    }

    /**
     * @param  array<int, string>  $criteria
     * @return int how many criteria were created
     */
    public function createAcceptanceCriteria(UserStory $userStory, array $criteria, User $creator): int
    {
        $created = 0;

        foreach ($criteria as $criterion) {
            $description = $this->text($criterion);

            if ($description === null) {
                continue;
            }

            $userStory->acceptanceCriteria()->create([
                'description' => $description,
                'status' => AcceptanceCriterion::STATUS_NOT_MET,
                'created_by' => $creator->id,
            ]);

            $created++;
        }

        return $created;
    }

    private function text(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Join a list of requirement strings into a single bulleted text block,
     * so structured AI output lands in the plain-text requirement columns.
     */
    private function bulletize(mixed $items): ?string
    {
        if (! is_array($items)) {
            return $this->text($items);
        }

        $lines = [];

        foreach ($items as $item) {
            $line = $this->text($item);

            if ($line !== null) {
                $lines[] = '- '.$line;
            }
        }

        return $lines === [] ? null : implode("\n", $lines);
    }
}
