<?php

declare(strict_types=1);

namespace Akawaka\Newsletter\Infrastructure\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'init',
    description: 'Initialize newsletter configuration and templates in the current directory',
)]
final class InitCommand extends Command
{
    public function __invoke(
        SymfonyStyle $io,
        #[Option(description: 'Force overwrite existing files', shortcut: 'f')]
        bool $force = false,
    ): int {
        $io->title('Initialize Newsletter Configuration');

        $basePath = self::getEmbeddedResourcesPath();

        $configSource = $basePath . '/config/newsletter.yaml.dist';
        $templatesSource = $basePath . '/templates';

        if (!file_exists($configSource)) {
            $io->error(sprintf('Source configuration not found: %s', $configSource));

            return Command::FAILURE;
        }

        if (!is_dir($templatesSource)) {
            $io->error(sprintf('Source templates directory not found: %s', $templatesSource));

            return Command::FAILURE;
        }

        $created = [];
        $skipped = [];

        // Create config directory and copy newsletter.yaml
        $configDir = getcwd() . '/config';
        $configTarget = $configDir . '/newsletter.yaml';

        if (!is_dir($configDir)) {
            mkdir($configDir, 0o755, true);
        }

        if (file_exists($configTarget) && !$force) {
            $skipped[] = 'config/newsletter.yaml (already exists, use --force to overwrite)';
        } else {
            copy($configSource, $configTarget);
            $created[] = 'config/newsletter.yaml';
        }

        // Copy templates directory
        $templatesTarget = getcwd() . '/templates';

        if (!is_dir($templatesTarget)) {
            mkdir($templatesTarget, 0o755, true);
        }

        $this->copyDirectory($templatesSource, $templatesTarget, $force, $created, $skipped);

        $io->section('Results');

        if (\count($created) > 0) {
            $io->success(sprintf('Created %d file(s):', \count($created)));
            $io->listing($created);
        }

        if (\count($skipped) > 0) {
            $io->note(sprintf('Skipped %d file(s):', \count($skipped)));
            $io->listing($skipped);
        }

        if (0 === \count($created) && \count($skipped) > 0) {
            $io->warning('No files were created. Use --force to overwrite existing files.');
        }

        return Command::SUCCESS;
    }

    /**
     * Returns the path to embedded resources (templates, config).
     * Works both when running from PHAR and from source.
     */
    public static function getEmbeddedResourcesPath(): string
    {
        // When running inside a PHAR, use the phar:// path
        $pharPath = \Phar::running(false);
        if ('' !== $pharPath) {
            return 'phar://' . $pharPath;
        }

        // When running from source, use the project root
        return \dirname(__DIR__, 3);
    }

    private function copyDirectory(string $source, string $target, bool $force, array &$created, array &$skipped): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $item) {
            $relativePath = substr($item->getPathname(), \strlen($source) + 1);
            $targetPath = $target . '/' . $relativePath;

            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0o755, true);
                }
            } else {
                $relativeDisplayPath = 'templates/' . $relativePath;

                if (file_exists($targetPath) && !$force) {
                    $skipped[] = $relativeDisplayPath . ' (already exists)';
                } else {
                    $dir = \dirname($targetPath);
                    if (!is_dir($dir)) {
                        mkdir($dir, 0o755, true);
                    }
                    copy($item->getPathname(), $targetPath);
                    $created[] = $relativeDisplayPath;
                }
            }
        }
    }
}
