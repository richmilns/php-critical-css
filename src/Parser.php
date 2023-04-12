<?php

declare(strict_types=1);

namespace CriticalCSS;

use Exception;
use Sabberworm\CSS\CSSList\Document;
use Sabberworm\CSS\OutputFormat;
use Sabberworm\CSS\Parser as SabberwormParser;
use Sabberworm\CSS\Parsing\SourceException;
use Sabberworm\CSS\Property\AtRule;
use Sabberworm\CSS\RuleSet\RuleSet;
use UnexpectedValueException;

/**
 * Critical CSS Parser, creates two files from `$sourceFile` input:
 * - `$sourceFile-critical.css`
 * - `$sourceFile-non-critical.css`
 * ```php
 * $criticalParser = new Parser('test/test.css');
 * $criticalParser->parse()->output();
 * ```
 * Inside your CSS, simply add a `!critical` CSS comment to a selector block (see `test/` folder for example).
 */
class Parser
{
    /**
     * The full path to the source file including filename
     * @var string
     */
    protected string $file;
    /**
     * The source filename
     * @var string
     */
    protected string $filename;
    /**
     * The path to the source file
     * @var string
     */
    protected string $path;
    /**
     * The raw CSS code that we will be parsing
     * @var string
     */
    protected string $source;
    /**
     * The source CSS code parsed by `SabberwormParser`
     * @var Document
     */
    protected Document $parsed;
    /**
     * A blank document that will contain our critical CSS definitions
     * @var Document
     */
    protected Document $critical;
    /**
     * A blank document that will contain our non-critical CSS definitions
     * @var Document
     */
    protected Document $nonCritical;

    /**
     * Create an instance of the parser using `$sourceFile`
     * @param string $sourceFile
     * @return void
     * @throws Exception
     */
    public function __construct(string $sourceFile)
    {
        $this->file = $sourceFile;
        if (!is_readable($this->file)) {
            throw new Exception('Cannot read: ' . $sourceFile);
        }
        if (!$this->source = file_get_contents($this->file)) {
            throw new Exception('Cannot open: ' . $sourceFile);
        }
        $this->filename = basename($this->file);
        $this->path = dirname($this->file);
        $this->critical = new Document();
        $this->nonCritical = new Document();
    }

    /**
     * Parses `$this->source` and splits into critical & non-critical CSS styles
     * @return Parser
     * @throws SourceException
     */
    public function parse(): Parser
    {
        $parser = new SabberwormParser($this->source);
        $this->parsed = $parser->parse();
        foreach ($this->parsed->getContents() as $item) {
            // A) standard selector rule parsing
            if ($item instanceof RuleSet) {
                if ($this->rulesAreCritical($item) === true) {
                    $this->critical->append($item);
                } else {
                    $this->nonCritical->append($item);
                }
                continue;
            }
            // B) @rule parsing
            if ($item instanceof AtRule) {
                if (!method_exists($item, 'getContents')) {
                    $this->nonCritical->append($item);
                    continue;
                }
                $itemIsCritical = false;
                $itemCritical = clone $item;
                $itemCritical->setContents([]);
                $itemNonCritical = clone $itemCritical;
                foreach ($item->getContents() as $subItem) {
                    if ($itemIsCritical === true) {
                        continue;
                    }
                    $itemIsCritical = $this->rulesAreCritical($subItem);
                    if ($itemIsCritical === true) {
                        $itemCritical->append($subItem);
                    } else {
                        $itemNonCritical->append($subItem);
                    }
                }
                if (count($itemCritical->getContents()) > 0) {
                    $this->critical->append($itemCritical);
                }
                if (count($itemNonCritical->getContents()) > 0) {
                    $this->nonCritical->append($itemNonCritical);
                }
            }
        }
        return $this;
    }

    /**
     * Checks if a selector's block of rules contains any `!critical` comments
     * @param mixed $item
     * @return bool
     */
    public function rulesAreCritical($item): bool
    {
        $itemIsCritical = false;
        foreach ($item->getRules() as $rule) {
            if ($itemIsCritical === true) {
                continue;
            }
            foreach ($rule->getComments() as $ruleComment) {
                $comment = str_replace(' ', '', strtolower((string)$ruleComment->getComment()));
                $itemIsCritical = $comment === '!critical';
            }
        }
        return $itemIsCritical;
    }

    /**
     * Returns the critical and non-critical CSS styles as an array
     * @return array<string,Document>
     */
    public function getParts(): array
    {
        return [
            'critical' => $this->critical,
            'non_critical' => $this->nonCritical,
        ];
    }

    /**
     * Returns the critical and non-critical CSS styles as compiled CSS
     * @return array<string,string>
     */
    public function getPartsCompiled(): array
    {
        return [
            'critical' => $this->critical->__toString(),
            'non_critical' => $this->nonCritical->__toString(),
        ];
    }

    /**
     * Returns just the critical CSS styles as a `Document` object
     * @return Document
     */
    public function getCritical(): Document
    {
        return $this->critical;
    }

    /**
     * Returns just the non-critical CSS styles as a `Document` object
     * @return Document
     */
    public function getNonCritical(): Document
    {
        return $this->nonCritical;
    }

    /**
     * Writes out the critical and non-critical CSS files
     * @return bool
     * @throws Exception
     */
    public function output(string $format = 'compact'): bool
    {
        if (!in_array($format, ['compact', 'pretty'])) {
            throw new UnexpectedValueException('Value for format is invalid, expected values are: compact, pretty');
        }
        if ($format === 'compact') {
            $format = OutputFormat::createCompact();
        }
        if ($format === 'pretty') {
            $format = OutputFormat::createPretty();
        }
        if (!is_writable($this->path)) {
            throw new Exception('Cannot write to: ' . $this->path);
        }
        $output1 = file_put_contents(
            $this->path . DIRECTORY_SEPARATOR . str_replace('.css', '-non-critical.css', $this->filename),
            $this->nonCritical->render($format)
        );
        $output2 = file_put_contents(
            $this->path . DIRECTORY_SEPARATOR . str_replace('.css', '-critical.css', $this->filename),
            $this->critical->render($format)
        );
        return $output1 !== false and $output2 !== false;
    }
}
