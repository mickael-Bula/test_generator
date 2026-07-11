<?php

declare(strict_types=1);

namespace App\Service;

readonly class PhpTestFileBuilder
{
    public function __construct(
        private LlmJsonSanitizer $jsonSanitizer,
    ) {}

    /**
     * Reconstruit un fichier de test PHPUnit complet à partir de morceaux de JSON.
     *
     * @param array{namespace: string, class: string, uses?: string[], methods: array<array{code?: string}>} $content
     */
    public function buildFromFragments(array $content): string
    {
        $php = "<?php\n\ndeclare(strict_types=1);\n\n";

        $namespace = $this->jsonSanitizer->sanitizePhpCode($content['namespace']);
        $php .= "namespace " . $namespace . ";\n\n";

        if (isset($content['uses']) && is_array($content['uses'])) {
            foreach ($content['uses'] as $use) {
                $php .= "use " . $this->jsonSanitizer->sanitizePhpCode($use) . ";\n";
            }
            $php .= "\n";
        }

        $className = $this->jsonSanitizer->sanitizePhpCode($content['class']);
        $php .= "class " . $className . " extends TestCase\n{\n";

        foreach ($content['methods'] as $method) {
            if (isset($method['code'])) {
                // On indente proprement le code de la méthode
                $methodCode = implode("\n    ", explode("\n", trim($method['code'])));
                $php .= "    " . $this->jsonSanitizer->sanitizePhpCode($methodCode) . "\n\n";
            }
        }

        $php .= "}\n";

        return $php;
    }
}
