<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/**
 * Read-only view over the trained Teachable Machine model.
 *
 * Everything the browser needs to run inference is derived here from the
 * model's own metadata.json, so retraining with different classes or a
 * different input resolution needs no code change.
 */
class WasteClassifier
{
    /** @var array<string, mixed>|null */
    protected ?array $metadata = null;

    /**
     * Class names the model was trained on, e.g. ["Paper", "Plastic", "Class 3"].
     *
     * @return array<int, string>
     */
    public function labels(): array
    {
        return $this->metadata()['labels'] ?? [];
    }

    public function imageSize(): int
    {
        return (int) ($this->metadata()['imageSize'] ?? config('waste.inference.image_size', 224));
    }

    public function modelName(): string
    {
        return (string) ($this->metadata()['modelName'] ?? 'untitled');
    }

    public function trainedAt(): ?string
    {
        $timestamp = $this->metadata()['timeStamp'] ?? null;

        if (! is_string($timestamp) || $timestamp === '') {
            return null;
        }

        try {
            return Carbon::parse($timestamp)->toDateTimeString();
        } catch (\Throwable) {
            return $timestamp;
        }
    }

    public function isKnownLabel(string $label): bool
    {
        return in_array($label, $this->labels(), true);
    }

    /**
     * Route a model label onto one of the four tracked compartments.
     */
    public function compartmentFor(string $label): string
    {
        $map = config('waste.label_map', []);

        return $map[strtolower(trim($label))] ?? config('waste.default_compartment', 'reject');
    }

    /**
     * @return array{label: string, icon: string, color: string}
     */
    public function compartmentMeta(string $compartment): array
    {
        return config('waste.compartments.'.$compartment, [
            'label' => ucfirst($compartment).' bin',
            'icon' => 'bi-trash3',
            'color' => 'var(--sw-reject)',
        ]);
    }

    /**
     * True once every artefact the browser needs is present in public/.
     */
    public function isInstalled(): bool
    {
        return $this->missingFiles() === [];
    }

    /**
     * @return array<int, string>
     */
    public function missingFiles(): array
    {
        $directory = $this->servedPath();

        return array_values(array_filter(
            ['metadata.json', 'model.json', 'weights.bin'],
            fn (string $file) => ! is_file($directory.$file),
        ));
    }

    public function servedPath(): string
    {
        return rtrim((string) config('waste.model.served'), '/\\').DIRECTORY_SEPARATOR;
    }

    public function sourcePath(): string
    {
        return rtrim((string) config('waste.model.source'), '/\\').DIRECTORY_SEPARATOR;
    }

    /**
     * Payload shared by the live page and the /api/waste-model endpoint.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $classes = [];

        foreach ($this->labels() as $label) {
            $compartment = $this->compartmentFor($label);
            $meta = $this->compartmentMeta($compartment);

            $classes[] = [
                'label' => $label,
                'compartment' => $compartment,
                'compartment_label' => $meta['label'],
                'icon' => $meta['icon'],
                'color' => $meta['color'],
            ];
        }

        return [
            'name' => $this->modelName(),
            'trained_at' => $this->trainedAt(),
            'labels' => $this->labels(),
            'image_size' => $this->imageSize(),
            'classes' => $classes,
            'runtime_version' => config('waste.model.runtime_version'),
            'model_url' => asset(config('waste.model.url').'/model.json'),
            'metadata_url' => asset(config('waste.model.url').'/metadata.json'),
            'runtime_url' => asset(config('waste.model.runtime_url')),
            'installed' => $this->isInstalled(),
            'missing_files' => $this->missingFiles(),
            'threshold' => (float) config('waste.inference.threshold'),
            'top_k' => (int) config('waste.inference.top_k'),
            'frame_interval_ms' => (int) config('waste.inference.frame_interval_ms'),
            'stable_frames' => (int) config('waste.inference.stable_frames'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function metadata(): array
    {
        if ($this->metadata !== null) {
            return $this->metadata;
        }

        $file = $this->servedPath().'metadata.json';

        if (! is_file($file)) {
            return $this->metadata = [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        return $this->metadata = is_array($decoded) ? $decoded : [];
    }
}
