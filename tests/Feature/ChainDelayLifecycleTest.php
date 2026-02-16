<?php

namespace Tests\Feature;

use App\Jobs\DispatcherJob;
use App\Jobs\ResolveIntentJob;
use App\Models\ActionChain;
use App\Models\ScheduledAction;
use App\Models\User;
use App\Services\ChainService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChainDelayLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private ChainService $chainService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->user->account->update(['manual_plan' => 'pro']);
        $this->chainService = app(ChainService::class);
    }

    /**
     * Regression test: delay steps must use the 'delay' intent key,
     * not the 'preset' key. Arbitrary durations like '5m' are not presets.
     */
    public function test_delay_step_uses_delay_intent_key(): void
    {
        Queue::fake();

        $chain = $this->chainService->createChain($this->user->account, [
            'name' => 'Test Chain',
            'steps' => [
                ['name' => 'Step 1', 'type' => 'http_call', 'url' => 'https://example.com', 'method' => 'POST'],
                ['name' => 'Wait 5m', 'type' => 'delay', 'delay' => '5m'],
                ['name' => 'Step 2', 'type' => 'http_call', 'url' => 'https://example.com', 'method' => 'POST'],
            ],
        ], $this->user);

        // Simulate step 0 completing and chain advancing to the delay step
        $step0 = ScheduledAction::where('chain_id', $chain->id)->where('chain_step', 0)->first();
        $step0->resolution_status = ScheduledAction::STATUS_EXECUTED;
        $step0->save();

        $this->chainService->advanceChain($chain, $step0, ['status' => 'completed']);

        // The delay step action should have been created with 'delay' key, not 'preset'
        $delayAction = ScheduledAction::where('chain_id', $chain->id)->where('chain_step', 1)->first();
        $this->assertNotNull($delayAction);
        $this->assertEquals('wall_clock', $delayAction->intent_type);
        $this->assertArrayHasKey('delay', $delayAction->intent_payload);
        $this->assertArrayNotHasKey('preset', $delayAction->intent_payload);
        $this->assertEquals('5m', $delayAction->intent_payload['delay']);
    }

    /**
     * Regression test: ensure '5m' delay is correctly resolved by IntentResolver
     * when routed through the 'delay' key.
     */
    public function test_delay_step_resolves_5m_correctly(): void
    {
        Queue::fake();

        $chain = $this->chainService->createChain($this->user->account, [
            'name' => 'Test Chain',
            'steps' => [
                ['name' => 'Step 1', 'type' => 'http_call', 'url' => 'https://example.com', 'method' => 'POST'],
                ['name' => 'Wait 5m', 'type' => 'delay', 'delay' => '5m'],
                ['name' => 'Step 2', 'type' => 'http_call', 'url' => 'https://example.com', 'method' => 'POST'],
            ],
        ], $this->user);

        // Advance to delay step
        $step0 = ScheduledAction::where('chain_id', $chain->id)->where('chain_step', 0)->first();
        $step0->resolution_status = ScheduledAction::STATUS_EXECUTED;
        $step0->save();

        $this->chainService->advanceChain($chain, $step0, ['status' => 'completed']);

        $delayAction = ScheduledAction::where('chain_id', $chain->id)->where('chain_step', 1)->first();

        // Actually run the ResolveIntentJob (not faked)
        (new ResolveIntentJob($delayAction))->handle(app(\App\Services\IntentResolver::class));

        $delayAction->refresh();
        $this->assertEquals(ScheduledAction::STATUS_RESOLVED, $delayAction->resolution_status);
        $this->assertNotNull($delayAction->execute_at_utc);
        // Should be approximately 5 minutes from now
        $this->assertEqualsWithDelta(
            now()->addMinutes(5)->timestamp,
            $delayAction->execute_at_utc->timestamp,
            5 // 5 seconds tolerance
        );
    }

    /**
     * Test that the dispatcher handles no-op delay steps and advances the chain.
     */
    public function test_dispatcher_executes_delay_step_and_advances_chain(): void
    {
        // Create a chain already at the delay step (step 1)
        $chain = ActionChain::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Delay Test Chain',
            'steps' => [
                ['name' => 'Step 1', 'type' => 'http_call', 'url' => 'https://example.com', 'method' => 'POST'],
                ['name' => 'Wait', 'type' => 'delay', 'delay' => '5m'],
                ['name' => 'Step 2', 'type' => 'http_call', 'url' => 'https://example.com', 'method' => 'POST'],
            ],
            'context' => ['steps' => [0 => ['status' => 'executed', 'response' => []]]],
            'status' => ActionChain::STATUS_RUNNING,
            'current_step' => 1,
            'error_handling' => ActionChain::ERROR_FAIL_CHAIN,
            'started_at' => now()->subMinutes(10),
        ]);

        // Create the delay action in RESOLVED state (due now)
        $delayAction = ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'chain_id' => $chain->id,
            'chain_step' => 1,
            'name' => 'Wait',
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'intent_type' => ScheduledAction::INTENT_WALL_CLOCK,
            'intent_payload' => ['delay' => '5m'],
            'timezone' => 'UTC',
            'resolution_status' => ScheduledAction::STATUS_RESOLVED,
            'execute_at_utc' => now()->subMinute(), // Due
            'request' => null, // No HTTP request — it's a delay step
        ]);

        // Queue should be faked to catch the next step's ResolveIntentJob
        Queue::fake();

        (new DispatcherJob)->handle();

        // Delay action should be executed
        $delayAction->refresh();
        $this->assertEquals(ScheduledAction::STATUS_EXECUTED, $delayAction->resolution_status);

        // Chain should have advanced to step 2
        $chain->refresh();
        $this->assertEquals(2, $chain->current_step);

        // A new action should be created for step 2
        $step2Action = ScheduledAction::where('chain_id', $chain->id)->where('chain_step', 2)->first();
        $this->assertNotNull($step2Action);
        $this->assertEquals(ScheduledAction::STATUS_PENDING_RESOLUTION, $step2Action->resolution_status);
        $this->assertNotNull($step2Action->request); // HTTP step has a request
    }

    /**
     * Test that recoverStuckChains recovers a chain whose step action failed
     * during intent resolution.
     */
    public function test_recover_stuck_chain_with_failed_step(): void
    {
        $chain = ActionChain::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Stuck Chain',
            'steps' => [
                ['name' => 'Step 1', 'type' => 'http_call', 'url' => 'https://example.com', 'method' => 'POST'],
                ['name' => 'Wait', 'type' => 'delay', 'delay' => '5m'],
                ['name' => 'Step 2', 'type' => 'http_call', 'url' => 'https://example.com', 'method' => 'POST'],
            ],
            'context' => ['steps' => [0 => ['status' => 'executed', 'response' => []]]],
            'status' => ActionChain::STATUS_RUNNING,
            'current_step' => 1,
            'error_handling' => ActionChain::ERROR_FAIL_CHAIN,
            'started_at' => now()->subMinutes(30),
        ]);

        // Backdate updated_at to trigger recovery (not in fillable)
        ActionChain::where('id', $chain->id)->toBase()->update(['updated_at' => now()->subMinutes(10)]);

        // The delay step action that failed during intent resolution
        ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'chain_id' => $chain->id,
            'chain_step' => 1,
            'name' => 'Wait',
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'intent_type' => ScheduledAction::INTENT_WALL_CLOCK,
            'intent_payload' => ['preset' => '5m'], // The old buggy key
            'timezone' => 'UTC',
            'resolution_status' => ScheduledAction::STATUS_FAILED,
            'failure_reason' => 'Intent resolution failed: Unknown preset: 5m',
        ]);

        Queue::fake();

        (new DispatcherJob)->handle();

        // Chain should now be marked as failed (fail_chain error handling)
        $chain->refresh();
        $this->assertEquals(ActionChain::STATUS_FAILED, $chain->status);
        $this->assertNotNull($chain->failure_reason);
        $this->assertStringContainsString('failed', $chain->failure_reason);
    }

    /**
     * Test that recoverStuckChains advances a chain whose step was executed
     * but chain advancement silently failed.
     */
    public function test_recover_stuck_chain_with_executed_step_not_advanced(): void
    {
        $chain = ActionChain::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Stuck Chain',
            'steps' => [
                ['name' => 'Step 1', 'type' => 'http_call', 'url' => 'https://example.com', 'method' => 'POST'],
                ['name' => 'Wait', 'type' => 'delay', 'delay' => '5m'],
                ['name' => 'Step 2', 'type' => 'http_call', 'url' => 'https://example.com', 'method' => 'POST'],
            ],
            'context' => ['steps' => [0 => ['status' => 'executed', 'response' => []]]],
            'status' => ActionChain::STATUS_RUNNING,
            'current_step' => 1,
            'error_handling' => ActionChain::ERROR_FAIL_CHAIN,
            'started_at' => now()->subMinutes(30),
        ]);

        ActionChain::where('id', $chain->id)->toBase()->update(['updated_at' => now()->subMinutes(10)]);

        // The delay step was executed, but chain didn't advance
        ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'chain_id' => $chain->id,
            'chain_step' => 1,
            'name' => 'Wait',
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'intent_type' => ScheduledAction::INTENT_WALL_CLOCK,
            'intent_payload' => ['delay' => '5m'],
            'timezone' => 'UTC',
            'resolution_status' => ScheduledAction::STATUS_EXECUTED,
            'execute_at_utc' => now()->subMinutes(10),
            'executed_at_utc' => now()->subMinutes(10),
        ]);

        Queue::fake();

        (new DispatcherJob)->handle();

        // Chain should have advanced to step 2
        $chain->refresh();
        $this->assertEquals(2, $chain->current_step);

        // A new action should be created for step 2
        $step2Action = ScheduledAction::where('chain_id', $chain->id)->where('chain_step', 2)->first();
        $this->assertNotNull($step2Action);
    }

    /**
     * Test that recoverStuckChains skips chains that were recently updated.
     */
    public function test_recover_does_not_touch_recently_updated_chains(): void
    {
        $chain = ActionChain::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Active Chain',
            'steps' => [
                ['name' => 'Step 1', 'type' => 'http_call', 'url' => 'https://example.com', 'method' => 'POST'],
                ['name' => 'Wait', 'type' => 'delay', 'delay' => '5m'],
                ['name' => 'Step 2', 'type' => 'http_call', 'url' => 'https://example.com', 'method' => 'POST'],
            ],
            'context' => ['steps' => [0 => ['status' => 'executed', 'response' => []]]],
            'status' => ActionChain::STATUS_RUNNING,
            'current_step' => 1,
            'error_handling' => ActionChain::ERROR_FAIL_CHAIN,
            'started_at' => now()->subMinutes(2),
            // updated_at defaults to now() — recently updated, should NOT be recovered
        ]);

        // Step failed, but chain was just updated — don't recover yet
        ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'chain_id' => $chain->id,
            'chain_step' => 1,
            'name' => 'Wait',
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'intent_type' => ScheduledAction::INTENT_WALL_CLOCK,
            'intent_payload' => ['delay' => '5m'],
            'timezone' => 'UTC',
            'resolution_status' => ScheduledAction::STATUS_FAILED,
            'failure_reason' => 'Some error',
        ]);

        Queue::fake();

        (new DispatcherJob)->handle();

        // Chain should NOT have been recovered — still running
        $chain->refresh();
        $this->assertEquals(ActionChain::STATUS_RUNNING, $chain->status);
    }

    /**
     * Test that skip_step error handling skips a failed delay step and continues.
     */
    public function test_recover_stuck_chain_with_skip_step_error_handling(): void
    {
        $chain = ActionChain::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'name' => 'Lenient Chain',
            'steps' => [
                ['name' => 'Step 1', 'type' => 'http_call', 'url' => 'https://example.com', 'method' => 'POST'],
                ['name' => 'Wait', 'type' => 'delay', 'delay' => '5m'],
                ['name' => 'Step 2', 'type' => 'http_call', 'url' => 'https://example.com', 'method' => 'POST'],
            ],
            'context' => ['steps' => [0 => ['status' => 'executed', 'response' => []]]],
            'status' => ActionChain::STATUS_RUNNING,
            'current_step' => 1,
            'error_handling' => ActionChain::ERROR_SKIP_STEP,
            'started_at' => now()->subMinutes(30),
        ]);

        ActionChain::where('id', $chain->id)->toBase()->update(['updated_at' => now()->subMinutes(10)]);

        ScheduledAction::create([
            'account_id' => $this->user->account_id,
            'created_by_user_id' => $this->user->id,
            'chain_id' => $chain->id,
            'chain_step' => 1,
            'name' => 'Wait',
            'mode' => ScheduledAction::MODE_IMMEDIATE,
            'intent_type' => ScheduledAction::INTENT_WALL_CLOCK,
            'intent_payload' => ['delay' => '5m'],
            'timezone' => 'UTC',
            'resolution_status' => ScheduledAction::STATUS_FAILED,
            'failure_reason' => 'Intent resolution failed: Unknown preset: 5m',
        ]);

        Queue::fake();

        (new DispatcherJob)->handle();

        // Chain should have skipped the failed step and advanced to step 2
        $chain->refresh();
        $this->assertEquals(2, $chain->current_step);
        $this->assertEquals(ActionChain::STATUS_RUNNING, $chain->status);

        // Step 2 action should be created
        $step2Action = ScheduledAction::where('chain_id', $chain->id)->where('chain_step', 2)->first();
        $this->assertNotNull($step2Action);
    }
}
