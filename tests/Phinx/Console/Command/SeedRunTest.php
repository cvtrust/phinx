<?php

namespace Test\Phinx\Console\Command;

use Phinx\Config\Config;
use Phinx\Config\ConfigInterface;
use Phinx\Console\Command\SeedRun;
use Phinx\Console\PhinxApplication;
use Phinx\Migration\Manager;
use PHPUnit_Framework_MockObject_MockObject;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Output\StreamOutput;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class SeedRunTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ConfigInterface|array
     */
    protected $config = [];

    /**
     * @var InputInterface $input
     */
    protected $input;

    /**
     * @var OutputInterface $output
     */
    protected $output;

    protected function setUp()
    {
        $this->config = new Config([
            'paths' => [
                'migrations' => __FILE__,
                'seeds' => __FILE__,
            ],
            'environments' => [
                'default_migration_table' => 'phinxlog',
                'default_database' => 'development',
                'development' => [
                    'adapter' => 'mysql',
                    'host' => 'fakehost',
                    'name' => 'development',
                    'user' => '',
                    'pass' => '',
                    'port' => 3006,
                ]
            ]
        ]);

        $this->input = new ArrayInput([]);
        $this->output = new StreamOutput(fopen('php://memory', 'a', false));
    }

    public function testExecute()
    {
        $application = new PhinxApplication('testing');
        $application->add(new SeedRun());

        /** @var SeedRun $command */
        $command = $application->find('seed:run');

        // mock the manager class
        /** @var Manager|PHPUnit_Framework_MockObject_MockObject $managerStub */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();
        $managerStub->expects($this->once())
                    ->method('seed')->with($this->identicalTo('development'), $this->identicalTo(null));

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()], ['decorated' => false]);

        $this->assertRegExp('/no environment specified/', $commandTester->getDisplay());
    }

    public function testExecuteWithEnvironmentOption()
    {
        $application = new PhinxApplication('testing');
        $application->add(new SeedRun());

        /** @var SeedRun $command */
        $command = $application->find('seed:run');

        // mock the manager class
        /** @var Manager|PHPUnit_Framework_MockObject_MockObject $managerStub */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();
        $managerStub->expects($this->any())
                    ->method('migrate');

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName(), '--environment' => 'fakeenv'], ['decorated' => false]);
        $this->assertRegExp('/using environment fakeenv/', $commandTester->getDisplay());
    }

    public function testDatabaseNameSpecified()
    {
        $application = new PhinxApplication('testing');
        $application->add(new SeedRun());

        /** @var SeedRun $command */
        $command = $application->find('seed:run');

        // mock the manager class
        /** @var Manager|PHPUnit_Framework_MockObject_MockObject $managerStub */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();
        $managerStub->expects($this->once())
                    ->method('seed');

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()], ['decorated' => false]);
        $this->assertRegExp('/using database development/', $commandTester->getDisplay());
    }

    public function testExecuteMultipleSeeders()
    {
        $application = new PhinxApplication('testing');
        $application->add(new SeedRun());

        /** @var SeedRun $command */
        $command = $application->find('seed:run');

        // mock the manager class
        /** @var Manager|PHPUnit_Framework_MockObject_MockObject $managerStub */
        $managerStub = $this->getMockBuilder('\Phinx\Migration\Manager')
            ->setConstructorArgs([$this->config, $this->input, $this->output])
            ->getMock();
        $managerStub->expects($this->exactly(3))
                    ->method('seed')->withConsecutive(
                        [$this->identicalTo('development'), $this->identicalTo('One')],
                        [$this->identicalTo('development'), $this->identicalTo('Two')],
                        [$this->identicalTo('development'), $this->identicalTo('Three')]
                    );

        $command->setConfig($this->config);
        $command->setManager($managerStub);

        $commandTester = new CommandTester($command);
        $commandTester->execute(
            [
                'command' => $command->getName(),
                '--seed' => ['One', 'Two', 'Three'],
            ],
            ['decorated' => false]
        );

        $this->assertRegExp('/no environment specified/', $commandTester->getDisplay());
    }

    public function testInspectWithoutAnySeeders()
    {
        $application = new PhinxApplication('testing');
        $application->add(new SeedRun());

        /** @var SeedRun $command */
        $command = $application->find('seed:run');

        $ask = function (InputInterface $input, OutputInterface $output, ConfirmationQuestion $question) {
            static $order = -1;

            $order = $order + 1;
            $text = $question->getQuestion();

            $output->write($text." => ");

            // handle a question
            if (strpos($text, 'about to run all the seeds') !== false) {
                $response = 'y';
            }

            if (isset($response) === false) {
                throw new \RuntimeException('Was asked for input on an unhandled question: '.$text);
            }

            $output->writeln(print_r($response, true));
            return $response;
        };

        $helper = $this->getMock('\Symfony\Component\Console\Helper\QuestionHelper', ['ask']);
        $helper->expects($this->any())
            ->method('ask')
            ->will($this->returnCallback($ask));

        $command->getHelperSet()->set($helper, 'question');

        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertRegExp('/about to run all the seeds/', $commandTester->getDisplay());
    }

    public function testInspectWithoutAnySeedersShouldExit()
    {
        $application = new PhinxApplication('testing');
        $application->add(new SeedRun());

        /** @var SeedRun $command */
        $command = $application->find('seed:run');

        $ask = function (InputInterface $input, OutputInterface $output, ConfirmationQuestion $question) {
            static $order = -1;

            $order = $order + 1;
            $text = $question->getQuestion();

            $output->write($text." => ");

            // handle a question
            if (strpos($text, 'about to run all the seeds') !== false) {
                $response = 'n';
            }

            if (isset($response) === false) {
                throw new \RuntimeException('Was asked for input on an unhandled question: '.$text);
            }

            $output->writeln(print_r($response, true));
            return $response;
        };

        $helper = $this->getMock('\Symfony\Component\Console\Helper\QuestionHelper', ['ask']);
        $helper->expects($this->any())
            ->method('ask')
            ->will($this->returnCallback($ask));

        $command->getHelperSet()->set($helper, 'question');

        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertNotRegExp('/All Done/', $commandTester->getDisplay());
    }

    public function testInspectWithoutAnySeedersShouldContinue()
    {
        $application = new PhinxApplication('testing');
        $application->add(new SeedRun());

        /** @var SeedRun $command */
        $command = $application->find('seed:run');

        $ask = function (InputInterface $input, OutputInterface $output, ConfirmationQuestion $question) {
            static $order = -1;

            $order = $order + 1;
            $text = $question->getQuestion();

            $output->write($text." => ");

            // handle a question
            if (strpos($text, 'about to run all the seeds') !== false) {
                $response = 'yes';
            }

            if (isset($response) === false) {
                throw new \RuntimeException('Was asked for input on an unhandled question: '.$text);
            }

            $output->writeln(print_r($response, true));
            return $response;
        };

        $helper = $this->getMock('\Symfony\Component\Console\Helper\QuestionHelper', ['ask']);
        $helper->expects($this->any())
            ->method('ask')
            ->will($this->returnCallback($ask));

        $command->getHelperSet()->set($helper, 'question');

        $commandTester = new CommandTester($command);

        $commandTester->execute([
            'command' => $command->getName(),
        ]);

        $this->assertRegExp('/All Done/', $commandTester->getDisplay());
    }
}
