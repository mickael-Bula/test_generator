<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class TestGenerator
{
    public function __construct(
        // Symfony injecte automatiquement le client "openrouter.client" configuré dans le fichier services.yaml
        private HttpClientInterface $openRouterClient
    ) {}

    public function generateForClass(string $classCode, string $className): string
    {
        $response = $this->openRouterClient->request('POST', 'chat/completions', [
            'json' => [
                // Tu peux changer de modèle ici instantanément (ex: google/gemini-flash-1.5)
                'model' => 'google/gemini-pro-1.5',
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => "Tu es un expert PHPUnit et Symfony. Génère un test unitaire. Répond TOUJOURS sous forme d'un objet JSON contenant la clé 'test_code'."
                    ],
                    [
                        'role' => 'user',
                        'content' => "Génère le test pour cette classe ({$className}) :\n\n" . $classCode
                    ]
                ]
            ]
        ]);

        $data = $response->toArray();

        // Le LLM renvoie une chaîne JSON dans le contenu du message, on la décode
        $content = json_decode($data['choices'][0]['message']['content'], true, 512, JSON_THROW_ON_ERROR);

        return $content['test_code'];
    }
}
