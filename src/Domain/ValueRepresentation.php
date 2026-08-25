<?php

declare(strict_types=1);

namespace MyTree\IndexProviders\Domain;

use JsonSerializable;

/**
 * Describes how descriptive values in an external index record should be
 * interpreted with regard to the original source document.
 */
final readonly class ValueRepresentation implements JsonSerializable
{
    /**
     * @param array<string,mixed> $producer
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        public string $kind,
        public string $scope,
        public bool $verbatimFromProvider,
        public bool $originalDocumentWordingAsserted,
        public ?string $languageHint = null,
        public ?string $scriptHint = null,
        public array $producer = [],
        public array $metadata = [],
    ) {
    }

    public static function indexerRendering(string $provider, ?string $indexedBy = null): self
    {
        $producer = [
            'type' => 'human_indexer',
            'provider' => $provider,
        ];

        $indexedBy = $indexedBy !== null ? trim($indexedBy) : null;
        if ($indexedBy !== null && $indexedBy !== '') {
            $producer['indexer_id'] = $indexedBy;
        }

        return new self(
            kind: 'indexer_rendering',
            scope: 'indexed_descriptive_values',
            verbatimFromProvider: true,
            originalDocumentWordingAsserted: false,
            languageHint: 'pl',
            scriptHint: 'Latn',
            producer: $producer,
            metadata: [
                'may_include' => [
                    'transcription',
                    'transliteration',
                    'translation',
                    'normalization',
                ],
            ],
        );
    }

    /** @return array<string,mixed> */
    public function jsonSerialize(): array
    {
        return [
            'kind' => $this->kind,
            'scope' => $this->scope,
            'verbatim_from_provider' => $this->verbatimFromProvider,
            'original_document_wording_asserted' => $this->originalDocumentWordingAsserted,
            'language_hint' => $this->languageHint,
            'script_hint' => $this->scriptHint,
            'producer' => $this->producer,
            'metadata' => $this->metadata,
        ];
    }
}
