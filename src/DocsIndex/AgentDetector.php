<?php

declare(strict_types=1);

namespace Leek\LaravelDocsIndex\DocsIndex;

class AgentDetector
{
    /**
     * Known agent guidelines files in order of preference.
     *
     * @var list<string>
     */
    private const GUIDELINES_FILES = [
        'CLAUDE.md',
        '.cursorrules',
        '.windsurfrules',
        '.github/copilot-instructions.md',
    ];

    /**
     * Detect which agent guidelines files exist in the project.
     *
     * @return list<string> Paths to existing guidelines files
     */
    public function detect(): array
    {
        $found = [];

        foreach (self::GUIDELINES_FILES as $file) {
            if (file_exists(base_path($file))) {
                $found[] = $file;
            }
        }

        return $found;
    }

    /**
     * Get all known guidelines file names.
     *
     * @return list<string>
     */
    public static function knownFiles(): array
    {
        return self::GUIDELINES_FILES;
    }
}
