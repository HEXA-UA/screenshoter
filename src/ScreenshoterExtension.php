<?php

namespace Hexa\Qa\Screenshoter;

use Codeception\Util\Template;

/**
 * Description: if you want to run screenshots after each action,
 * you have to run tests with the environment variable, for example:
 * codecept run acceptance --env debug
 *
 * or you can run debug only for test who has been failed
 * codecept run acceptance --env debug_failed
 *
 * Created by PhpStorm.
 * User: s.nazarenko
 * Date: 19.05.16
 * Time: 11:12
 */
class ScreenshoterExtension extends \Codeception\Extension
{
    public static $events = [
        'test.before' => 'beforeTest',
        'test.after' => 'afterTest',
        'test.fail' => 'failTest',
        'step.after' => 'afterStep',
        'result.print.after' => 'afterPrintResult'
    ];

    protected $config = [
        'delete_successful' => true,
        'module'            => 'WebDriver',
        'template'          => null,
        'animate_slides'    => true
    ];

    private $counter = '';
    private $screenshoterOn = false;
    private $showPassed = false;
    protected $globalConfig;
    protected $cestName = '';
    protected $indicatorHtml = '';
    protected $slideHtml = '';
    protected $active = true;
    protected $passed = true;

    protected $template = <<<EOF
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recorder Result</title>

    <!-- Bootstrap Core CSS -->
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css" rel="stylesheet">

    <style>
        html,
        body {
            height: 100%;
        }
        .carousel,
        .item,
        .active {
            height: 100%;
        }
        .navbar {
            margin-bottom: 0px !important;
        }
        .first-slide {
            background: rgba(13, 34, 56, 0.8) !important;
        }
        .carousel-caption {
            border: 1px solid white;
            background: rgba(0,0,0,0.8);
            padding-bottom: 50px !important;
        }
        .carousel-caption-last {
            background: rgba(16, 62, 23, 0.8);
            padding-bottom: 50px !important;
        }
        .carousel-caption.error, .carousel-caption.first-slide.error {
            background: #c0392b !important;
        }

        .carousel-inner {
            height: 100%;
            background-color: rgba(0, 255, 0, 0.1);
        }

        .carousel-inner.error {
            background-color: rgba(255, 0, 0, 0.1);
        }

