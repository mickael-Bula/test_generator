<?php

namespace App\Command;

use App\Service\TestGenerator;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-test',
    description: 'Génère un test unitaire pour une classe PHP via le LLM.',
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

        $io->title(sprintf('Analyse et génération de test pour : %s', $filePath));

        // 2. Lire le contenu du fichier (notre "parser" V1 ultra-simple)
        $classCode = file_get_contents($fullPath);
        $className = pathinfo($filePath, PATHINFO_FILENAME);

        try {
            $io->comment('Envoi du code au LLM (OpenRouter/Gemini)...');

            // 3. Appeler ton service
            $testCode = $this->testGenerator->generateForClass($classCode, $className);

            // 4. Déterminer le chemin de sortie du test
            // Version naïve : On remplace "src/" par "tests/" et on ajoute "Test.php"
            $testFilePath = str_replace(['src/', '.php'], ['tests/', 'Test.php'], $fullPath);
            $testDir = dirname($testFilePath);

            // Créer le dossier s'il n'existe pas
            if (!is_dir($testDir) && !mkdir($testDir, 0777, true) && !is_dir($testDir)) {
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $testDir));
            }

            // 5. Écrire le fichier de test
            file_put_contents($testFilePath, $testCode);

            $io->success(sprintf(
                'Le fichier de test a été généré avec succès dans : %s',
                str_replace($this->projectDir.'/', '', $testFilePath))
            );

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error('Une erreur est survenue lors de la génération : '.$e->getMessage());

            return Command::FAILURE;
        }
    }
}
