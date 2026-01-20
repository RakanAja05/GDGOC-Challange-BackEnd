<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class GeminiService
{
    public function generate(string $prompt): array
    {
        $key = config('services.gemini.key');
        $model = config('services.gemini.model', 'gemini-1.5-flash');
        $promptLength = mb_strlen($prompt);

        if (empty($key)) {
            Log::warning('Gemini API key is missing.', [
                'model' => $model,
                'prompt_length' => $promptLength,
            ]);

            return [
                'error' => 'GEMINI_API_KEY is not set.',
            ];
        }

        $response = Http::timeout(20)->post(
            "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$key}",
            [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
            ]
        );

        if ($response->failed()) {
            Log::error('Gemini request failed.', [
                'model' => $model,
                'status' => $response->status(),
                'prompt_length' => $promptLength,
                'response' => $response->json(),
            ]);

            return [
                'error' => 'Gemini request failed.',
                'status' => $response->status(),
                'details' => $response->json(),
            ];
        }

        Log::info('Gemini request succeeded.', [
            'model' => $model,
            'status' => $response->status(),
            'prompt_length' => $promptLength,
        ]);

        return $response->json();
    }

    public function getText(string $prompt): ?string
    {
        $result = $this->generate($prompt);

        $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

        if ($text === null) {
            Log::warning('Gemini response missing text.', [
                'has_error' => array_key_exists('error', $result),
                'response_keys' => array_keys($result),
            ]);
        }

        return $text;
    }
}
