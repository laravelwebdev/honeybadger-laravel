<?php

namespace Honeybadger\Tests\Commands;

use Honeybadger\HoneybadgerLaravel\Commands\HoneybadgerInstallCommand;
use Honeybadger\HoneybadgerLaravel\Commands\SuccessMessage;
use Honeybadger\HoneybadgerLaravel\CommandTasks;
use Honeybadger\HoneybadgerLaravel\Contracts\Installer;
use Honeybadger\Tests\TestCase;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Config;

class HoneybadgerInstallCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('honeybadger.report_data', true);
    }

    /** @test */
    public function prompts_for_options_and_outputs_all_successful_operations()
    {
        $installer = $this->createMock(Installer::class);

        $installer->method('sendTestException')
            ->willReturn(['id' => '1234']);

        $installer->method('writeConfig')
            ->willReturn(true);

        $installer->method('shouldPublishConfig')
            ->willReturn(true);

        $installer->method('publishLaravelConfig')
            ->willReturn(true);

        $this->app[Installer::class] = $installer;

        $commandTasks = new CommandTasks;

        $commandTasks->doNotThrowOnError();

        $this->app[CommandTasks::class] = $commandTasks;

        $command = $this->commandMock();

        $command->expects($this->once())
            ->method('requiredSecret')
            ->with('Your API key', 'The API key is required')
            ->willReturn('supersecret');

        $this->app[Kernel::class]->registerCommand($command);

        $this->artisan('honeybadger:install');

        $this->assertEquals([
            'Write HONEYBADGER_API_KEY to .env' => true,
            'Write HONEYBADGER_API_KEY and HONEYBADGER_VERIFY_SSL placeholders to .env.example' => true,
            'Publish the config file' => true,
            'Send test exception to Honeybadger' => [
                'id' => '1234',
            ],
        ], $commandTasks->getResults());
    }

    /** @test */
    public function writes_endpoint_values_to_env_files()
    {
        $installer = $this->createMock(Installer::class);

        $installer->method('sendTestException')
            ->willReturn(['id' => '1234']);

        $installer->method('writeConfig')
            ->willReturn(true);

        $installer->method('shouldPublishConfig')
            ->willReturn(true);

        $installer->method('publishLaravelConfig')
            ->willReturn(true);

        $this->app[Installer::class] = $installer;

        $commandTasks = new CommandTasks;

        $commandTasks->doNotThrowOnError();

        $this->app[CommandTasks::class] = $commandTasks;

        $command = $this->commandMock();

        $this->app[Kernel::class]->registerCommand($command);

        $this->artisan('honeybadger:install', [
            'apiKey' => 'supersecret',
            '--endpoint' => 'https://self-hosted.honeybadger.io',
            '--appEndpoint' => 'https://self-hosted-app.honeybadger.io',
        ]);

        $this->assertEquals([
            'Write HONEYBADGER_API_KEY to .env' => true,
            'Write HONEYBADGER_API_KEY and HONEYBADGER_VERIFY_SSL placeholders to .env.example' => true,
            'Write HONEYBADGER_ENDPOINT to .env' => true,
            'Write HONEYBADGER_ENDPOINT to .env.example' => true,
            'Write HONEYBADGER_APP_ENDPOINT to .env' => true,
            'Write HONEYBADGER_APP_ENDPOINT to .env.example' => true,
            'Publish the config file' => true,
            'Send test exception to Honeybadger' => [
                'id' => '1234',
            ],
        ], $commandTasks->getResults());
    }

    /** @test */
    public function the_correct_config_gets_published_for_lumen()
    {
        $this->app['honeybadger.isLumen'] = true;

        $installer = $this->createMock(Installer::class);

        $installer->method('shouldPublishConfig')
            ->willReturn(true);

        $installer->expects($this->once())
            ->method('publishLumenConfig')
            ->willReturn(true);

        $this->app[Installer::class] = $installer;

        $commandTasks = new CommandTasks;

        $commandTasks->doNotThrowOnError();

        $this->app[CommandTasks::class] = $commandTasks;

        $command = $this->commandMock();

        $command->method('requiredSecret')
            ->willReturn('');

        $this->app[Kernel::class]->registerCommand($command);

        $this->artisan('honeybadger:install');

        $this->assertTrue($commandTasks->getResults()['Publish the config file']);
    }

    /** @test */
    public function publish_does_not_run_if_config_file_exists()
    {
        $installer = $this->createMock(Installer::class);

        $installer->expects($this->never())
            ->method('publishLaravelConfig');

        $installer->method('shouldPublishConfig')
            ->willReturn(false);

        $this->app[Installer::class] = $installer;

        $commandTasks = new CommandTasks;
        $commandTasks->doNotThrowOnError();

        $this->app[CommandTasks::class] = $commandTasks;

        $command = $this->commandMock();

        $command->method('requiredSecret')
            ->willReturn('');

        $this->app[Kernel::class]->registerCommand($command);

        $this->artisan('honeybadger:install');

        $this->assertArrayNotHasKey(
            'Publish the config file',
            $commandTasks->getResults()
        );
    }

    /** @test */
    public function sends_a_test_to_honeybadger()
    {
        $installer = $this->createMock(Installer::class);

        $installer->expects($this->once())
            ->method('sendTestException')
            ->willReturn(['id' => '1234']);

        $this->app[Installer::class] = $installer;

        $commandTasks = new CommandTasks;
        $commandTasks->doNotThrowOnError();

        $this->app[CommandTasks::class] = $commandTasks;

        $command = $this->commandMock();

        $command->method('requiredSecret')
            ->willReturn('asdf123');

        $this->app[Kernel::class]->registerCommand($command);

        $this->artisan('honeybadger:install');

        $this->assertEquals(['id' => '1234'], $commandTasks->getResults()['Send test exception to Honeybadger']);
        $this->assertEquals('asdf123', Config::get('honeybadger.api_key'));
    }

    /** @test */
    public function gracefully_handles_env_file_not_existing()
    {
        $installer = $this->createMock(Installer::class);

        $installer->method('writeConfig')
            ->willReturn(false);

        $this->app[Installer::class] = $installer;

        $commandTasks = new CommandTasks;
        $commandTasks->doNotThrowOnError();

        $this->app[CommandTasks::class] = $commandTasks;

        $command = $this->commandMock();

        $command->method('requiredSecret')
            ->willReturn('');

        $this->app[Kernel::class]->registerCommand($command);

        $this->artisan('honeybadger:install');

        $taskResults = $commandTasks->getResults();

        $this->assertFalse($taskResults['Write HONEYBADGER_API_KEY to .env']);
        $this->assertFalse($taskResults['Write HONEYBADGER_API_KEY and HONEYBADGER_VERIFY_SSL placeholders to .env.example']);
    }

    /** @test */
    public function prompt_for_api_keys_does_not_get_called_if_key_is_passed()
    {
        $this->app[Installer::class] = $this->createMock(Installer::class);

        $command = $this->commandMock();

        // API key
        $command->expects($this->never())
            ->method('requiredSecret');

        $this->app[Kernel::class]->registerCommand($command);

        $this->artisan('honeybadger:install', [
            'apiKey' => 'asdf123',
        ]);
    }

    /** @test */
    public function the_success_block_is_output_with_link_to_notice()
    {
        $installer = $this->createMock(Installer::class);

        $installer->method('shouldPublishConfig')
            ->willReturn(false);
        $installer->method('writeConfig')
            ->willReturn(true);
        $installer->method('sendTestException')
            ->willReturn(['id' => '1234']);

        $this->app[Installer::class] = $installer;

        $command = $this->getMockBuilder(HoneybadgerInstallCommand::class)
            ->disableOriginalClone()
            ->onlyMethods([
                'requiredSecret',
                'confirm',
                'line',
            ])->getMock();

        $command->expects($this->once())
            ->method('line')
            ->with(SuccessMessage::make('1234'));

        $this->app[Kernel::class]->registerCommand($command);

        $this->artisan('honeybadger:install', [
            'apiKey' => 'asdf123',
        ]);
    }

    /** @test */
    public function outputs_retry_text_if_any_tasks_fail()
    {
        $installer = $this->createMock(Installer::class);

        $installer->method('writeConfig')
            ->willReturn(false);

        $this->app[Installer::class] = $installer;

        $commandTasks = new CommandTasks;

        $this->app[CommandTasks::class] = $commandTasks;

        $command = $this->getMockBuilder(HoneybadgerInstallCommand::class)
            ->disableOriginalClone()
            ->onlyMethods([
                'requiredSecret',
                'confirm',
                'error',
            ])->getMock();

        $command->expects($this->once())
            ->method('error')
            ->with('Send test exception to Honeybadger failed, please review output and try again.');

        $this->app[Kernel::class]->registerCommand($command);

        $this->artisan('honeybadger:install', [
            'apiKey' => 'asdf123',
        ]);
    }

    private function commandMock()
    {
        return $this->getMockBuilder(HoneybadgerInstallCommand::class)
            ->disableOriginalClone()
            ->onlyMethods([
                'requiredSecret',
                'line',
                'confirm',
            ])->getMock();
    }
}
