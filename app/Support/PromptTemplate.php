<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Loads a prompt template from resources/prompts/{name}.txt and substitutes
 * {{placeholder}} tokens. Keeping prompts in flat .txt files (rather than
 * inline PHP strings) lets them be reviewed and tuned without touching code.
 */
class PromptTemplate
{
    /**
     * @param  array<string, string>  $replacements
     */
    public static function load(string $name, array $replacements = []): string
    {
        $path = resource_path("prompts/{$name}.txt");

        if (! is_file($path)) {
            throw new InvalidArgumentException("Prompt template [{$name}] not found at {$path}.");
        }

        $template = (string) file_get_contents($path);

        $tokens = [];
        foreach ($replacements as $key => $value) {
            $tokens['{{'.$key.'}}'] = $value;
        }

        return trim(strtr($template, $tokens));
    }
}
