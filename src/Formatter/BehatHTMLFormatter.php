<?php

namespace eG9yIGF4LGF4\BehatHTMLFormatter\Formatter;

use Behat\Behat\EventDispatcher\Event\AfterFeatureTested;
use Behat\Behat\EventDispatcher\Event\AfterOutlineTested;
use Behat\Behat\EventDispatcher\Event\AfterScenarioTested;
use Behat\Behat\EventDispatcher\Event\AfterStepTested;
use Behat\Behat\EventDispatcher\Event\BeforeFeatureTested;
use Behat\Behat\EventDispatcher\Event\BeforeOutlineTested;
use Behat\Behat\EventDispatcher\Event\BeforeScenarioTested;
use Behat\Behat\Tester\Exception\PendingException;
use Behat\Behat\Tester\Result\ExecutedStepResult;
use Behat\Behat\Tester\Result\SkippedStepResult;
use Behat\Behat\Tester\Result\StepResult;
use Behat\Gherkin\Node\OutlineNode;
use Behat\Testwork\Counter\Memory;
use Behat\Testwork\Counter\Timer;
use Behat\Testwork\EventDispatcher\Event\AfterExerciseCompleted;
use Behat\Testwork\EventDispatcher\Event\AfterSuiteTested;
use Behat\Testwork\EventDispatcher\Event\BeforeExerciseCompleted;
use Behat\Testwork\EventDispatcher\Event\BeforeSuiteTested;
use Behat\Testwork\Output\Formatter;
use Behat\Testwork\Output\Printer\OutputPrinter;
use Behat\Testwork\Tester\Result\ExceptionResult;
use eG9yIGF4LGF4\BehatHTMLFormatter\Classes\Feature;
use eG9yIGF4LGF4\BehatHTMLFormatter\Classes\Scenario;
use eG9yIGF4LGF4\BehatHTMLFormatter\Classes\Step;
use eG9yIGF4LGF4\BehatHTMLFormatter\Classes\Suite;
use eG9yIGF4LGF4\BehatHTMLFormatter\Printer\FileOutputPrinter;
use eG9yIGF4LGF4\BehatHTMLFormatter\Renderer\BaseRenderer;

/**
 * Class BehatHTMLFormatter.
 */
class BehatHTMLFormatter implements Formatter
{
    //<editor-fold desc="Variables">
    /**
     * @var array
     */
    private $parameters;

    /**
     * @var
     */
    private $name;

    /**
     * @var
     */
    private $timer;

    /**
     * @var
     */
    private $memory;

    /**
     * @param string $outputPath where to save the generated report file
     */
    private $outputPath;

    /**
     * @param string $base_path Behat base path
     */
    private $base_path;

    /**
     * Printer used by this Formatter.
     *
     * @param $printer OutputPrinter
     */
    private $printer;

    /**
     * Renderer used by this Formatter.
     *
     * @param $renderer BaseRenderer
     */
    private $renderer;

    /**
     * Flag used by this Formatter.
     *
     * @param $print_args boolean
     */
    private $print_args;

    /**
     * Flag used by this Formatter.
     *
     * @param $print_outp boolean
     */
    private $print_outp;

    /**
     * Flag used by this Formatter.
     *
     * @param $loop_break boolean
     */
    private $loop_break;

    /**
     * @var array
     */
    private $suites;

    /**
     * @var Suite
     */
    private $currentSuite;

    /**
     * @var int
     */
    private $featureCounter = 1;

    /**
     * @var Feature
     */
    private $currentFeature;

    /**
     * @var Scenario
     */
    private $currentScenario;

    /**
     * @var Scenario[]
     */
    private $failedScenarios = array();

    /**
     * @var Scenario[]
     */
    private $pendingScenarios = array();

    /**
     * @var Scenario[]
     */
    private $passedScenarios = array();

    /**
     * @var Feature[]
     */
    private $failedFeatures = array();

    /**
     * @var Feature[]
     */
    private $passedFeatures = array();

    /**
     * @var Step[]
     */
    private $failedSteps = array();

    /**
     * @var Step[]
     */
    private $passedSteps = array();

