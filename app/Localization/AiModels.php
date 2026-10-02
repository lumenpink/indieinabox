<?php

declare(strict_types=1);

namespace Indieinabox\Localization;

/**
 * Catalog and recommendations for AI & LLM translation models.
 */
class AiModels
{
    /**
     * Complete catalog of supported providers and their models.
     *
     * @return array<string, array<string, array{label: string, recommended: bool, tag: string}>>
     */
    public static function getCatalog(): array
    {
        return [
            'gemini' => [
                'gemini-2.5-flash' => [
                    'label' => 'Gemini 2.5 Flash',
                    'recommended' => true,
                    'tag' => 'Recomendado (Custo-Benefício & Velocidade)',
                ],
                'gemini-2.5-pro' => [
                    'label' => 'Gemini 2.5 Pro',
                    'recommended' => false,
                    'tag' => 'Alta Precisão Literária',
                ],
                'gemini-1.5-flash' => [
                    'label' => 'Gemini 1.5 Flash',
                    'recommended' => false,
                    'tag' => 'Legado Rápido',
                ],
                'gemini-1.5-pro' => [
                    'label' => 'Gemini 1.5 Pro',
                    'recommended' => false,
                    'tag' => 'Legado Pro',
                ],
            ],
            'claude' => [
                'claude-3-5-haiku-20241022' => [
                    'label' => 'Claude 3.5 Haiku',
                    'recommended' => true,
                    'tag' => 'Recomendado (Rápido & Econômico)',
                ],
                'claude-3-7-sonnet-20250219' => [
                    'label' => 'Claude 3.7 Sonnet',
                    'recommended' => false,
                    'tag' => 'Alta Fidelidade Estilística',
                ],
                'claude-3-5-sonnet-20241022' => [
                    'label' => 'Claude 3.5 Sonnet',
                    'recommended' => false,
                    'tag' => 'Alta Precisão',
                ],
            ],
            'openai' => [
                'gpt-4o-mini' => [
                    'label' => 'GPT-4o Mini',
                    'recommended' => true,
                    'tag' => 'Recomendado (Econômico & Rápido)',
                ],
                'gpt-4o' => [
                    'label' => 'GPT-4o',
                    'recommended' => false,
                    'tag' => 'Alta Precisão Multimodal',
                ],
                'o3-mini' => [
                    'label' => 'o3-mini',
                    'recommended' => false,
                    'tag' => 'Raciocínio Estruturado',
                ],
            ],
            'grok' => [
                'grok-2' => [
                    'label' => 'Grok 2',
                    'recommended' => true,
                    'tag' => 'Recomendado',
                ],
                'grok-beta' => [
                    'label' => 'Grok Beta',
                    'recommended' => false,
                    'tag' => 'Beta',
                ],
            ],
            'ollama' => [
                'llama3.2' => [
                    'label' => 'Llama 3.2',
                    'recommended' => true,
                    'tag' => 'Recomendado (Offline / Local Leve)',
                ],
                'mistral' => [
                    'label' => 'Mistral 7B',
                    'recommended' => false,
                    'tag' => 'Equilibrado',
                ],
                'qwen2.5' => [
                    'label' => 'Qwen 2.5',
                    'recommended' => false,
                    'tag' => 'Excelente Multilíngue',
                ],
                'deepseek-r1' => [
                    'label' => 'DeepSeek R1',
                    'recommended' => false,
                    'tag' => 'Raciocínio Local',
                ],
            ],
        ];
    }

    /**
     * Gets models for a given provider.
     *
     * @param string $provider
     * @return array<string, array{label: string, recommended: bool, tag: string}>
     */
    public static function getModelsForProvider(string $provider): array
    {
        $catalog = self::getCatalog();
        return $catalog[strtolower(trim($provider))] ?? [];
    }

    /**
     * Gets the default recommended model for a provider.
     *
     * @param string $provider
     * @return string
     */
    public static function getDefaultModel(string $provider): string
    {
        $models = self::getModelsForProvider($provider);
        foreach ($models as $modelId => $info) {
            if (!empty($info['recommended'])) {
                return $modelId;
            }
        }
        return array_key_first($models) ?? 'default';
    }

    /**
     * Checks if a model is recommended for a provider.
     *
     * @param string $provider
     * @param string $model
     * @return bool
     */
    public static function isRecommended(string $provider, string $model): bool
    {
        $models = self::getModelsForProvider($provider);
        return !empty($models[$model]['recommended']);
    }
}
