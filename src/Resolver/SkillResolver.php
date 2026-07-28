<?php

declare(strict_types=1);

namespace App\Resolver;

use Symfony\Component\Console\Command\Command;

class SkillResolver
{
    private string $nativeSkillsDir;

    public function __construct(
        private readonly ?string $customSkillsDir = null,
    ) {
        // Chemin relatif vers src/Resources/skills
        $this->nativeSkillsDir = \dirname(__DIR__).'/Resources/skills';
    }

    /**
     * Résolution et concatènation des skills pertinents selon la classe à tester.
     * La méthode s'appuie sur l'autoloader de Composer, mais conserve une détection de secours sur le code source :
     * si la classe n'a pas éncore été chargée par Composer,
     * on tente de lire les informations directement depuis le code source.
     */
    public function resolveForClass(string $fqcn, string $classCode): string
    {
        $skills = [];

        // 1. Skill : Commandes Symfony
        $isCommand = (class_exists($fqcn) && is_subclass_of($fqcn, Command::class))
            || str_contains($classCode, 'extends Command')
            || str_contains($classCode, '#[AsCommand');

        if ($isCommand) {
            $skills[] = $this->loadSkill('symfony_command.md');
        }

        // 2. Skill : Manipulation du système de fichiers / Finder / Temp
        $hasFilesystem = str_contains($classCode, 'file_put_contents')
            || str_contains($classCode, 'Finder')
            || str_contains($classCode, 'sys_get_temp_dir')
            || str_contains($classCode, 'mkdir');

        if ($hasFilesystem) {
            $skills[] = $this->loadSkill('filesystem_test.md');
        }

        // 3. Chargement éventuel de skills personnalisés situés dans le projet hôte
        $customSkills = $this->loadCustomSkills();

        return implode("\n\n", array_merge($skills, $customSkills));
    }

    private function loadSkill(string $filename): string
    {
        $path = $this->nativeSkillsDir.'/'.$filename;

        if (file_exists($path)) {
            return file_get_contents($path) ?: '';
        }

        return '';
    }

    /**
     * @return array<string>
     */
    private function loadCustomSkills(): array
    {
        if (null === $this->customSkillsDir || !is_dir($this->customSkillsDir)) {
            return [];
        }

        $custom = [];
        foreach (glob($this->customSkillsDir.'/*.md') ?: [] as $file) {
            $custom[] = file_get_contents($file) ?: '';
        }

        return $custom;
    }
}
