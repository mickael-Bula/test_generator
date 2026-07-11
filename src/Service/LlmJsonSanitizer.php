<?php

declare(strict_types=1);

namespace App\Service;

class LlmJsonSanitizer
{
    /**
     * Répare le JSON brut contenant des antislashs mal échappés.
     * On cherche les groupes d'antislashs suivis d'une lettre qui ne sont pas des échappements JSON valides
     * (les valides étant \b, \f, \n, \r, \t, \u, \, \\, \/)
     * On force ces groupes à devenir des antislashs correctement échappés pour le JSON (\\\\)
     */
    public function sanitizeRawJson(string $rawJson): string
    {
        return preg_replace_callback(
            '#\\\\+([^"\\\\/bfnrtu])#', static function ($matches) {
            return '\\\\' . $matches[1];
        },
            $rawJson
        );
    }

    /**
     * Nettoie le code PHP final extrait pour enlever les résidus
     * de doubles antislashs (\\\\) générés par le LLM.
     */
    public function sanitizePhpCode(string $phpCode): string
    {
        return str_replace('\\\\', '\\', $phpCode);
    }
}
