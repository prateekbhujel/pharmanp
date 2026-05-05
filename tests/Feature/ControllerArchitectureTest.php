<?php

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verifies that controllers remain thin: no private helper methods allowed.
 *
 * The allowlist below is the documented set of exceptions. It should remain
 * small. Add entries only when a private method is truly presentation-only
 * and moving it elsewhere would create unnecessary ceremony.
 */
class ControllerArchitectureTest extends TestCase
{
    /**
     * Documented exceptions: [ControllerClass::method, ...].
     *
     * Each entry must have a comment explaining why it is allowed to stay.
     *
     * @var array<string>
     */
    private const ALLOWLIST = [
        // ModularController::success/error/resource are base-class response helpers,
        // not business logic. They are protected, not private, but listed here for
        // documentation completeness. The scan below only catches private methods.
    ];

    /** @test */
    public function controllers_have_no_private_methods(): void
    {
        $directories = [
            app_path('Http/Controllers'),
            app_path('Modules'),
        ];

        $violations = [];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $files = $this->phpFilesIn($directory);

            foreach ($files as $file) {
                // Only scan files under Http/Controllers paths inside modules
                if (! $this->isControllerFile($file)) {
                    continue;
                }

                $contents = file_get_contents($file);

                if (! $contents) {
                    continue;
                }

                $class = $this->classNameFrom($contents);
                $privateMatches = [];

                preg_match_all('/\bprivate\s+function\s+(\w+)\s*\(/m', $contents, $privateMatches);

                foreach ($privateMatches[1] as $method) {
                    $key = $class.'::'.$method;
                    if (! in_array($key, self::ALLOWLIST, true)) {
                        $violations[] = $key.' in '.str_replace(base_path().'/', '', $file);
                    }
                }
            }
        }

        $this->assertEmpty(
            $violations,
            "The following controller private methods must be moved to services, repositories, resources, or support classes:\n"
            .implode("\n", $violations)
        );
    }

    /** @return array<string> */
    private function phpFilesIn(string $directory): array
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
        );

        $files = [];
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function isControllerFile(string $path): bool
    {
        return Str::contains($path, [
            DIRECTORY_SEPARATOR.'Http'.DIRECTORY_SEPARATOR.'Controllers'.DIRECTORY_SEPARATOR,
        ]) && Str::endsWith($path, 'Controller.php');
    }

    private function classNameFrom(string $contents): string
    {
        if (preg_match('/^class\s+(\w+)/m', $contents, $matches)) {
            return $matches[1];
        }

        return 'UnknownController';
    }
}
