<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Agent\AgentServer;
use App\Agent\Tools\AgentTool;
use App\Console\Commands\Concerns\RunsInAGame;
use Illuminate\Console\Command;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * Runs a tool of the AI assistant from the command line, as the server of MCP runs it, and prints what it gives back: for a person of the server who wants to see what the
 * assistant sees, and for the tests. It runs at the first level (it reads), with no consent and so with no line in the trail of the assistant.
 */
class AgentToolCommand extends Command
{
    use RunsInAGame;

    protected $signature = 'agent:tool
        {name? : The name of the tool (rules_read, catalog_list, ...); without it the tools are listed}
        {--input= : What the tool is asked, as a JSON object (nothing when left out)}
        {--game= : The slug of the game (the default game when left out)}';

    protected $description = 'Run a tool of the AI assistant of the panel and print what it gives back (JSON)';

    public function handle(): int
    {
        $tools = array_map(static fn (string $class): AgentTool => app($class), AgentServer::TOOLS);
        $name = $this->argument('name');

        if (! is_string($name) || $name === '') {
            $this->table(['tool', 'level', 'what it does'], array_map(static fn (AgentTool $tool): array => [$tool->name(), $tool->requiredLevel()->value, $tool->description()], $tools));

            return self::SUCCESS;
        }

        $tool = collect($tools)->first(static fn (AgentTool $tool): bool => $tool->name() === $name);

        if ($tool === null) {
            $this->error("There is no tool {$name}. Run agent:tool without a name to list them.");

            return self::FAILURE;
        }

        $given = trim((string) $this->option('input'));
        $input = $given === '' ? [] : json_decode($given, true);

        if (! is_array($input)) {
            $this->error('--input is not a JSON object.');

            return self::FAILURE;
        }

        /** @var Response|ResponseFactory $response */
        $response = app()->call([$tool, 'handle'], ['request' => new Request($input)]);
        $factory = $response instanceof ResponseFactory ? $response : new ResponseFactory($response);
        $failed = $factory->responses()->contains(static fn (Response $one): bool => $one->isError());

        $this->line((string) json_encode(
            $factory->getStructuredContent() ?? $factory->responses()->map(static fn (Response $one): string => (string) $one->content())->all(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        ));

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
