<?php

namespace App\Console\Commands;

use App\Support\WasteClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Copies the trained model out of the "real dataset" folder and into the
 * public directory the browser downloads from.
 *
 * The "real dataset" folder stays the single source of truth so retraining
 * never touches application code, but browsers cannot read outside public/,
 * so the three artefacts have to be mirrored.
 */
class SyncWasteModelCommand extends Command
{
    /** Artefacts that make up a Teachable Machine model. */
    protected const FILES = ['metadata.json', 'model.json', 'weights.bin'];

    protected $signature = 'waste:sync-model
                            {--check : Only report what is missing, do not copy}';

    protected $description = 'Copy the trained model from "real dataset" into public/models/waste-classifier';

    public function handle(WasteClassifier $classifier): int
    {
        $source = $classifier->sourcePath();
        $target = $classifier->servedPath();

        if (! File::isDirectory($source)) {
            $this->error('Source folder not found: '.$source);

            return self::FAILURE;
        }

        $missing = array_values(array_filter(
            self::FILES,
            fn (string $file) => ! File::exists($source.$file),
        ));

        if ($missing !== []) {
            $this->error('The trained model is incomplete. Missing: '.implode(', ', $missing));

            return self::FAILURE;
        }

        if ($this->option('check')) {
            $this->report($source, $target, false);

            return self::SUCCESS;
        }

        File::ensureDirectoryExists($target);

        foreach (self::FILES as $file) {
            File::copy($source.$file, $target.$file);
            $this->line('  <info>copied</info> '.$file);
        }

        $this->newLine();
        $this->report($source, $target, true);

        return self::SUCCESS;
    }

    protected function report(string $source, string $target, bool $synced): void
    {
        $labels = [];

        $metadata = json_decode((string) File::get($source.'metadata.json'), true);

        if (is_array($metadata)) {
            $labels = $metadata['labels'] ?? [];
            $size = (int) ($metadata['imageSize'] ?? 0);
        }

        $this->info('Model: '.($metadata['modelName'] ?? 'unknown'));
        $this->line('  classes   : '.implode(', ', $labels));
        $this->line('  input size: '.$size.'x'.$size);
        $this->line('  trained   : '.($metadata['timeStamp'] ?? 'unknown'));
        $this->line('  served at : '.$target);

        if (! $synced) {
            $this->newLine();
            $this->comment('Run without --check to copy the files into public/.');

            return;
        }

        $this->newLine();
        $this->info('Model is ready. Open /classifications/live to classify a live feed.');
    }
}
