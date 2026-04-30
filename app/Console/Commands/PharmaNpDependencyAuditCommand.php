<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class PharmaNpDependencyAuditCommand extends Command
{
    protected $signature = 'pharmanp:dependency-audit {--json : Output machine-readable JSON} {--strict : Fail when review candidates are found}';

    protected $description = 'Report likely unused Composer and npm packages without removing anything.';

    public function handle(): int
    {
        $rows = [
            ...$this->npmRows(),
            ...$this->composerRows(),
        ];

        if ($this->option('json')) {
            $this->line(json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info('PharmaNP dependency audit');
            $this->components->warn('This command is read-only. Review candidates manually before removing packages.');
            $this->table(
                ['Eco', 'Package', 'Scope', 'Status', 'Evidence'],
                array_map(fn (array $row) => [
                    $row['ecosystem'],
                    $row['package'],
                    $row['scope'],
                    $row['status'],
                    $row['evidence'] ?: $row['note'],
                ], $rows),
            );
        }

        $reviewCount = collect($rows)->where('status', 'review')->count();

        if ($reviewCount > 0) {
            $this->warn($reviewCount.' package(s) need manual review before removal.');
        }

        return $this->option('strict') && $reviewCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function npmRows(): array
    {
        $packageJson = $this->jsonFile(base_path('package.json'));
        $composerJson = $this->jsonFile(base_path('composer.json'));
        $scripts = implode("\n", [
            json_encode($packageJson['scripts'] ?? [], JSON_UNESCAPED_SLASHES),
            json_encode($composerJson['scripts'] ?? [], JSON_UNESCAPED_SLASHES),
        ]);
        $dependencies = [
            'dependencies' => $packageJson['dependencies'] ?? [],
            'devDependencies' => $packageJson['devDependencies'] ?? [],
        ];
        $haystack = $this->projectText([
            base_path('resources/js'),
            base_path('resources/css'),
            base_path('vite.config.js'),
        ])."\n".$scripts;
        $core = [
            '@vitejs/plugin-react',
            '@tailwindcss/vite',
            'laravel-vite-plugin',
            'tailwindcss',
            'vite',
            'react',
            'react-dom',
        ];

        $signals = [
            'concurrently' => ['concurrently'],
            'antd' => ['antd'],
            '@ant-design/icons' => ['@ant-design/icons'],
            'axios' => ['axios'],
            'dayjs' => ['dayjs'],
            'jspdf' => ['jspdf'],
            'jspdf-autotable' => ['jspdf-autotable', 'autoTable'],
            'recharts' => ['recharts'],
            'xlsx' => ['xlsx'],
        ];

        return $this->dependencyRows('npm', $dependencies, $haystack, $signals, $core);
    }

    private function composerRows(): array
    {
        $composerJson = $this->jsonFile(base_path('composer.json'));
        $scripts = json_encode($composerJson['scripts'] ?? [], JSON_UNESCAPED_SLASHES);
        $dependencies = [
            'require' => $composerJson['require'] ?? [],
            'require-dev' => $composerJson['require-dev'] ?? [],
        ];
        $haystack = $this->projectText([
            base_path('app'),
            base_path('bootstrap'),
            base_path('config'),
            base_path('database'),
            base_path('resources/views'),
            base_path('routes'),
            base_path('tests'),
        ])."\n".$scripts;
        $core = [
            'php',
            'laravel/framework',
            'fakerphp/faker',
            'mockery/mockery',
            'nunomaduro/collision',
            'phpunit/phpunit',
        ];

        $signals = [
            'barryvdh/laravel-dompdf' => ['Barryvdh\\DomPDF', 'dompdf', 'PDF::', '->stream('],
            'laravel/tinker' => ['Laravel\\Tinker', 'artisan tinker'],
            'laravel/pail' => ['pail'],
            'laravel/pint' => ['pint'],
            'laravel/sail' => ['sail'],
            'maatwebsite/excel' => ['Maatwebsite\\Excel', 'Excel::'],
            'rap2hpoutre/fast-excel' => ['Rap2hpoutre\\FastExcel', 'FastExcel'],
            'rubix/ml' => ['Rubix\\ML'],
            'spatie/laravel-permission' => ['Spatie\\Permission', 'HasRoles', 'permission:'],
        ];

        return $this->dependencyRows('composer', $dependencies, $haystack, $signals, $core);
    }

    private function dependencyRows(string $ecosystem, array $groups, string $haystack, array $signals, array $core): array
    {
        $rows = [];

        foreach ($groups as $scope => $packages) {
            foreach (array_keys($packages) as $package) {
                $packageSignals = $signals[$package] ?? [$package];
                $evidence = collect($packageSignals)->first(fn (string $signal) => str_contains($haystack, $signal));
                $isCore = in_array($package, $core, true);

                $rows[] = [
                    'ecosystem' => $ecosystem,
                    'package' => $package,
                    'scope' => $scope,
                    'status' => $isCore || $evidence ? 'used' : 'review',
                    'evidence' => $isCore ? 'framework/build core' : ($evidence ?: null),
                    'note' => $isCore
                        ? 'required by Laravel/Vite toolchain'
                        : 'no direct reference detected; confirm before removal',
                ];
            }
        }

        return $rows;
    }

    private function jsonFile(string $path): array
    {
        if (! File::exists($path)) {
            return [];
        }

        return json_decode(File::get($path), true, flags: JSON_THROW_ON_ERROR);
    }

    private function projectText(array $paths): string
    {
        $chunks = [];

        foreach ($paths as $path) {
            if (File::isFile($path)) {
                $chunks[] = File::get($path);
                continue;
            }

            if (! File::isDirectory($path)) {
                continue;
            }

            foreach (File::allFiles($path) as $file) {
                if ($file->getFilename() === class_basename(self::class).'.php') {
                    continue;
                }

                if (! in_array($file->getExtension(), ['css', 'js', 'jsx', 'json', 'php', 'vue'], true)) {
                    continue;
                }

                $chunks[] = File::get($file->getPathname());
            }
        }

        return implode("\n", $chunks);
    }
}
