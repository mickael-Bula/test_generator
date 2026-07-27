<?php

declare(strict_types=1);

namespace App\Command;

use App\Exception\TestCorrectionException;
use App\Llm\LlmClientFactory;
use App\Resolver\ClassResolver;
use App\Service\TestGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'app:generate-test',
    description: 'Génère un test unitaire PHPUnit pour une classe donnée via le LLM configuré, avec validation automatique.',
)]
class GenerateTestCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private readonly TestGenerator $testGenerator,
        private readonly LlmClientFactory $llmFactory, // Injecte la factory qui récupère le client et le modèle.
        private readonly string $projectDir, // injecté depuis services.yaml
        private readonly ClassResolver $classResolver,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'class',
            InputArgument::REQUIRED,
            'Le nom de la classe à tester, '
                .'ou son chemin (ex: src/Service/CalculatorService.php) '
                .'ou encore son namespace (ex : \\App\\Service\\Calculator)'
        )->addOption(
            'method',
            'm',
            InputOption::VALUE_REQUIRED,
            'Cibler une méthode spécifique de la classe à tester'
        )->addOption(
            'model',
            null,
            InputOption::VALUE_OPTIONAL,
            'Modèle LLM spécifique à utiliser (ex: qwen2.5-coder:14b ou un modèle OpenRouter)',
        )
        ->addOption(
            'spec',
            's',
            InputOption::VALUE_OPTIONAL,
            'Chemin vers un fichier de spécification (.md) ou consigne métier sous forme de texte'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // On donne dix minutes d'exécution au script global (important pour le CPU en local).
        set_time_limit(6000);

        $this->io = new SymfonyStyle($input, $output);
        $targetInput = $input->getArgument('class');

        try {
            // Résolution automatique de l'entrée
            $resolved = $this->classResolver->resolve($targetInput);

            $fqcn = $resolved['className'];
            $filePath = $resolved['filePath'];

            // Si le fichier n'existe pas, on arrête l'exécution de la commande.
            if (!file_exists($filePath)) {
                $this->io->error(sprintf('Le fichier "%s" n\'existe pas.', $filePath));

                return Command::FAILURE;
            }

            $classCode = file_get_contents($filePath);

            $this->io->note(sprintf('Classe ciblée : %s (%s)', $fqcn, $filePath));
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            $this->io->error($e->getMessage());

            return Command::FAILURE;
        }

        // On extrait le nom court de la classe (ex : "CalculatorService") depuis le FQCN
        $shortClassName = basename(str_replace('\\', '/', $fqcn));

        // Récupère le modèle passé en option, sinon celui déclaré par défaut dans les variables d'environnement
        $model = $input->getOption('model') ?? $this->llmFactory->getDefaultModel();

        // Récupération du contenu de la spécification
        $specOption = $input->getOption('spec');

        // Résolution du contenu de la spécification
        $specContent = $this->resolveSpecContent($specOption, $this->io);

        /** @var string|null $methodName */
        $methodName = $input->getOption('method');

        $this->io->title(sprintf('Analyse et génération de test pour : %s', $shortClassName));

        if ($methodName) {
            $this->io->text(sprintf('Cible spécifique : la méthode <info>%s()</info>', $methodName));
        }

        try {
            [$targetNamespace, $finalDisplayDir, $finalAbsoluteFilePath] = $this->getNamespaceAndPaths($classCode, $shortClassName);

            if (!$input->getOption('method') && file_exists($finalAbsoluteFilePath)) {
                $this->io->warning('Un fichier de test existe déjà pour cette classe : '.basename($finalAbsoluteFilePath));

                $confirm = $this->io->confirm(
                    'Voulez-vous lancer la fusion automatique par le LLM sur ce fichier existant ?',
                    false
                );

                if (!$confirm) {
                    $this->io->note('Génération annulée pour préserver vos tests existants.');

                    return Command::SUCCESS;
                }
            }

            if (!is_dir($finalDisplayDir) && !mkdir($finalDisplayDir, 0777, true) && !is_dir($finalDisplayDir)) {
                throw new \RuntimeException(sprintf('Le dossier "%s" n\'a pas été créé', $finalDisplayDir));
            }

            $existingTestCode = null;
            $testFileExisted = file_exists($finalAbsoluteFilePath);
            if ($testFileExisted) {
                if (Command::FAILURE === $this->checkTestFileIsClean($finalAbsoluteFilePath)) {
                    return Command::FAILURE;
                }

                $this->io->note('Un fichier de test existant a été détecté. Il va être transmis au LLM pour fusion.');
                $existingTestCode = file_get_contents($finalAbsoluteFilePath);
                $existingTestCode = $this->replaceDynamicHeadersInExistingTestCode(
                    $existingTestCode,
                    $targetNamespace,
                    $shortClassName
                );
            }

            $this->io->comment('Envoi du code au LLM...');

            if ($specContent) {
                $this->io->info('Une spécification métier a été injectée dans le contexte du LLM.');
            }

            // Appel du LLM
            try {
                $testCode = $this->testGenerator->generateForClass(
                    $classCode,
                    $shortClassName,
                    $model,
                    $methodName,
                    $existingTestCode,
                    $specContent
                );
            } catch (\RuntimeException|TestCorrectionException $e) {
                // Intercepte les erreurs de Repo-Map ainsi que l'échec de correction PHPUnit
                $this->io->error($e->getMessage());

                return Command::FAILURE;
            }

            $testCode = $this->replaceDynamicHeadersInTestCode($testCode, $targetNamespace, $shortClassName);

            file_put_contents($finalAbsoluteFilePath, $testCode);

            if ($testFileExisted) {
                $this->io->success('Le fichier de test existant a été mis à jour et fusionné par le LLM !');
                $this->io->section('🔍 Sécurité & Revue de code');
                $this->io->info([
                    'Le code existant a été préservé et enrichi.',
                    "Utilisez votre IDE ou la commande 'git diff' pour inspecter les ajouts de l'IA.",
                    "Si le résultat ne vous convient pas, vous pouvez l'annuler à tout moment avec :",
                    '👉 git restore '.str_replace($this->projectDir.'/', '', $finalAbsoluteFilePath),
                ]);
            } else {
                $relativeLogPath = str_replace($this->projectDir.'/', '', $finalAbsoluteFilePath);
                $this->io->success(sprintf('Le fichier de test a été généré avec succès dans : %s', $relativeLogPath));
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->io->error('Une erreur est survenue lors de la génération : '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function getNamespaceAndPaths(string $classCode, string $shortClassName): array
    {
        // 1. On normalise la racine du projet
        $normalizedProjectDir = rtrim(str_replace('\\', '/', $this->projectDir), '/');

        // 2. On extrait le namespace d'origine (ex : "App\Service")
        $originNamespace = $this->extractNamespaceFromCode($classCode);

        // 3. On calcule le namespace cible avec des antislashes (ex : "App\Tests\Service")
        $targetNamespace = str_replace('App\\', 'App\\Tests\\', $originNamespace);

        // 4. On extrait le sous-dossier (on retire "App\Tests\" puis on convertit les "\" restants en "/")
        $subFolder = str_replace(['App\\Tests\\', '\\'], ['', '/'], $targetNamespace); // Donne: "Service"

        // 5. On assemble le tout proprement avec des slashes
        $finalDisplayDir = sprintf('%s/tests/%s', $normalizedProjectDir, $subFolder);
        $finalAbsoluteFilePath = sprintf('%s/%sTest.php', $finalDisplayDir, $shortClassName);

        return [$targetNamespace, $finalDisplayDir, $finalAbsoluteFilePath];
    }

    private function extractNamespaceFromCode(string $classCode): string
    {
        if (preg_match('/namespace\s+([^;]+);/', $classCode, $matches)) {
            return trim($matches[1]);
        }

        return 'App\Tests';
    }

    private function replaceDynamicHeadersInExistingTestCode(string $existingTestCode, string $targetNamespace, string $className): string
    {
        return str_replace(
            [
                sprintf('namespace %s;', $targetNamespace),
                sprintf('class %sTest', $className),
            ],
            [
                'namespace App\Tests\Dynamic;',
                sprintf('class %sDynamicTest', $className),
            ],
            $existingTestCode
        );
    }

    private function replaceDynamicHeadersInTestCode(string $testCode, string $targetNamespace, string $className): string
    {
        return str_replace(
            [
                'namespace App\Tests\Dynamic;',
                sprintf('class %sDynamicTest', $className),
            ],
            [
                sprintf('namespace %s;', $targetNamespace),
                sprintf('class %sTest', $className),
            ],
            $testCode
        );
    }

    private function checkTestFileIsClean(string $path): int
    {
        $checkClean = new Process(['git', 'status', '--porcelain', $path], $this->projectDir);
        $checkClean->run();
        $isDirty = !empty(trim($checkClean->getOutput()));

        if ($isDirty) {
            $this->io->warning('Le fichier de test existant a des modifications non versionnées dans Git.');
            if (!$this->io->confirm('Voulez-vous continuer et écraser ces modifications temporairement ?', false)) {
                return Command::FAILURE;
            }
        }

        return Command::SUCCESS;
    }

    /**
     * Tente de lire le contenu de la spec depuis un fichier s'il existe,
     * sinon retourne la chaîne brute fournie.
     */
    private function resolveSpecContent(?string $specOption, SymfonyStyle $io): ?string
    {
        if (null === $specOption || '' === trim($specOption)) {
            return null;
        }

        // Normalisation des séparateurs de dossier (Windows vs Linux)
        $normalizedOption = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $specOption);

        // 1. Si le chemin peut être résolu directement (chemin absolu ou relatif au dossier d'exécution).
        if (file_exists($normalizedOption) && is_file($normalizedOption)) {
            return file_get_contents($normalizedOption);
        }

        // 2. Si c'est un chemin relatif à la racine du projet (ex : tests/Specs/my_spec.md).
        $relativePath = $this->projectDir.DIRECTORY_SEPARATOR.ltrim($normalizedOption, '/\\');
        if (file_exists($relativePath) && is_file($relativePath)) {
            return file_get_contents($relativePath);
        }

        // 3. Si le fichier n'est pas trouvé, mais se termine par .md, on avertit l'utilisateur
        if (str_ends_with(mb_strtolower($specOption), '.md')) {
            $io->warning(sprintf('Fichier de spécification non trouvé à l\'emplacement : %s. La valeur sera traitée comme du texte brut.', $relativePath));
        }

        // Si ce n'est pas un fichier existant, on traite la chaîne directement comme une consigne texte
        return $specOption;
    }
}
