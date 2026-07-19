<?php

declare(strict_types=1);

namespace App\Command;

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
    name: 'app:generate-ollama-test',
    description: 'Génère un test unitaire PHPUnit pour une classe donnée via le LLM configuré, avec validation automatique.',
)]
class GenerateTestWithOllamaCommand extends Command
{
    private SymfonyStyle $io;

    public function __construct(
        private readonly TestGenerator $testGenerator,
        private readonly string $model,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'filePath',
            InputArgument::REQUIRED,
            'Le chemin vers le fichier PHP à tester (ex: src/Service/CalculatorService.php)'
        )->addOption(
            'method',
            'm',
            InputOption::VALUE_REQUIRED,
            'Cibler une méthode spécifique de la classe à tester'
        )->addOption(
            'model',
            null,
            InputOption::VALUE_OPTIONAL,
            'Modèle LLM spécifique à utiliser (ex: qwen2.5-coder:1.5b ou un modèle OpenRouter)',
            $this->model // Modèle par défaut déclaré dans les variables d'environnement
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // On donne cinq minutes d'exécution au script global (important pour le CPU en local).
        set_time_limit(300);

        $this->io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('filePath');
        $model = $input->getOption('model') ?? $this->model;

        $fullPath = $this->projectDir.'/'.$filePath;
        if (!file_exists($fullPath)) {
            $this->io->error(sprintf('Le fichier "\%s" n\'existe pas.', $fullPath));

            return Command::FAILURE;
        }

        /** @var string|null $methodName */
        $methodName = $input->getOption('method');

        $this->io->title(sprintf('Analyse et génération de test pour : %s', $filePath));

        if ($methodName) {
            $this->io->text(sprintf('🎯 Cible spécifique : la méthode <info>\%s()</info>', $methodName));
        }

        $classCode = file_get_contents($fullPath);
        $className = pathinfo($filePath, PATHINFO_FILENAME);

        try {
            // 1. On normalise la racine du projet
            $normalizedProjectDir = rtrim(str_replace('\\', '/', $this->projectDir), '/');

            // 2. On extrait le namespace d'origine (ex: "App\Service")
            $originNamespace = $this->extractNamespaceFromCode($classCode);

            // 3. On calcule le namespace cible avec des antislashes (ex : "App\Tests\Service")
            $targetNamespace = str_replace('App\\', 'App\\Tests\\', $originNamespace);

            // 4. On extrait le sous-dossier (on retire "App\Tests\" puis on convertit les "\" restants en "/")
            $subFolder = str_replace('App\\Tests\\', '', $targetNamespace); // Donne: "Service"
            $subFolder = str_replace('\\', '/', $subFolder); // Sécurité si sous-dossiers profonds

            // 5. On assemble le tout proprement avec des slashes
            $finalDisplayDir = sprintf('%s/tests/%s', $normalizedProjectDir, $subFolder);
            $finalAbsoluteFilePath = sprintf('%s/%sTest.php', $finalDisplayDir, $className);

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
                $existingTestCode = $this->replaceDynamicHeadersInExistingTestCode($existingTestCode, $targetNamespace,
                    $className);
            }

            $this->io->comment('Envoi du code à Ollama...');

            // Appel du LLM
            $testCode = $this->testGenerator->generateForClass($classCode, $className, $model, $methodName, $existingTestCode);

            $testCode = $this->replaceDynamicHeadersInTestCode($testCode, $targetNamespace, $className);

            file_put_contents($finalAbsoluteFilePath, $testCode);

            if ($testFileExisted) {
                $this->io->success('Le fichier de test existant a été mis à jour et fusionné par Ollama !');
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
}
