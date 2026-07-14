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
use Symfony\Component\Process\Process;

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

        // Vérifier si le fichier existe
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

        // Lire le contenu du fichier (le "parser" V1 ultra-simple)
        $classCode = file_get_contents($fullPath);
        $className = pathinfo($filePath, PATHINFO_FILENAME);

        try {
            // On calcule d'abord où devrait se trouver le fichier de test permanent
            $originNamespace = $this->extractNamespaceFromCode($classCode);

            // On génère le namespace de test correspondant (ex : App\Tests\Repository)
            $targetNamespace = str_replace('App\\', 'App\\Tests\\', $originNamespace);

            // Déterminer le chemin de sortie du test
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

            // Est-ce qu'un test existe déjà ? Si oui, on charge son code
            $existingTestCode = null;
            $testFileExisted = file_exists($finalAbsoluteFilePath);
            if ($testFileExisted) {
                $io->note('Un fichier de test existant a été détecté. Il va être transmis au LLM pour fusion.');
                $existingTestCode = file_get_contents($finalAbsoluteFilePath);

                // Étape cruciale : Pour que le LLM puisse travailler sans être perturbé,
                // on fait l'inverse du nettoyage : on remet temporairement le namespace et la classe
                // au format "Dynamic" dans le code qu'on lui envoie !
                $existingTestCode = str_replace(
                    [sprintf('namespace %s;', $targetNamespace), sprintf('class %sTest', $className)],
                    ['namespace App\Tests\Dynamic;', sprintf('class %sDynamicTest', $className)],
                    $existingTestCode
                );
            }

            $io->comment('Envoi du code au LLM...');

            // Appel du service de génération de test.
            $testCode = $this->testGenerator->generateForClass($classCode, $className, $methodName, $existingTestCode);

            // Remplacement dynamique des en-têtes
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

            // Écrire le fichier de test à son emplacement définitif (chemin complet).
            if ($testFileExisted) {
                // Optionnel : On peut s'assurer que le fichier existant est actuellement propre dans Git
                // pour éviter de mélanger des modifications locales non commitées avec la génération LLM.
                $checkClean = new Process(['git', 'status', '--porcelain', $finalAbsoluteFilePath], $this->projectDir);
                $checkClean->run();
                $isDirty = !empty(trim($checkClean->getOutput()));

                if ($isDirty) {
                    $io->warning('Le fichier de test existant a des modifications non validées (dirty) dans Git.');
                    if (!$io->confirm('Voulez-vous continuer et écraser ces modifications temporairement ?', false)) {
                        return Command::FAILURE;
                    }
                }
            }

            // On écrit le nouveau code (il écrase l'ancien).
            file_put_contents($finalAbsoluteFilePath, $testCode);

            if ($testFileExisted) {
                $io->section('🔍 Intégration Git - Un fichier de test existait déjà');

                // On affiche le diff pour que l'utilisateur voie ce que l'IA a changé/ajouté
                $io->text('Voici le diff des modifications apportées par le LLM :');

                $gitDiff = new Process(['git', 'diff', '--color', $finalAbsoluteFilePath], $this->projectDir);
                $gitDiff->run();

                $io->writeln($gitDiff->getOutput());

                // On propose un choix interactif à l'utilisateur
                $choice = $io->choice(
                    'Que souhaitez-vous faire avec ces modifications ?',
                    [
                        'keep' => "Tout garder (Écraser l'ancien fichier de test par le nouveau)",
                        'discard' => "Tout annuler (Revenir à l'état initial via Git)",
                        'patch' => "Fusionner interactivement (Sélectionner les lignes à garder via 'git checkout -p')",
                    ],
                    'patch' // Par défaut, on propose la fusion interactive
                );

                if ('discard' === $choice) {
                    $restore = new Process(['git', 'restore', $finalAbsoluteFilePath], $this->projectDir);
                    $restore->run();
                    $io->warning("Modifications annulées. Le fichier d'origine a été restauré.");
                } elseif ('patch' === $choice) {
                    $io->section('Commencer la fusion interactive');
                    $io->note("Répondez [y] pour accepter un changement de l'IA, [n] pour le refuser et garder votre code d'origine.");

                    // On lance 'git checkout -p' de manière interactive.
                    // Note : On fait un checkout interactif de l'ancienne version sur notre fichier modifié,
                    // ce qui permet de "rejeter" sélectivement les nouveautés de l'IA qu'on ne veut pas.
                    $patch = new Process(['git', 'checkout', '-p', $finalAbsoluteFilePath], $this->projectDir);

                    // Pour que l'interactivité fonctionne en console (Symfony Process doit lier les entrées/sorties)
                    $patch->setTty(Process::isTtySupported());
                    $patch->run();

                    $io->success('Fusion interactive terminée !');
                } else {
                    $io->success('Nouveau fichier conservé intégralement.');
                }
            } else {
                // Cas classique : création d'un tout nouveau fichier. Affichage d'un chemin relatif propre dans la console
                $relativeLogPath = str_replace($this->projectDir.'/', '', $finalAbsoluteFilePath);
                $io->success(sprintf('Le fichier de test a été généré avec succès dans : %s', $relativeLogPath));
            }

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