        .fill {
            width: 100%;
            height: 100%;
            text-align: center;
            overflow-y: scroll;
            background-position: top;
            -webkit-background-size: cover;
            -moz-background-size: cover;
            background-size: cover;
            -o-background-size: cover;
        }
        span.number {
            font-size: 40px;
            font-weight: bold;
            float: left;
            margin: 20px;
            border: 5px solid white;
            padding: 5px 10px;
        }
        div.caption-header, div.caption-status {
            font-size: 30px;
        }
        div.caption-common {
            margin: 10px 0;
            font-size: 16px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-default" role="navigation">
        <div class="navbar-header">
            <a class="navbar-brand" href="#">
                <small>{{test}}</small>
            </a>
        </div>
    </nav>
    <header id="steps" class="carousel{{carousel_class}}">
        <!-- Indicators -->
        <ol class="carousel-indicators">
            {{indicators}}
        </ol>

        <!-- Wrapper for Slides -->
        <div class="carousel-inner {{error}}">
            {{slides}}
        </div>

        <!-- Controls -->
        <a class="left carousel-control" href="#steps" data-slide="prev">
            <span class="icon-prev"></span>
        </a>
        <a class="right carousel-control" href="#steps" data-slide="next">
            <span class="icon-next"></span>
        </a>

    </header>

    <!-- jQuery -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.4/jquery.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>

    <!-- Script to Activate the Carousel -->
    <script>
    $('.carousel').carousel({
        wrap: true,
        interval: false
    })

    $(document).bind('keyup', function(e) {
      if(e.keyCode==39){
      jQuery('a.carousel-control.right').trigger('click');
      }

      else if(e.keyCode==37){
      jQuery('a.carousel-control.left').trigger('click');
      }

    });

    </script>

</body>

</html>
EOF;

    protected $indicatorTemplate = <<<EOF
<li data-target="#steps" data-slide-to="{{step}}" {{isActive}}></li>
EOF;

    protected $indexTemplate = <<<EOF
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recorder Results Index</title>

    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <!-- <nav class="navbar navbar-default" role="navigation">
        <div class="navbar-header">
            <a class="navbar-brand" href="#">Recorded Tests
            </a>
        </div>
    </nav> -->
    <div class="container">
        <h1>Record #{{seed}}</h1>
        <ul>
            {{records}}
        </ul>
    </div>

</body>

</html>

EOF;

    protected $slidesTemplate = <<<EOF
<div class="item {{isActive}}">
    <div class="fill">
        <img src="./{{image}}.png">
    </div>
    <div class="carousel-caption {{captionClass}} {{isError}}">
        <span class="number">{{number}}</span>
        <h2>{{caption}}</h2>
        <small>scroll up and down to see the full page</small>
    </div>
</div>
EOF;

    public function __construct($config, $options)
    {
        parent::__construct($config, $options);

        if (isset($this->options['env'])) {
            if ( array_search('debug', $this->options['env']) !== FALSE ) {
                $this->screenshoterOn = true;
            } else if (array_search('debug_all', $this->options['env']) !== FALSE) {
                $this->screenshoterOn = true;
                $this->showPassed = true;
            }
        }

        $this->globalConfig = $this->getGlobalConfig();
    }

    public function beforeTest(\Codeception\Event\TestEvent $e)
    {
        $this->cestName = $e->getTest()->toString();
        $this->counter  = 0;
    }

    public function afterTest(\Codeception\Event\TestEvent $e)
    {
        $html = (new Template($this->template))
            ->place('indicators', $this->indicatorHtml)
            ->place('slides', $this->slideHtml)
            ->place('error', (!$this->passed)?'error':'')
            ->place('test', $this->cestName)
            ->place('carousel_class', $this->config['animate_slides'] ? ' slide' : '')
            ->produce();

        $failed = (!$this->passed)?'___FAILED___':'';

        file_put_contents($this->globalConfig['paths']['log'].'/debug/'.$this->cestName.'-00-index'.$failed.'.html', $html);
        $this->counter = 0;
        $this->indicatorHtml = '';
        $this->slideHtml = '';
        $this->active = true;
    }

    public function failTest(\Codeception\Event\FailEvent $e)
    {
    }

    public function afterStep(\Codeception\Event\StepEvent $e)
    {
        if ($this->screenshoterOn) {
            $I = $this->getModule('WebDriver');
            $this->counter++;

            if ($this->counter < 10) {
                $counterString = '0'.$this->counter;
            } else {
                $counterString = (string) $this->counter;
            }

            if ($e->getStep()->hasFailed()) {
                $this->passed = false;
                $failed = '___FAILED___';
            } else {
                $failed = '';
            }

            if ($this->showPassed || $e->getStep()->hasFailed())
            {
                $fileName = $this->cestName.'-'.$counterString.'-'.$this->getStepAction($e).$failed;

                try {
                    $I->webDriver->switchTo()->alert()->getText();
                } catch (\Facebook\WebDriver\Exception\NoAlertOpenException $exception) {
                    $I->makeScreenshot($fileName);
                    $this->addToIndexFile($fileName, $e->getStep()->getAction(), $e->getStep()->hasFailed(), $this->createOutputText($e));
                    $this->active = false;
                }
            }
        }
    }

    public function afterPrintResult(\Codeception\Event\PrintResultEvent $e)
    {
        //here can be total index.html
        /*

         */
    }

    protected function addToIndexFile($fileName, $step, $failed=false, $caption='')
    {
        $this->indicatorHtml .= (new Template($this->indicatorTemplate))
            ->place('step', $fileName)
            ->place('isActive', $this->active ? 'class="active"' : '')
            ->produce();

        $this->slideHtml .= (new Template($this->slidesTemplate))
            ->place('image', $fileName)
            ->place('caption', $caption)
            ->place('number', $this->counter)
            ->place('captionClass', ($this->counter == 1) ? 'first-slide' : '' )
            ->place('isActive', $this->active ? 'active' : '')
            ->place('isError', $failed ? 'error' : '')
            ->produce();
    }

    protected function createOutputText(\Codeception\Event\StepEvent $e)
    {
        $outputText = '';
        $outputText .= "<div class='caption-header'>Action: ".$e->getStep()->getAction()."</div>";
        $outputText .= "<div class='caption-common'>Step: ".$e->getStep()->getHumanizedActionWithoutArguments().", Args: ".$e->getStep()->getHumanizedArguments()."</div>";
        $outputText .= "<div class='caption-common'>Code:\n\t".$e->getStep()->getPhpCode(500)."</div>";
        $outputText .= "<div class='caption-common'>Line: ".$e->getStep()->getLine()."</div>";
        $outputText .= "<div class='caption-status'>Status: ".(($e->getStep()->hasFailed())?"FAILED":"PASSED")."</div>";

        return $outputText;
    }

    protected function getStepAction(\Codeception\Event\StepEvent $e)
    {
        if (preg_match('~\s~isu', $e->getStep()->getAction())) {
            $stepAction = 'notAction';
        } else {
            $stepAction = $e->getStep()->getAction();
        }

        return $stepAction;
    }
}