<?php

namespace App\Services;

use App\Models\Specification;
use App\Models\UserStory;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIService
{
    private const MODEL = 'gpt-4o-mini';

    public function improveText(string $fieldLabel, string $content): string
    {
        $response = OpenAI::chat()->create([
            'model' => self::MODEL,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a technical writing assistant for a software requirements '
                        .'documentation tool. You improve grammar, structure, professional wording, and '
                        .'clarity of requirement text. Return only the improved text, with no preamble, '
                        .'quotes, or explanation.',
                ],
                [
                    'role' => 'user',
                    'content' => "Improve the following \"{$fieldLabel}\" text for a software specification:\n\n{$content}",
                ],
            ],
        ]);

        return trim($response->choices[0]->message->content);
    }

    public function generateNextSteps(Specification|UserStory $subject): string
    {
        $content = $subject instanceof Specification
            ? $this->summarizeSpecification($subject)
            : $this->summarizeUserStory($subject);

        $response = OpenAI::chat()->create([
            'model' => self::MODEL,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'You are a requirements analyst reviewing a software specification or user '
                        .'story. Based on its content, identify missing information, recommend next actions, '
                        .'flag potential risks, and list questions for stakeholders. Structure your response '
                        .'with those four headings.',
                ],
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
        ]);

        return trim($response->choices[0]->message->content);
    }

    private function summarizeSpecification(Specification $specification): string
    {
        return implode("\n\n", array_filter([
            "Title: {$specification->title}",
            $specification->description ? "Description: {$specification->description}" : null,
            $specification->goals ? "Goals: {$specification->goals}" : null,
            $specification->scope ? "Scope: {$specification->scope}" : null,
            $specification->functional_requirements ? "Functional Requirements: {$specification->functional_requirements}" : null,
            $specification->non_functional_requirements ? "Non-Functional Requirements: {$specification->non_functional_requirements}" : null,
        ]));
    }

    private function summarizeUserStory(UserStory $userStory): string
    {
        return implode("\n\n", array_filter([
            "Title: {$userStory->title}",
            $userStory->description ? "Description: {$userStory->description}" : null,
        ]));
    }
}
