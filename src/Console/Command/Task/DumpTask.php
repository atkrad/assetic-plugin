<?php

namespace Assetic\Console\Command\Task;

use Assetic\Asset\FileAsset;
use Assetic\AssetManager;
use Assetic\AssetWriter;
use Cake\Cache\Cache;
use Cake\Console\Shell;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Utility\Xml;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Class DumpTask
 *
 * @package Assetic\Console\Command\Task
 */
class DumpTask extends Shell
{
    /**
     * All path pass to XML file
     *
     * @var array
     */
    protected $paths = [];

    /**
     * Initializes the Shell
     * acts as constructor for subclasses
     * allows configuration of tasks prior to shell execution
     *
     * @return void
     */
    public function initialize()
    {
        parent::initialize();

        $this->paths = [
            '%bower_asset_path%' => Configure::read('Assetic.path.bower'),
            '%npm_asset_path%' => Configure::read('Assetic.path.npm')
        ];
    }

    /**
     * Starts up the Shell and displays the welcome message.
     * Allows for checking and configuring prior to command or main execution
     *
     * Override this method if you want to remove the welcome information,
     * or otherwise modify the pre-command flow.
     *
     * @return void
     */
    public function startup()
    {
        Cache::disable();
    }

    /**
     * Dump all assets
     */
    public function all()
    {
        foreach ($this->getAssetsXMLFiles() as $path => $XMLFile) {
            $this->out('');
            $this->out(__d('assetic_console', '<info>Start dumping "%s" assets:</info>', $path));

            $xml = new Xml();
            $domDocument = $xml->build($XMLFile, ['return' => 'domdocument']);
            $xpath = new DOMXPath($domDocument);
            $assetsNodeList = $xpath->query('/assetic/assets/asset');

            $assetManager = new AssetManager();
            /** @var $assetNode DOMElement */
            foreach ($assetsNodeList as $assetNode) {
                $source = strtr($assetNode->getElementsByTagName('source')->item(0)->nodeValue, $this->paths);
                $destination = strtr(
                    $assetNode->getElementsByTagName('destination')->item(0)->nodeValue,
                    $this->paths
                );

                $assetManager->set(
                    $assetNode->getAttribute('name'),
                    $fileAsset = new FileAsset($source)
                );
                $fileAsset->setTargetPath($destination);
                $this->out($source . ' <info>===>>></info> ' . WWW_ROOT . $destination);
            }

            $assetWriter = new AssetWriter(WWW_ROOT);
            $assetWriter->writeManagerAssets($assetManager);
            $this->dumpStaticFiles($domDocument);
            $this->out(__d('assetic_console', '<info>End</info>'));
        }
    }

    /**
     * Dump static files
     *
     * @param DOMDocument $domDocument
     */
    protected function dumpStaticFiles(DOMDocument $domDocument)
    {
        $xpath = new DOMXPath($domDocument);
        $assetsNodeList = $xpath->query('/assetic/static/files/file');

        $assetManager = new AssetManager();
        /** @var $assetNode DOMElement */
        foreach ($assetsNodeList as $assetNode) {
            $source = strtr($assetNode->getElementsByTagName('source')->item(0)->nodeValue, $this->paths);
            $destination = strtr($assetNode->getElementsByTagName('destination')->item(0)->nodeValue, $this->paths);

            $assetManager->set(
                $assetNode->getAttribute('name'),
                $fileAsset = new FileAsset($source)
            );
            $fileAsset->setTargetPath($destination);
            $this->out($source . ' <info>===>>></info> ' . WWW_ROOT . $destination);
        }

        $assetWriter = new AssetWriter(WWW_ROOT);
        $assetWriter->writeManagerAssets($assetManager);
    }

    /**
     * Get assets XML files
     *
     * @return array
     */
    protected function getAssetsXMLFiles()
    {
        $output = [];
        $appAssetsXML = APP . 'Config' . DS . 'assets.xml';

        if (is_file($appAssetsXML)) {
            $output['App'] = $appAssetsXML;
        } else {
            $this->out(__d('assetic_console', '<warning>App have not assets.xml file.</warning>'), 1, Shell::VERBOSE);
        }

        foreach (Plugin::loaded() as $plugin) {
            $classPath = Plugin::classPath($plugin);
            $configPath = $classPath . 'Config' . DS;
            $assetsFile = $configPath . 'assets.xml';

            if (is_file($assetsFile)) {
                $output[$plugin] = $assetsFile;
            } else {
                $this->out(
                    __d('assetic_console', '<warning>Plugin "%s" have not assets.xml file.</warning>', $plugin),
                    1,
                    Shell::VERBOSE
                );
            }
        }

        return $output;
    }

    /**
     * Gets the option parser instance and configures it.
     *
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function getOptionParser()
    {
        $parser = parent::getOptionParser();
        $parser->description(
            __d(
                'assetic_console',
                'Dump assets into "webroot" directory.'
            )
        )->addSubcommand(
                'all',
                [
                    'help' => __d('assetic_console', 'Dump all assets in all plugins and app.')
                ]
            );

        return $parser;
    }
} 