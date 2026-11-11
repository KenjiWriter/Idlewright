<?php

declare(strict_types=1);

namespace App\Agent\Tools;

use App\Agent\AgentContext;
use App\Agent\AgentLevel;
use App\Agent\AgentText;
use App\Agent\ToolRefused;
use App\Models\AgentAction;
use App\Models\AgentGrant;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tool;

/**
 * A tool of the AI assistant of the panel. Every tool is shown only at the level of the consent it needs, is limited in calls a minute, gives back only data (cleaned of
 * text that a person cannot see, see `AgentText`) and leaves a line in the trail of the assistant (`agent_actions`), failed or not. What a tool does is in `run`.
 */
abstract class AgentTool extends Tool
{
    /** The level of the consent the tool needs. */
    protected AgentLevel $level = AgentLevel::Read;

    /** How much of what the tool was asked the trail keeps. */
    private const INPUT_KEPT = 2000;

    /**
     * What the tool gives back: a JSON object.
     *
     * @return array<string, mixed>
     *
     * @throws ToolRefused when it cannot do what it was asked
     */
    abstract protected function run(Request $request): array;

    /**
     * The line the trail keeps of a call.
     *
     * @param  array<string, mixed>  $data
     */
    protected function summary(Request $request, array $data): string
    {
        return $this->name();
    }

    /** The level of the consent the tool needs. */
    public function requiredLevel(): AgentLevel
    {
        return $this->level;
    }

    public function shouldRegister(AgentContext $context): bool
    {
        return $context->level()->covers($this->level);
    }

    public function handle(Request $request, AgentContext $context): Response|ResponseFactory
    {
        $grant = $context->grant();
        $started = hrtime(true);

        if ($grant !== null) {
            $key = "agent-calls:{$grant->id}";

            if (RateLimiter::tooManyAttempts($key, (int) config('ride.agent.calls_per_minute'))) {
                return Response::error('Too many calls in a minute: wait '.RateLimiter::availableIn($key).' s and go on.');
            }

            RateLimiter::hit($key, 60);
        }

        try {
            $text = new AgentText;
            $data = $text->clean($this->run($request));

            if ($text->warnings() !== []) {
                $data['warnings'] = $text->warnings();
            }

            $this->trail($grant, $request, true, $this->summary($request, $data), $started);

            return Response::structured($data);
        } catch (ToolRefused $refused) {
            $this->trail($grant, $request, false, $refused->getMessage(), $started);

            return Response::error($refused->getMessage());
        } catch (ValidationException $invalid) {
            $message = implode(' ', array_merge(...array_values($invalid->errors())));
            $this->trail($grant, $request, false, $message, $started);

            return Response::error($message);
        }
    }

    /** The consent is the one the call started under: a study of the simulators forgets what the request holds while it runs. */
    private function trail(?AgentGrant $grant, Request $request, bool $ok, string $summary, int|float $started): void
    {
        if ($grant === null) {
            return;
        }

        AgentAction::query()->create([
            'agent_grant_id' => $grant->id,
            'game_id' => $grant->game_id,
            'tool' => $this->name(),
            'input' => Str::limit((string) json_encode($request->all(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), self::INPUT_KEPT, '...'),
            'summary' => Str::limit($summary, 490, '...'),
            'ok' => $ok,
            'ms' => (int) ((hrtime(true) - $started) / 1_000_000),
        ]);
    }
}