    /**
     * @var Step[]
     */
    private $pendingSteps = array();

    /**
     * @var Step[]
     */
    private $skippedSteps = array();

    private static $runId;
    private $url;
    private $apiKey;
    private $testRunTitle;
    private $hasFailed = false;

    private $current_message;
    private $current_outline;
    private $current_steps;

    //</editor-fold>

    //<editor-fold desc="Formatter functions">

    /**
     * @param $name
     * @param $base_path
     */
    public function __construct($name, $renderer, $filename, $print_args, $print_outp, $loop_break, $base_path)
    {
        $this->url = 'https://app.testomat.io';
        $this->testRunTitle = 'E2E Test Run';
        $this->apiKey = 'tstmt_tSIQMAd4SZ7RY2jELD-7lXTIkuy5tKuxyw1726839848';
        $this->name = $name;
        $this->base_path = $base_path;
        $this->print_args = $print_args;
        $this->print_outp = $print_outp;
        $this->loop_break = $loop_break;
        $this->renderer = new BaseRenderer($renderer, $base_path);
        $this->printer = new FileOutputPrinter($this->renderer->getNameList(), $filename, $base_path);
        $this->timer = new Timer();
        $this->memory = new Memory();
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * @return array The event names to listen to
     */
    public static function getSubscribedEvents()
    {
        return array(
            'tester.exercise_completed.before' => 'onBeforeExercise',
            'tester.exercise_completed.after' => 'onAfterExercise',
            'tester.suite_tested.before' => 'onBeforeSuiteTested',
            'tester.suite_tested.after' => 'onAfterSuiteTested',
            'tester.feature_tested.before' => 'onBeforeFeatureTested',
            'tester.feature_tested.after' => 'onAfterFeatureTested',
            'tester.scenario_tested.before' => 'onBeforeScenarioTested',
            'tester.scenario_tested.after' => 'onAfterScenarioTested',
            'tester.outline_tested.before' => 'onBeforeOutlineTested',
            'tester.outline_tested.after' => 'onAfterOutlineTested',
            'tester.step_tested.after' => 'onAfterStepTested',
            'tester.example_tested.before' => 'onBeforeOutlineExampleTested',
            'tester.example_tested.after' => 'onAfterOutlineExampleTested'
        );
    }

    /**
     * Returns formatter name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getBasePath()
    {
        return $this->base_path;
    }

    /**
     * Returns formatter description.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'Formatter for teamcity';
    }

    /**
     * Returns formatter output printer.
     *
     * @return OutputPrinter
     */
    public function getOutputPrinter()
    {
        return $this->printer;
    }

    /**
     * Sets formatter parameter.
     *
     * @param string $name
     * @param mixed  $value
     */
    public function setParameter($name, $value)
    {
        $this->parameters[$name] = $value;
    }

    /**
     * Returns parameter name.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function getParameter($name)
    {
        return $this->parameters[$name];
    }

    /**
     * Returns output path.
     *
     * @return string output path
     */
    public function getOutputPath()
    {
        return $this->printer->getOutputPath();
    }

    /**
     * Returns if it should print the step arguments.
     *
     * @return bool
     */
    public function getPrintArguments()
    {
        return $this->print_args;
    }

    /**
     * Returns if it should print the step outputs.
     *
     * @return bool
     */
    public function getPrintOutputs()
    {
        return $this->print_outp;
    }

    /**
     * Returns if it should print scenario loop break.
     *
     * @return bool
     */
    public function getPrintLoopBreak()
    {
        return $this->loop_break;
    }

    public function getTimer()
    {
        return $this->timer;
    }

    public function getMemory()
    {
        return $this->memory;
    }

    public function getSuites()
    {
        return $this->suites;
    }

    public function getCurrentSuite()
    {
        return $this->currentSuite;
    }

    public function getFeatureCounter()
    {
        return $this->featureCounter;
    }

    public function getCurrentFeature()
    {
        return $this->currentFeature;
    }

    public function getCurrentScenario()
    {
        return $this->currentScenario;
    }

    public function getFailedScenarios()
    {
        return $this->failedScenarios;
    }

    public function getPendingScenarios()
    {
        return $this->pendingScenarios;
    }

    public function getPassedScenarios()
    {
        return $this->passedScenarios;
    }

    public function getFailedFeatures()
    {
        return $this->failedFeatures;
    }

    public function getPassedFeatures()
    {
        return $this->passedFeatures;
    }

    public function getFailedSteps()
    {
        return $this->failedSteps;
    }

    public function getPassedSteps()
    {
        return $this->passedSteps;
    }

    public function getPendingSteps()
    {
        return $this->pendingSteps;
    }

    public function getSkippedSteps()
    {
        return $this->skippedSteps;
    }

    public function createRun()
    {
        $runId = getenv('runId');
        if ($runId) {
            self::$runId = $runId;
            return;
        }

        $params = [];

        if (getenv('TESTOMATIO_RUNGROUP_TITLE')) {
            $params['group_title'] = trim(getenv('TESTOMATIO_RUNGROUP_TITLE'));
        }

        if (getenv('TESTOMATIO_ENV')) {
            $params['env'] = trim(getenv('TESTOMATIO_ENV'));
        }

        if (getenv('TESTOMATIO_RUNID')) {
            $params['run_id'] = trim(getenv('TESTOMATIO_RUNID'));
        }

        if (getenv('TESTOMATIO_TITLE')) {
            $params['title'] = trim(getenv('TESTOMATIO_TITLE'));
        } else {
            $params['title'] = $this->testRunTitle.' at '.now();
        }

        if (getenv('TESTOMATIO_SHARED_RUN')) {
            $params['shared_run'] = trim(getenv('TESTOMATIO_SHARED_RUN'));
        }

        if(!array_has($params, 'run_id') ) {
            try {
                $url = $this->url . '/api/reporter?api_key=' . $this->apiKey;
                echo $url."\n";

                $request = \Httpful\Request::post($url)
                    ->sendsJson()
                    ->expectsJson();

                if (!empty($params)) {
                    $request = $request->body($params);
                }
                $response = $request->send();
            } catch (\Exception $e) {
                //$this->writeln("Couldn't start run at Testomatio: " . $e->getMessage());
                exit(1);
            }

            self::$runId = $response->body->uid;
            putenv("TESTOMATIO_RUNID=".self::$runId);

        } else {
            self::$runId = $params['run_id'];
        }
        //$this->writeln("Started Testomatio run " . self::$runId);
    }

    private function getTestId(array $groups)
    {
        foreach ($groups as $group) {
            if (preg_match('/^T\w{8}/', $group)) {
                return substr($group, 1);
            }
        }
    }


    /**
     * Used to add a new test to Run instance
     *
     */
    public function addTestRun($event, $outline)
    {
        if (!$this->apiKey) {
            return;
        }

//        $testId = null;
//        if ($test instanceof \Behat\Gherkin\Node\ScenarioNode) {
//            //$testId = $this->getTestId($test->getTags());
//            $testId = $test->getTitle();
//        }

//        list($suite, $testTitle) = explode(':', Descriptor::getTestAsString($test));
//
//        $testTitle = preg_replace('/^Test\s/', '', trim($testTitle)); // remove "test" prefix

        $result = $event->getTestResult();
        $scenarioPassed = $event->getTestResult()->isPassed();
        $scenarioSkipped = $event->getTestResult()->getResultCode() == 10;
//        $outline = $event->getOutline();
        $feature = $event->getFeature();

        $example = '';
        $testTitle = $outline->getTitle();
        if($this->current_outline !== null) {
            $example = '';
            $examples = explode('|', $outline->getTitle());
            foreach ($examples as $example) {
                $example = trim($example);
                if($example !== '') {
                    break;
                }
            }
            $testTitle = ($this->current_outline->getTitle())." | ".$example;
            //$testTitle = $this->current_outline->getTitle();
        }

        $suite = $feature->getTitle();
        $status = $scenarioPassed ? 'passed' : ($scenarioSkipped ? 'skipped' : 'failed');
        $testId = '';
        $runTime = 0;
        $message = '';
        if ($result instanceof ExceptionResult && $result->hasException()) {
            $message = ': ' . $result->getException()->getMessage();
        } else
        if($this->current_message !== null) {
            $message = trim($this->current_message->getMessage());
        }

        echo "\n\n";
        echo "Test: ".$testTitle."\n";
        echo "Suite: ".$suite."\n";
        echo "TestId: ".$testId."\n";
        echo "Example: ".$example."\n";
        echo "Status: ".$status."\n";
        echo "RunTime: ".($runTime * 1000)."\n";
        echo "Message: \"".$message."\"\n";
        echo "Stgeps: ".$this->current_steps."\n";
        echo "\n\n";



        $body = [
            'api_key' => $this->apiKey,
            'status' => $status,
            'message' => $message,
            'run_time' => $runTime * 1000,
            'title' => trim($testTitle),
            'suite_title' => trim($suite),
            'test_id' => $testId,
            'example' => $example,
        ];

//        if($this->current_steps != '') {
//            array_add($body, 'steps', $this->current_steps);
//        }

        if (trim(getenv('TESTOMATIO_CREATE'))) {
            $body['create'] = true;
        }

        $runId = self::$runId;
        try {
            $url = $this->url . "/api/reporter/$runId/testrun";
            $response = \Httpful\Request::post($url)
                ->body($body)
                ->sendsJson()
                ->expectsJson()
                ->send();
            if (isset($response->body->message)) {
                codecept_debug("Testomatio: " . $response->body->message);
            }
        } catch (\Exception $e) {
            //$this->writeln("[Testomatio] Test $testId-$testTitle was not found in Testomat.io, skipping...");
        }
    }


    /**
     * Update run status
     *
     * @returns {Promise}
     */
    public function updateStatus()
    {
        if (!$this->apiKey) return;
        if (!self::$runId) {
            return;
        }

        $body = [
            'api_key' => $this->apiKey,
            'status_event' => $this->hasFailed ? 'fail' : 'pass',
        ];

        if (getenv('TESTOMATIO_ENV')) {
            $body['env'] = trim(getenv('TESTOMATIO_ENV'));
        }

        try {
            $url = $this->url . "/api/reporter/" . self::$runId;
            $response = \Httpful\Request::put($url)
                ->body($body)
                ->sendsJson()
                ->expectsJson()
                ->send();
        } catch (\Exception $e) {
            //$this->writeln("[Testomatio] Error updating status, skipping...");
        }
    }


    //</editor-fold>

    //<editor-fold desc="Event functions">

    /**
     * @param BeforeExerciseCompleted $event
     */
    public function onBeforeExercise(BeforeExerciseCompleted $event)
    {
        echo "onBeforeExerciseCompleted called\n";

        $this->timer->start();

        $print = $this->renderer->renderBeforeExercise($this);
        $this->printer->write($print);
    }

    /**
     * @param AfterExerciseCompleted $event
     */
    public function onAfterExercise(AfterExerciseCompleted $event)
    {
        echo "onAfterExerciseCompleted called\n";

        $this->timer->stop();

        $print = $this->renderer->renderAfterExercise($this);
        $this->printer->writeln($print);
    }

    /**
     * @param BeforeSuiteTested $event
     */
    public function onBeforeSuiteTested(BeforeSuiteTested $event)
    {
        echo "onBeforeSuiteTested called\n";
//        printf("onBeforeSuiteTested called\n");
        
        $this->currentSuite = new Suite();
        $this->currentSuite->setName($event->getSuite()->getName());

        $print = $this->renderer->renderBeforeSuite($this);
        $this->printer->writeln($print);

        $this->createRun();
    }

    /**
     * @param AfterSuiteTested $event
     */
    public function onAfterSuiteTested(AfterSuiteTested $event)
    {
        echo "onAfterSuiteTested called\n";
//        printf("onAfterSuiteTested called\n");

        $this->suites[] = $this->currentSuite;

        $print = $this->renderer->renderAfterSuite($this);
        $this->printer->writeln($print);

        $this->updateStatus();
    }

    /**
     * @param BeforeFeatureTested $event
     */
    public function onBeforeFeatureTested(BeforeFeatureTested $event)
    {
        echo "onBeforeFeatureTested called\n";
//        printf("onBeforeFeatureTested called\n");


        $feature = new Feature();
        $feature->setId($this->featureCounter);
        ++$this->featureCounter;
        $feature->setName($event->getFeature()->getTitle());
        $feature->setDescription($event->getFeature()->getDescription());
        $feature->setTags($event->getFeature()->getTags());
        $feature->setFile($event->getFeature()->getFile());
        $feature->setScreenshotFolder($event->getFeature()->getTitle());
        $this->currentFeature = $feature;

        $print = $this->renderer->renderBeforeFeature($this);
        $this->printer->writeln($print);
    }

    /**
     * @param AfterFeatureTested $event
     */
    public function onAfterFeatureTested(AfterFeatureTested $event)
    {
        echo "onAfterFeatureTested called\n";
//        printf("onAfterFeatureTested called\n");

        $this->currentSuite->addFeature($this->currentFeature);
        if ($this->currentFeature->allPassed()) {
            $this->passedFeatures[] = $this->currentFeature;
        } else {
            $this->failedFeatures[] = $this->currentFeature;
        }

        $print = $this->renderer->renderAfterFeature($this);
        $this->printer->writeln($print);
    }

    /**
     * @param BeforeScenarioTested $event
     */
    public function onBeforeScenarioTested(BeforeScenarioTested $event)
    {
        echo "onBeforeScenarioTested called\n";
        $this->current_steps = '';

        //        printf("onBeforeScenarioTested called\n");

        $scenario = new Scenario();
        $scenario->setName($event->getScenario()->getTitle());
        $scenario->setTags($event->getScenario()->getTags());
        $scenario->setLine($event->getScenario()->getLine());
        $scenario->setScreenshotName($event->getScenario()->getTitle());
        $scenario->setScreenshotPath(
            $this->printer->getOutputPath().
            '/assets/screenshots/'.
            preg_replace('/\W/', '', $event->getFeature()->getTitle()).'/'.
            preg_replace('/\W/', '', $event->getScenario()->getTitle()).'.png'
        );
        $this->currentScenario = $scenario;

        $print = $this->renderer->renderBeforeScenario($this);
        $this->printer->writeln($print);
    }

    /**
     * @param AfterScenarioTested $event
     */
    public function onAfterScenarioTested(AfterScenarioTested $event)
    {
        echo "onAfterScenarioTested called\n";
//        printf("onAfterScenarioTested called\n");

        $scenarioPassed = $event->getTestResult()->isPassed();

        if ($scenarioPassed) {
            $this->passedScenarios[] = $this->currentScenario;
            $this->currentFeature->addPassedScenario();
            $this->currentScenario->setPassed(true);
        } elseif (StepResult::PENDING == $event->getTestResult()->getResultCode()) {
            $this->pendingScenarios[] = $this->currentScenario;
            $this->currentFeature->addPendingScenario();
            $this->currentScenario->setPending(true);
        } else {
            $this->failedScenarios[] = $this->currentScenario;
            $this->currentFeature->addFailedScenario();
            $this->currentScenario->setPassed(false);
            $this->currentScenario->setPending(false);
        }

        $this->currentScenario->setLoopCount(1);
        $this->currentFeature->addScenario($this->currentScenario);

        $print = $this->renderer->renderAfterScenario($this);
        $this->printer->writeln($print);

        $this->addTestRun($event, $event->getScenario());
    }

    /**
     * @param BeforeOutlineTested $event
     */
    public function onBeforeOutlineTested(BeforeOutlineTested $event)
    {
        echo "onBeforeOutlineTested called\n";
//        printf("onBeforeOutlineTested called\n");


        $scenario = new Scenario();
        $scenario->setName($event->getOutline()->getTitle());
        $scenario->setTags($event->getOutline()->getTags());
        $scenario->setLine($event->getOutline()->getLine());
        $this->currentScenario = $scenario;

        $print = $this->renderer->renderBeforeOutline($this);
        $this->printer->writeln($print);

        $this->current_outline = $event->getOutline();
        $this->current_message = null;
    }

    /**
     * @param AfterOutlineTested $event
     */
    public function onAfterOutlineTested(AfterOutlineTested $event)
    {
        echo "onAfterOutlineTested called\n";
//        printf("onAfterOutlineTested called\n");

        $scenarioPassed = $event->getTestResult()->isPassed();
//        $this->addTestRun($event, $event->getOutline());
//        $scenarioSkipped = $event->getTestResult()->getResultCode() == 10;
//        $outline = $event->getOutline();
//        $feature = $event->getFeature();
//
//        $testTitle = $outline->getTitle();
//        $suite = $feature->getTitle();
//        $status = $scenarioPassed ? 'passed' : ($scenarioSkipped ? 'skipped' : 'failed');
//        $testId = '';
//        $runTime = 0;
//        $message = '';
//
//        echo "\n\n";
//        echo "Test: ".$testTitle."\n";
//        echo "Suite: ".$suite."\n";
//        echo "TestId: ".$testId."\n";
//        echo "Status: ".$status."\n";
//        echo "RunTime: ".($runTime * 1000)."\n";
//        echo "Message: \"".$message."\"\n";
//        echo "\n\n";

        if ($scenarioPassed) {
            $this->passedScenarios[] = $this->currentScenario;
            $this->currentFeature->addPassedScenario();
            $this->currentScenario->setPassed(true);
        } elseif (StepResult::PENDING == $event->getTestResult()->getResultCode()) {
            $this->pendingScenarios[] = $this->currentScenario;
            $this->currentFeature->addPendingScenario();
            $this->currentScenario->setPending(true);
        } else {
            $this->failedScenarios[] = $this->currentScenario;
            $this->currentFeature->addFailedScenario();
            $this->currentScenario->setPassed(false);
            $this->currentScenario->setPending(false);
        }

        $this->currentScenario->setLoopCount(sizeof($event->getTestResult()));
        $this->currentFeature->addScenario($this->currentScenario);

        $print = $this->renderer->renderAfterOutline($this);
        $this->printer->writeln($print);

        $this->current_outline = null;
        $this->current_message = null;
    }



    /**
     * @param BeforeOutlineTested $event
     */
    public function onBeforeOutlineExampleTested(BeforeScenarioTested $event)
    {
        echo "onBeforeOutlineExampleTested called\n";
        $this->current_steps = '';


        //        printf("onBeforeOutlineTested called\n");

//
//        $scenario = new Scenario();
//        $scenario->setName($event->getOutline()->getTitle());
//        $scenario->setTags($event->getOutline()->getTags());
//        $scenario->setLine($event->getOutline()->getLine());
//        $this->currentScenario = $scenario;
//
//        $print = $this->renderer->renderBeforeOutline($this);
//        $this->printer->writeln($print);
    }

    /**
     * @param AfterOutlineTested $event
     */
    public function onAfterOutlineExampleTested(AfterScenarioTested $event)
    {
        echo "onAfterOutlineExampleTested called\n";
//        printf("onAfterOutlineTested called\n");

        $scenarioPassed = $event->getTestResult()->isPassed();
        $this->addTestRun($event, $event->getScenario());
//        $scenarioSkipped = $event->getTestResult()->getResultCode() == 10;
//        $outline = $event->getOutline();
//        $feature = $event->getFeature();
//
//        $testTitle = $outline->getTitle();
//        $suite = $feature->getTitle();
//        $status = $scenarioPassed ? 'passed' : ($scenarioSkipped ? 'skipped' : 'failed');
//        $testId = '';
//        $runTime = 0;
//        $message = '';
//
//        echo "\n\n";
//        echo "Test: ".$testTitle."\n";
//        echo "Suite: ".$suite."\n";
//        echo "TestId: ".$testId."\n";
//        echo "Status: ".$status."\n";
//        echo "RunTime: ".($runTime * 1000)."\n";
//        echo "Message: \"".$message."\"\n";
//        echo "\n\n";
//
//        if ($scenarioPassed) {
//            $this->passedScenarios[] = $this->currentScenario;
//            $this->currentFeature->addPassedScenario();
//            $this->currentScenario->setPassed(true);
//        } elseif (StepResult::PENDING == $event->getTestResult()->getResultCode()) {
//            $this->pendingScenarios[] = $this->currentScenario;
//            $this->currentFeature->addPendingScenario();
//            $this->currentScenario->setPending(true);
//        } else {
//            $this->failedScenarios[] = $this->currentScenario;
//            $this->currentFeature->addFailedScenario();
//            $this->currentScenario->setPassed(false);
//            $this->currentScenario->setPending(false);
//        }
//
//        $this->currentScenario->setLoopCount(sizeof($event->getTestResult()));
//        $this->currentFeature->addScenario($this->currentScenario);
//
//        $print = $this->renderer->renderAfterOutline($this);
//        $this->printer->writeln($print);
    }



    /**
     * @param BeforeStepTested $event
     */
    public function onBeforeStepTested(BeforeStepTested $event)
    {
        echo "onBeforeStepTested called\n";
//        printf("onBeforeStepTested called\n");


        $print = $this->renderer->renderBeforeStep($this);
        $this->printer->writeln($print);
        $this->current_message = null;
    }

    /**
     * @param AfterStepTested $event
     */
    public function onAfterStepTested(AfterStepTested $event)
    {
        try {
            $text = $event->getStep()->getText();
        }
        catch(\Exception $e){
            $text = "";
        }

        echo "onAfterStepTested called >> ".$text."\n";
//        printf("onAfterStepTested called >> ".$text."\n");

        //echo "onAfterStepTested called >> ".$text."\n";
        //printf("onAfterStepTested called >>

//        echo "onAfterStepTested called\n";
//        printf("onAfterStepTested called\n");

        $result = $event->getTestResult();

        /** @var Step $step */
        $step = new Step();
        $step->setKeyword($event->getStep()->getKeyword());
        $step->setText($event->getStep()->getText());
        $step->setLine($event->getStep()->getLine());
        $step->setResult($result);
        $step->setResultCode($result->getResultCode());

        $this->current_steps = $this->current_steps.($event->getStep()->getKeyword())." ".($event->getStep()->getText())." 1000ms\n";

        if ($event->getStep()->hasArguments()) {
            $object = $this->getObject($event->getStep()->getArguments());
            $step->setArgumentType($object->getNodeType());
            $step->setArguments($object);
        }

        $line = $event->getStep()->getLine();
        $text = $event->getStep()->getText();
        $keyt = $event->getStep()->getKeywordType();
        $keyw = $event->getStep()->getKeyword();

        if($line && $text && $keyt && $keyw) {
            echo "onAfterStepTested data >> line: " .$line.", text: ".$text.", key: ".$keyt.":".$keyw."\n";
        }

        $res = $step->getResult();
        if($res instanceof ExecutedStepResult) {
            $e = $res->getException();
            if($e) {
                echo "onAfterStepTested exception >> " .$e. "\n";

                $this->current_message = $e;
            }
        }

        //What is the result of this step ?
        if (is_a($result, 'Behat\Behat\Tester\Result\UndefinedStepResult')) {
            //pending step -> no definition to load
            $this->pendingSteps[] = $step;
        } else {
            if (is_a($result, 'Behat\Behat\Tester\Result\SkippedStepResult')) {
                //skipped step
                /* @var ExecutedStepResult $result */
                $step->setDefinition($result->getStepDefinition());
                $this->skippedSteps[] = $step;
            } else {
                //failed or passed
                if ($result instanceof ExecutedStepResult) {
                    $step->setDefinition($result->getStepDefinition());
                    $exception = $result->getException();
                    if ($exception) {
                        if ($exception instanceof PendingException) {
                            $this->pendingSteps[] = $step;
                        } else {
                            $step->setException($exception->getMessage());
                            $this->failedSteps[] = $step;
                        }
                    } else {
                        $step->setOutput($result->getCallResult()->getStdOut());
                        $this->passedSteps[] = $step;
                    }
                }
            }
        }

        $this->currentScenario->addStep($step);

        $print = $this->renderer->renderAfterStep($this);
        $this->printer->writeln($print);
    }

    //</editor-fold>

    /**
     * @param $arguments
     */
    public function getObject($arguments)
    {
        foreach ($arguments as $argument => $args) {
            return $args;
        }
    }
}
