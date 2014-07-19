<?php

namespace Assetic\Console\Command;

use App\Console\Command\AppShell;
use Cake\Utility\Inflector;

/**
 * Class AsseticShell
 *
 * @package Assetic\Console\Command
 */
class AsseticShell extends AppShell
{
    /**
     * Contains tasks to load and instantiate
     *
     * @var array
     */
    public $tasks = ['Assetic.Dump'];

    /**
     * Default action
     */
    public function main()
    {
        $this->out('');
        $this->out(__d('cake_console', '<info>Available assetic commands:</info>'));
        $this->out('');

        foreach ($this->_taskMap as $task => $config) {
            list($plugin, $name) = pluginSplit($task);
            $this->out('- ' . Inflector::underscore($name));
        }

        $this->out('');
        $this->out(
            __d(
                'cake_console',
                'By using <info>Console/cake assetic.assetic [name]</info> you can invoke a specific assetic task.'
            )
        );
    }

    /**
     * Gets the option parser instance and configures it.
     *
     * @return \Cake\Console\ConsoleOptionParser
     */
    public function getOptionParser()
    {
        $parser = parent::getOptionParser();

        $parser->description(__d('assetic_console', 'Asset Management for CakePHP.'));

        foreach ($this->_taskMap as $task => $config) {
            $taskParser = $this->{$task}->getOptionParser();
            $parser->addSubcommand(
                Inflector::underscore($task),
                [
                    'help' => $taskParser->description(),
                    'parser' => $taskParser
                ]
            );
        }

        return $parser;
    }
}
