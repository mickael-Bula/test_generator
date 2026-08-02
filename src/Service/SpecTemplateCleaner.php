<?php

declare(strict_types=1);

namespace App\Service;

class SpecTemplateCleaner
{
    /**
     * Nettoie les commentaires HTML d'instruction et les lignes vides superflues.
     */
    public function cleanForLlm(string $markdownContent): string
    {
        // 1. Supprime tous les commentaires HTML <!-- ... --> (y compris multilignes)
        $cleaned = preg_replace('/<!--.*?-->/s', '', $markdownContent);

        // 2. Nettoie les lignes vides consécutives laissées par la suppression
        $cleaned = preg_replace("/\n\s*\n\s*\n/", "\n\n", (string) $cleaned);

        return trim((string) $cleaned);
    }
}
