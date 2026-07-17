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
    private SymfonyStyle $io;

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
        $this->io = new SymfonyStyle($input, $output);
        $filePath = $input->getArgument('filePath');

        // Vérifier si le fichier existe
        $fullPath = $this->projectDir.'/'.$filePath;
        if (!file_exists($fullPath)) {
            $this->io->error(sprintf('Le fichier "%s" n\'existe pas.', $fullPath));

            return Command::FAILURE;
        }

        // On récupère l'option (sera null si non fournie).
        /** @var string|null $methodName */
        $methodName = $input->getOption('method');

        $this->io->title(sprintf('Analyse et génération de test pour : %s', $filePath));

        if ($methodName) {
            $this->io->text(sprintf('🎯 Cible spécifique : la méthode <info>%s()</info>', $methodName));
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

            // Si la commande concerne une classe complète et qu'un fichier de test existe, on lance un avertissement.
            if (!$input->getOption('method') && file_exists($finalAbsoluteFilePath)) {
                $this->io->warning('Un fichier de test existe déjà pour cette classe : '.basename($finalAbsoluteFilePath));

                // On demande confirmation de manière interactive
                $confirm = $this->io->confirm(
                    'Voulez-vous lancer la fusion automatique par le LLM sur ce fichier existant ?',
                    false // Par défaut, on choisit "non" par sécurité
                );

                if (!$confirm) {
                    $this->io->note('Génération annulée pour préserver vos tests existants.');

                    return Command::SUCCESS;
                }
            }

            // Créer le dossier parent s'il n'existe pas
            if (!is_dir($finalDisplayDir) && !mkdir($finalDisplayDir, 0777, true) && !is_dir($finalDisplayDir)) {
                throw new \RuntimeException(sprintf('Le dossier "%s" n\'a pas été créé', $finalDisplayDir));
            }

            // Est-ce qu'un test existe déjà ? Si oui, on charge son code
            $existingTestCode = null;
            $testFileExisted = file_exists($finalAbsoluteFilePath);
            if ($testFileExisted) {
                // On vérifie d'abord si le fichier Git est propre !
                if (Command::FAILURE === $this->checkTestFileIsClean($finalAbsoluteFilePath)) {
                    return Command::FAILURE;
                }

                $this->io->note('Un fichier de test existant a été détecté. Il va être transmis au LLM pour fusion.');
                $existingTestCode = file_get_contents($finalAbsoluteFilePath);

                // Étape cruciale : Pour que le LLM puisse travailler sans être perturbé,
                // on fait l'inverse du nettoyage : on remet temporairement le namespace et la classe
                // au format "Dynamic" dans le code qu'on lui envoie !
                $existingTestCode = $this->replaceDynamicHeadersInExistingTestCode($existingTestCode, $targetNamespace, $className);
            }

            $this->io->comment('Envoi du code au LLM...');

            // Appel du service de génération de test.
            $testCode = $this->testGenerator->generateForClass($classCode, $className, $methodName, $existingTestCode);

            // Remplacement dynamique des en-têtes
            $testCode = $this->replaceDynamicHeadersInTestCode($testCode, $targetNamespace, $className);

            // On écrit le nouveau code, qui écrase le précédent.
            file_put_contents($finalAbsoluteFilePath, $testCode);

            if ($testFileExisted) {
                $this->io->success('Le fichier de test existant a été mis à jour et fusionné par le LLM !');

                // 💡 On guide le développeur vers ses outils habituels
                $this->io->section('🔍 Sécurité & Revue de code');
                $this->io->info([
                    'Le code existant a été préservé et enrichi.',
                    "Utilisez votre IDE ou la commande 'git diff' pour inspecter les ajouts de l'IA.",
                    "Si le résultat ne vous convient pas, vous pouvez l'annuler à tout moment avec :",
                    '👉 git restore '.str_replace($this->projectDir.'/', '', $finalAbsoluteFilePath),
                ]);
            } else {
                // Cas classique : création d'un tout nouveau fichier. Affichage d'un chemin relatif propre dans la console
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
     * Déduit le namespace de test à partir du namespace déclaré dans la classe testée.
     */
    private function extractNamespaceFromCode(string $classCode): string
    {
        if (preg_match('/namespace\s+([^;]+);/', $classCode, $matches)) {
            return trim($matches[1]);
        }

        return 'App\Tests'; // Valeur par défaut.
    }

    /**
     * Remplacement du namespace et du nom de la classe pour enregistrement dans le dossier de test temporaire.
     */
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

    /**
     * Remplacement dynamique du namespace et du nom de la classe pour enregistrement dans le dossier final.
     */
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

    /**
     * On s'assure de ne pas mélanger des modifications locales non commitées avec la génération LLM.
     * Si des modificaitons non suivies dans Git existent, on prévient l'utilisateur.
     */
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
