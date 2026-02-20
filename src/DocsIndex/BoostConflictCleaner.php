<?php

declare(strict_types=1);

namespace Leek\LaravelDocsIndex\DocsIndex;

class BoostConflictCleaner
{
    /**
     * Remove Laravel Boost content that conflicts with local docs index.
     */
    public function clean(string $content): string
    {
        $content = $this->removeSearchDocsSection($content);
        $content = $this->removeSearchDocsLines($content);

        return $this->collapseBlankLines($content);
    }

    /**
     * Remove the entire "Searching Documentation" section including subsections.
     * Matches from "## Searching Documentation" up to the next "===" delimiter or "## " heading.
     */
    protected function removeSearchDocsSection(string $content): string
    {
        $pattern = '/\n*## Searching Documentation.*?(?=\n===|\n## )/s';

        return preg_replace($pattern, '', $content) ?? $content;
    }

    /**
     * Remove individual lines that reference the `search-docs` tool.
     */
    protected function removeSearchDocsLines(string $content): string
    {
        $pattern = '/^.*`search-docs`.*$\n?/m';

        return preg_replace($pattern, '', $content) ?? $content;
    }

    /**
     * Collapse 3+ consecutive blank lines down to 2.
     */
    protected function collapseBlankLines(string $content): string
    {
        return preg_replace('/\n{3,}/', "\n\n", $content) ?? $content;
    }
}
