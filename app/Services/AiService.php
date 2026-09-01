<?php

namespace App\Services;

use App\Models\Specification;
use App\Models\User;
use App\Models\UserStory;
use App\Support\PromptTemplate;
use Prism\Prism\Enums\Provider;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
use Prism\Prism\Structured\PendingRequest as StructuredRequest;
use Prism\Prism\Text\PendingRequest as TextRequest;
use Prism\Prism\ValueObjects\Media\Document;
use RuntimeException;

/**
 * Provider-agnostic AI layer built on Prism. Each call builds a fresh request
 * carrying the acting user's own provider + API key (usingProviderConfig), so
 * the same code serves OpenAI or Gemini and nothing global is held between
 * calls — the request is discarded once it returns.
 */
class AiService
{
    public function improveText(User $user, string $fieldLabel, string $content): string
    {
        $response = $this->text($user)
            ->withSystemPrompt(PromptTemplate::load('improve_text'))
            ->withPrompt("Improve the following \"{$fieldLabel}\" text for a software specification:\n\n{$content}")
            ->asText();

        return trim($response->text);
    }

    public function generateNextSteps(User $user, Specification|UserStory $subject): string
    {
        $content = $subject instanceof Specification
            ? $this->summarizeSpecification($subject)
            : $this->summarizeUserStory($subject);

        $response = $this->text($user)
            ->withSystemPrompt(PromptTemplate::load('generate_next_steps'))
            ->withPrompt($content)
            ->asText();

        return trim($response->text);
    }

    /**
     * Triage + extraction over an attached PDF. Returns the structured payload:
     * ['has_specification' => bool, 'has_user_stories' => bool, 'specification' => [...]].
     *
     * @return array<string, mixed>
     */
    public function extractSpecificationFromPdf(User $user, string $storagePath, string $disk = 'local'): array
    {
        $schema = new ObjectSchema(
            name: 'specification_extraction',
            description: 'A software specification extracted from a document, plus flags describing the document.',
            properties: [
                new BooleanSchema('has_specification', 'True if the document describes a usable specification.'),
                new BooleanSchema('has_user_stories', 'True if the document contains or clearly implies user stories or acceptance criteria.'),
                new ObjectSchema(
                    name: 'specification',
                    description: 'The extracted specification. Any field may be an empty string when the document has nothing for it.',
                    properties: [
                        new StringSchema('title', 'A short identifying title for the specification.'),
                        new StringSchema('description', 'A short summary of what this specification covers.'),
                        new StringSchema('goals', 'The outcomes this specification is trying to achieve.'),
                        new StringSchema('scope', 'What is in scope and what is explicitly out of scope.'),
                        new ArraySchema('functional_requirements', 'Functional requirements, one testable statement per item.', new StringSchema('requirement', 'A single functional requirement.')),
                        new ArraySchema('non_functional_requirements', 'Non-functional requirements, one testable statement per item.', new StringSchema('requirement', 'A single non-functional requirement.')),
                    ],
                    requiredFields: ['title', 'description', 'goals', 'scope', 'functional_requirements', 'non_functional_requirements'],
                ),
            ],
            requiredFields: ['has_specification', 'has_user_stories', 'specification'],
        );

        $response = $this->structured($user)
            ->withSchema($schema)
            ->withPrompt(PromptTemplate::load('extract_specification'), [Document::fromStoragePath($storagePath, $disk)])
            ->asStructured();

        return $response->structured ?? [];
    }

