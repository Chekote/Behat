<?php

/*
 * This file is part of the Behat.
 * (c) Konstantin Kudryashov <ever.zet@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Behat\Behat\Output\Node\Printer\JUnit;

use Behat\Behat\Output\Node\EventListener\JUnit\JUnitOutlineStoreListener;
use Behat\Behat\Output\Node\EventListener\JUnit\JUnitDurationListener;
use Behat\Behat\Output\Node\Printer\Helper\ResultToStringConverter;
use Behat\Gherkin\Node\ExampleNode;
use Behat\Gherkin\Node\FeatureNode;
use Behat\Gherkin\Node\OutlineNode;
use Behat\Gherkin\Node\ScenarioLikeInterface;
use Behat\Testwork\Output\Formatter;
use Behat\Testwork\Output\Printer\JUnitOutputPrinter;
use Behat\Testwork\Tester\Result\TestResult;

/**
 * Prints the <testcase> element.
 *
 * @author Wouter J <wouter@wouterj.nl>
 */
final class JUnitScenarioPrinter
{
    /**
     * @var ResultToStringConverter
     */
    private $resultConverter;

    /**
     * @var JUnitOutlineStoreListener
     */
    private $outlineStoreListener;

    /**
     * @var OutlineNode
     */
    private $lastOutline;

    /**
     * @var int
     */
    private $outlineStepCount;

    /**
     * @var JUnitDurationListener|null
     */
    private $durationListener;

    /**
     * @var string|bool
     */
    private $circleCiNode;

    public function __construct(ResultToStringConverter $resultConverter, JUnitOutlineStoreListener $outlineListener, JUnitDurationListener $durationListener = null)
    {
        $this->resultConverter = $resultConverter;
        $this->outlineStoreListener = $outlineListener;
        $this->durationListener = $durationListener;
        $this->circleCiNode = getenv('CIRCLE_NODE_INDEX');
    }

    /**
     * {@inheritDoc}
     */
    public function printOpenTag(Formatter $formatter, FeatureNode $feature, ScenarioLikeInterface $scenario, TestResult $result, string $file = null)
    {
        $name = implode(' ', array_map(function ($l) {
            return trim($l);
        }, explode("\n", $scenario->getTitle())));

        if ($scenario instanceof ExampleNode) {
            $name = $this->buildExampleName($scenario);
        }

        /** @var JUnitOutputPrinter $outputPrinter */
        $outputPrinter = $formatter->getOutputPrinter();

        $testCaseAttributes = $this->addCircleCiAttributes(array(
            'name'      => $name,
            'classname' => $feature->getTitle(),
            'status'    => $this->resultConverter->convertResultToString($result),
            'time'      => $this->durationListener ? $this->durationListener->getDuration($scenario) : ''
        ), $feature, $scenario);

        if ($file) {
            $cwd = realpath(getcwd());
            $testCaseAttributes['file'] =
                substr($file, 0, strlen($cwd)) === $cwd ?
                    ltrim(substr($file, strlen($cwd)), DIRECTORY_SEPARATOR) : $file;
        }

        $outputPrinter->addTestcase($testCaseAttributes);
    }

    /**
     * Append scenario line number to the path to a feature file
     *
     * This can be used to reference the scenario when running tests in the command line
     *
     * @param string $path
     * @param integer $lineNumber
     * @return string
     */
    private function appendLineNumberToPath(string $path, int $lineNumber) {
        return "$path:$lineNumber";
    }

    /**
     * Converts the absolute feature file path to the relative path to the root of the project
     *
     * @param string $absolutePath
     * @return string
     */
    private function convertToRelativePath(string $absolutePath) {
        return str_replace(getcwd() . '/', '', $absolutePath);
    }

    /**
     * Adds attributes to testcase tag related to CircleCI if in a CircleCI environment
     *
     * @param array $attributes
     * @return array
     */
    private function addCircleCiAttributes(array $attributes, FeatureNode $feature, ScenarioLikeInterface $scenario) {
        if ($this->circleCiNode !== false) {
            $attributes['name'] = "[Node #$this->circleCiNode] {$attributes['name']}";
            $attributes['file'] = $this->appendLineNumberToPath($this->convertToRelativePath($feature->getFile()), $scenario->getLine());
        }

        return $attributes;
    }

    /**
     * @param ExampleNode $scenario
     * @return string
     */
    private function buildExampleName(ExampleNode $scenario)
    {
        $currentOutline = $this->outlineStoreListener->getCurrentOutline($scenario);
        if ($currentOutline === $this->lastOutline) {
            $this->outlineStepCount++;
        } else {
            $this->lastOutline = $currentOutline;
            $this->outlineStepCount = 1;
        }

        $name = $currentOutline->getTitle() . ' #' . $this->outlineStepCount;
        return $name;
    }
}
