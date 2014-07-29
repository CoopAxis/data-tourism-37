#!/usr/bin/env php
<?php
error_reporting(- 1);

define('DIR_CRAWLERS', './crawlers');

/**
 * Console_CommandLine is a full featured package for managing command line options and arguments
 * highly inspired from the python optparse module,
 * it allows the developer to easily build complex command line interfaces.
 *
 * http://pear.php.net/manual/en/package.console.console-commandline.php
 */
require_once ('Console/CommandLine.php');

require_once ( __DIR__.'/lib/Util.php');

$parser = new Console_CommandLine(array(
    'description' => 'Crawl web page with dedicated crawlers.',
    'version' => '0.0.2'
));

try {
    $crawlers = loadCrawlers(DIR_CRAWLERS);
} catch (Exception $ex) {
    echo 'ERROR while loading crawlers: ', $ex->getMessage(), "\n";
    echo print_r($ex, true), "\n";
    exit(- 1);
}

$parser->addArgument('crawlers', array(
    'description' => implode(', ', array_keys($crawlers)),
    'multiple' => true,
    'choices' => array_keys($crawlers)
));

$parser->addOption('delete_dir', array(
    'short_name' => '-d',
    'long_name' => '--delete_dir',
    'action' => 'StoreTrue',
    'description' => 'delete "pages_dir" before crawl'
));

$parser->addOption('sleep', array(
    'short_name' => '-s',
    'long_name' => '--sleep',
    'action' => 'StoreInt',
    'description' => 'milliseconds to sleep between items interval'
));

$parser->addOption('itemsBetweenSleep', array(
    'short_name' => '-i',
    'long_name' => '--items',
    'action' => 'StoreInt',
    'description' => 'number of items interval between sleeps'
));

try {
    $result = $parser->parse();
    
    foreach ($result->args['crawlers'] as $crawlerName) {
        if (! isset($crawlers[$crawlerName])) {
            throw new Exception('Unknow crawler "' . $crawlerName . '"');
        }
        $crawler = $crawlers[$crawlerName];
        echo 'Crawler "', $crawler->getName(), "\"\n";
        
        if ($parser->options['delete_dir'])
            $crawler->setOption('delete_dir', true);
        if ($parser->options['sleep'])
            $crawler->setOption('sleep', true);
        if ($parser->options['itemsBetweenSleep'])
            $crawler->setOption('itemsBetweenSleep', true);
        
        try {
            $crawler->crawl();
        } catch (Exception $ex) {
            echo 'ERROR while crawling: ', $ex->getMessage(), "\n";
            echo 'at line ', $ex->getLine(), ' in file ', $ex->getFile(), "\n";
            exit(- 1);
        }
    }
} catch (Exception $exc) {
    $parser->displayError($exc->getMessage());
    exit(- 1);
}

abstract class Crawler
{
    const OPT_PAGES_DIR = 'pages_dir';

    const OPT_DIR_DELETE = 'delete_dir';

    const OPT_SLEEP = 'sleep';

    const OPT_ITEMSBETWEENSLEEP = 'itemsBetweenSleep';

    const DEFAULT_ENCODING = 'utf8';

    protected $default_options = array(
        self::OPT_PAGES_DIR => '',
        self::OPT_DIR_DELETE => false,
        self::OPT_SLEEP => 250,
        self::OPT_ITEMSBETWEENSLEEP => 10
    );

    protected $options;

    protected $pages_read = 0;

    protected $bytes_read = 0;

    protected $bytes_write = 0;

    public function __construct($options = array())
    {
        $this->default_options[self::OPT_PAGES_DIR] = './tmp.pages' . DIRECTORY_SEPARATOR . $this->getName();
        
        $this->setOptions($options);
    }

    public function setOptions($options)
    {
        foreach (array_keys($options) as $k)
            if (! isset($this->default_options[$k]))
                throw new Exception('Unknow Crawler option "' . $k . '"');
        $this->options = array_merge($this->default_options, $options);
    }

    public function setOption($option, $value)
    {
        $this->setOptions(array(
            $option => $value
        ));
    }

    public function getName()
    {
        return get_called_class();
    }

    public function crawl()
    {
        $pages_dir = $this->options[self::OPT_PAGES_DIR];
        if (file_exists($pages_dir)) {
            if (! $this->options[self::OPT_DIR_DELETE])
                throw new Exception('Folder already exists "' . $pages_dir . '"');
            Util::delTree($pages_dir);
        }
        mkdir($pages_dir, 0777, true);
        
        $this->_crawl();
        
        $this->log('pages_read='.$this->pages_read);
        $this->log('bytes_read='.$this->bytes_read);
        $this->log('bytes_write='.$this->bytes_write);
    }

    protected function onPageRead()
    {
        $this->pages_read ++;
        if ($this->pages_read > 0 && ($this->pages_read % $this->options[self::OPT_ITEMSBETWEENSLEEP] == 0)) {
            $this->log('Read ', $this->pages_read, ' pages, sleeping...');
            usleep($this->options[self::OPT_SLEEP]);
        }
    }

    protected function readPage($url, $inputEncoding = self::DEFAULT_ENCODING)
    {
        $html = file_get_contents($url);

        $this->bytes_read += strlen($html);
        $this->onPageRead();

        $tidy = new tidy();
        $xhtml = $tidy->repairString($html, array(
            'output-xhtml' => true,
            'numeric-entities' => true,
            'char-encoding' => $inputEncoding,
            'clean' => true,
            'input-xml' => false
        ), $inputEncoding);
        
        if ($inputEncoding != self::DEFAULT_ENCODING)
            $xhtml = iconv($inputEncoding, self::DEFAULT_ENCODING, $xhtml);
        
        return $xhtml;
    }

    protected function storePage($name, $content)
    {
        $bytes = file_put_contents($this->options[self::OPT_PAGES_DIR] . DIRECTORY_SEPARATOR . $name, $content);
        $this->bytes_write += $bytes;
    }

    protected function log($msg)
    {
        foreach (func_get_args() as $arg) {
            if (is_object($msg))
                echo print_r($arg, true);
            else
                echo $arg;
        }
        echo "\n";
    }

    protected abstract function _crawl();
}

function loadCrawlers($dir)
{
    $crawlers = array();
    $d = dir($dir);
    while (false !== ($entry = $d->read())) {
        switch ($entry) {
            case '.':
            case '..':
                break;
            default:
                list ($class, $dummy) = explode('.', $entry);
                include_once $dir . DIRECTORY_SEPARATOR . $entry;
                $crawler = new $class();
                if (! $crawler instanceof Crawler)
                    throw new RuntimeException('Crawlers have to extends the Crawler class', - 1);
                $crawlers[$class] = $crawler;
        }
    }
    $d->close();
    return $crawlers;
}