    /**
     * Generate user stories (each with acceptance criteria) from either free
     * text ($context) or an attached PDF ($pdfStoragePath). Any existing stories
     * ($existing) are shown to the model so it doesn't repeat them.
     *
     * @param  array<int, string>  $existing  summaries of stories already in the project
     * @return array<int, array{title: string, description: string, acceptance_criteria: array<int, string>}>
     */
    public function generateUserStories(User $user, ?string $context = null, ?string $pdfStoragePath = null, string $disk = 'local', array $existing = []): array
    {
        $schema = new ObjectSchema(
            name: 'generated_user_stories',
            description: 'A set of user stories, each with acceptance criteria.',
            properties: [
                new ArraySchema('user_stories', 'The generated user stories.', new ObjectSchema(
                    name: 'user_story',
                    description: 'A single user story.',
                    properties: [
                        new StringSchema('title', 'A short, specific title.'),
                        new StringSchema('description', 'The story in the form: As a <role>, I want <goal> so that <benefit>.'),
                        new ArraySchema('acceptance_criteria', '3 to 5 concrete, testable acceptance criteria.', new StringSchema('criterion', 'A single acceptance criterion.')),
                    ],
                    requiredFields: ['title', 'description', 'acceptance_criteria'],
                )),
            ],
            requiredFields: ['user_stories'],
        );

        $prompt = PromptTemplate::load('generate_user_stories', [
            'context' => $context ?? '(Use the attached document as the source material.)',
            'existing' => $this->formatExisting($existing),
        ]);

        $attachments = $pdfStoragePath ? [Document::fromStoragePath($pdfStoragePath, $disk)] : [];

        $response = $this->structured($user)
            ->withSchema($schema)
            ->withPrompt($prompt, $attachments)
            ->asStructured();

        return $response->structured['user_stories'] ?? [];
    }

    /**
     * Generate 3–5 acceptance criteria for a single user story.
     *
     * @return array<int, string>
     */
    public function generateAcceptanceCriteria(User $user, UserStory $userStory): array
    {
        $schema = new ObjectSchema(
            name: 'generated_acceptance_criteria',
            description: 'A set of acceptance criteria for one user story.',
            properties: [
                new ArraySchema('acceptance_criteria', '3 to 5 concrete, testable acceptance criteria.', new StringSchema('criterion', 'A single acceptance criterion.')),
            ],
            requiredFields: ['acceptance_criteria'],
        );

        $response = $this->structured($user)
            ->withSchema($schema)
            ->withPrompt(PromptTemplate::load('generate_acceptance_criteria', [
                'story' => $this->summarizeUserStory($userStory),
                'existing' => $this->formatExisting($userStory->acceptanceCriteria()->pluck('description')->all()),
            ]))
            ->asStructured();

        return $response->structured['acceptance_criteria'] ?? [];
    }

    /**
     * Format a list of existing-item summaries for the "do not repeat" prompt block.
     *
     * @param  array<int, string>  $existing
     */
    private function formatExisting(array $existing): string
    {
        $items = array_values(array_filter(array_map('trim', $existing)));

        if ($items === []) {
            return '(none yet)';
        }

        return implode("\n", array_map(fn (string $item): string => '- '.$item, $items));
    }

    private function text(User $user): TextRequest
    {
        [$provider, $model, $key] = $this->credentials($user);

        return Prism::text()->using($provider, $model, ['api_key' => $key]);
    }

    private function structured(User $user): StructuredRequest
    {
        [$provider, $model, $key] = $this->credentials($user);

        return Prism::structured()->using($provider, $model, ['api_key' => $key]);
    }

    /**
     * @return array{0: Provider, 1: string, 2: string}
     */
    private function credentials(User $user): array
    {
        if (! $user->hasAiConfigured()) {
            throw new RuntimeException('No AI provider or API key is configured for this user.');
        }

        $provider = Provider::from($user->ai_provider);
        $model = (string) config("ai.providers.{$user->ai_provider}.model");

        return [$provider, $model, $user->ai_api_key];
    }

    public function summarizeSpecification(Specification $specification): string
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

    public function summarizeUserStory(UserStory $userStory): string
    {
        return implode("\n\n", array_filter([
            "Title: {$userStory->title}",
            $userStory->description ? "Description: {$userStory->description}" : null,
        ]));
    }
}
