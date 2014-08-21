<?php

namespace Assetic\Console\Command\Task;

use Assetic\Asset\FileAsset;
use Assetic\Asset\GlobAsset;
use Assetic\AssetManager;
use Assetic\AssetWriter;
use Cake\Cache\Cache;
use Cake\Console\Shell;
use Cake\Core\Configure;
use Cake\Core\Plugin;
use Cake\Utility\File;
use Cake\Utility\Folder;
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
            $this->out(sprintf('<info>Start dumping "%s" assets:</info>', $path));

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
            $this->out('<info>End</info>');
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

        /** @var $assetNode DOMElement */
        foreach ($assetsNodeList as $assetNode) {
            $source = strtr($assetNode->getElementsByTagName('source')->item(0)->nodeValue, $this->paths);
            $destination = strtr($assetNode->getElementsByTagName('destination')->item(0)->nodeValue, $this->paths);

            $des = new Folder(WWW_ROOT . $destination, true, 0777);

            $allFiles = glob($source);

            foreach ($allFiles as $src) {
                if (is_dir($src)) {
                    $srcFolder = new Folder($src);
                    $srcFolder->copy($des->pwd());
                } else {
                    $srcFile = new File($src);
                    $srcFile->copy($des->path . DS . $srcFile->name);
                }

                $this->out($src . ' <info>===>>></info> ' . WWW_ROOT . $destination);
            }
        }
    }

    /**
     * Get assets XML files
     *
     * @return array
     */
    protected function getAssetsXMLFiles()
    {
        $output = [];
        $appAssetsXML = CONFIG . 'assets.xml';

        if (is_file($appAssetsXML)) {
            $output['App'] = $appAssetsXML;
        } else {
            $this->out('<warning>App have not assets.xml file.</warning>', 1, Shell::VERBOSE);
        }

        foreach (Plugin::loaded() as $plugin) {
            $classPath = Plugin::path($plugin);
            $configPath = $classPath . 'config' . DS;
            $assetsFile = $configPath . 'assets.xml';

            if (is_file($assetsFile)) {
                $output[$plugin] = $assetsFile;
            } else {
                $this->out(
                    sprintf('<warning>Plugin "%s" have not assets.xml file.</warning>', $plugin),
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
        $parser->description('Dump assets into "webroot" directory.')
            ->addSubcommand(
                'all',
                [
                    'help' => 'Dump all assets in all plugins and app.'
                ]
            );

        return $parser;
    }
}
