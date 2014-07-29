#!/usr/bin/env php
<?php
error_reporting(- 1);

define('DIR_SCRAPPERS', './scrappers');

/**
 * Console_CommandLine is a full featured package for managing command line options and arguments
 * highly inspired from the python optparse module,
 * it allows the developer to easily build complex command line interfaces.
 *
 * http://pear.php.net/manual/en/package.console.console-commandline.php
 */
require_once ('Console/CommandLine.php');

require_once (__DIR__ . '/lib/Util.php');

$parser = new Console_CommandLine(array(
    'description' => 'Scrap web page with dedicated scrappers.',
    'version' => '0.0.2'
));

try {
    $scrappers = loadScrappers(DIR_SCRAPPERS);
} catch (Exception $ex) {
    echo 'ERROR while loading scrappers: ', $ex->getMessage(), "\n";
    echo print_r($ex, true), "\n";
    exit(- 1);
}

$parser->addArgument('scrappers', array(
    'description' => implode(', ', array_keys($scrappers)),
    'multiple' => true,
    'choices' => array_keys($scrappers)
));

$parser->addOption('delete_dir', array(
    'short_name' => '-d',
    'long_name' => '--delete_dir',
    'action' => 'StoreTrue',
    'description' => 'delete "data_dir" before scrap'
));

try {
    $result = $parser->parse();

    foreach ($result->args['scrappers'] as $scrapperName) {
        if (! isset($scrappers[$scrapperName])) {
            throw new Exception('Unknow Scrapper "' . $scrapperName . '"');
        }
        $scrapper = $scrappers[$scrapperName];
        echo 'Crawler "', $scrapper->getName(), "\"\n";

        if ($parser->options['delete_dir'])
            $scrapper->setOption('delete_dir', true);

        try {
            $scrapper->scrap();
        } catch (Exception $ex) {
            echo 'ERROR while scrapping: ', $ex->getMessage(), "\n";
            echo 'at line ', $ex->getLine(), ' in file ', $ex->getFile(), "\n";
            exit(- 1);
        }
    }
} catch (Exception $exc) {
    $parser->displayError($exc->getMessage());
    exit(- 1);
}

abstract class Scrapper
{
    /**
     * @var string Where to write data
     */
    const OPT_DATA_DIR = 'data_dir';
    /**
     * @var string Where to read pages
     */
    const OPT_PAGES_DIR = 'pages_dir';
    
    const OPT_DIR_DELETE = 'delete_dir';
    
    protected $default_options = array(
        self::OPT_PAGES_DIR => '',
        self::OPT_DATA_DIR => './data',
        self::OPT_DIR_DELETE => false
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
            throw new Exception('Unknow Scrapper option "' . $k . '"');
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
    
    public function scrap()
    {
        $data_dir = $this->options[self::OPT_DATA_DIR];
        if (file_exists($data_dir)) {
            if (! $this->options[self::OPT_DIR_DELETE])
                throw new Exception('Folder already exists "' . $data_dir . '"');
            Util::delTree($data_dir);
        }
        mkdir($data_dir, 0777, true);
    
        $this->_scrap();
    
        $this->log('pages_read='.$this->pages_read);
        $this->log('bytes_read='.$this->bytes_read);
        $this->log('bytes_write='.$this->bytes_write);
    }
    
    protected function readPage($filename)
    {
        $content = file_get_contents($filename);
        $this->bytes_read += strlen($content);
        $this->pages_read ++ ;
        return $content ;
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
    
    protected abstract function _scrap();
}

function loadScrappers($dir)
{
    $scrappers = array();
    $d = dir($dir);
    while (false !== ($entry = $d->read())) {
        switch ($entry) {
            case '.':
            case '..':
                break;
            default:
                list ($class, $dummy) = explode('.', $entry);
                include_once $dir . DIRECTORY_SEPARATOR . $entry;
                $scrapper = new $class();
                if (! $scrapper instanceof Scrapper)
                    throw new RuntimeException('Scrappers have to extends the Scrapper class', - 1);
                $scrappers[$class] = $scrapper;
        }
    }
    $d->close();
    return $scrappers;
}
