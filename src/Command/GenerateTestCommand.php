<?php

namespace App\Command;

use App\Service\TestGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-test',
    description: 'Génère un test unitaire PHPUnit pour une classe donnée via un LLM, avec validation automatique.',
)]
class GenerateTestCommand extends Command
{
    public function __construct(
        private readonly TestGenerator $testGenerator,
        private readonly string $projectDir, // Injecté automatiquement par Symfony pour connaître la racine
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
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('filePath');

        // 1. Vérifier si le fichier existe
        $fullPath = $this->projectDir.'/'.$filePath;
        if (!file_exists($fullPath)) {
            $io->error(sprintf('Le fichier "%s" n\'existe pas.', $fullPath));

            return Command::FAILURE;
        }

        // On récupère l'option (sera null si non fournie).
        /** @var string|null $methodName */
        $methodName = $input->getOption('method');

        $io->title(sprintf('Analyse et génération de test pour : %s', $filePath));

        if ($methodName) {
            $io->text(sprintf('🎯 Cible spécifique : la méthode <info>%s()</info>', $methodName));
        }

        // 2. Lire le contenu du fichier (le "parser" V1 ultra-simple)
        $classCode = file_get_contents($fullPath);
        $className = pathinfo($filePath, PATHINFO_FILENAME);

        try {
            $io->comment('Envoi du code au LLM (OpenRouter/Gemini)...');

            // 3. Appel du service de génération de test.
            $testCode = $this->testGenerator->generateForClass($classCode, $className, $methodName);

            // 1. On détecte où se trouvait la classe originale (ex : App\Repository)
            $originNamespace = $this->extractNamespaceFromCode($classCode);

            // 2. On génère le namespace de test correspondant (ex : App\Tests\Repository)
            $targetNamespace = str_replace('App\\', 'App\\Tests\\', $originNamespace);

            // 3. Remplacement dynamique des en-têtes
            $testCode = str_replace(
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

            // 4. Déterminer le chemin de sortie du test
            // On convertit le namespace cible (ex : App\Tests\Service) en chemin de sous-dossier (ex : Service)
            $subFolder = str_replace(['App\\Tests\\', '\\'], ['', '/'], $targetNamespace);

            // Le dossier parent final (ex : /mon-projet/tests/Service)
            $finalDisplayDir = sprintf('%s/tests/%s', $this->projectDir, $subFolder);

            // Le chemin absolu complet du fichier final (ex : /mon-projet/tests/Service/VatCalculatorTest.php)
            $finalAbsoluteFilePath = sprintf('%s/%sTest.php', $finalDisplayDir, $className);

            // Créer le dossier parent s'il n'existe pas
            if (!is_dir($finalDisplayDir) && !mkdir($finalDisplayDir, 0777, true) && !is_dir($finalDisplayDir)) {
                throw new \RuntimeException(sprintf('Le dossier "%s" n\'a pas été créé', $finalDisplayDir));
            }

            // 5. Écrire le fichier de test à son emplacement définitif (chemin complet).
            file_put_contents($finalAbsoluteFilePath, $testCode);

            // Affichage d'un chemin relatif propre dans la console
            $relativeLogPath = str_replace($this->projectDir.'/', '', $finalAbsoluteFilePath);
            $io->success(sprintf('Le fichier de test a été généré avec succès dans : %s', $relativeLogPath));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Une erreur est survenue lors de la génération : '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Déduit le namespace de test à partir du namespace déclaré dans la classe testée.
     */
    private function extractNamespaceFromCode(string $classCode): string
    {
        if (preg_match('/namespace\s+([^;]+);/', $classCode, $matches)) {
            return trim($matches[1]);
        }

        return 'App\Tests'; // Valeur par défaut.
    }
}
