<?php

declare(strict_types=1);

namespace App\Service;

class LlmJsonSanitizer
{
    /**
     * Répare le JSON brut contenant des antislashs mal échappés.
     * On cherche les groupes d'antislashs suivis d'une lettre qui ne sont pas des échappements JSON valides
     * (les valides étant \b, \f, \n, \r, \t, \u, \, \\, \/)
     * On force ces groupes à devenir des antislashs correctement échappés pour le JSON (\\\\).
     */
    public function sanitizeRawJson(string $rawJson): string
    {
        return preg_replace_callback(
            '#\\\\+([^"\\\\/bfnrtu])#', static function ($matches) {
                return '\\\\'.$matches[1];
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

    /**
     * Supprime les blocs de code markdown si présents.
     * Remplace les vrais caractères de contrôle (sauts de ligne bruts, tabulations)
     * par leurs versions échappées valides en JSON, sauf si le format est déjà correct.
     */
    public function sanitizeJson(string $jsonString): string
    {
        $jsonString = trim($jsonString);

        // 1. Réparer les antislashs PHP mal échappés (ex: App\Service)
        // Le pattern cible le contenu entre guillemets sans créer d'ambiguïté pour l'EDI
        $jsonString = preg_replace_callback('/"([^"\\\\]*(?:\\\\[\\s\\S][^"\\\\]*)*)"/', static function ($matches) {
            $content = $matches[1];

            // Protège les échappements JSON légitimes, double les autres antislashs
            return '"'.preg_replace('/\\\(?!["\\\\\/bfnrtu])/i', '\\\\\\\\', $content).'"';
        }, $jsonString);

        // 2. Remplacer les vrais retours à la ligne physiques résiduels par "\n"
        return preg_replace_callback('/"([^"\\\\]*(?:\\\\[\\s\\S][^"\\\\]*)*)"/', static function ($matches) {
            return '"'.str_replace(["\r\n", "\n", "\r"], '\\n', $matches[1]).'"';
        }, $jsonString);
    }
}
