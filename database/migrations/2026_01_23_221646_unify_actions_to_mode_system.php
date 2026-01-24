<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration unifies webhooks and reminders into a mode-based system:
     * - mode: 'immediate' (was type='http') or 'gated' (was type='reminder')
     * - gate: JSON containing message, recipients, channels, timeout, on_timeout, max_snoozes
     * - request: JSON containing url, method, headers, body (renamed from http_request)
     */
    public function up(): void
    {
        // Step 1: Add new columns
        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->string('mode', 20)->default('immediate')->after('type');
            $table->json('gate')->nullable()->after('escalation_rules');
            $table->timestamp('gate_passed_at')->nullable()->after('executed_at_utc');
            $table->json('request')->nullable()->after('http_request');
        });

        // Step 2: Migrate existing data
        // Convert type='http' to mode='immediate'
        DB::table('scheduled_actions')
            ->where('type', 'http')
            ->update(['mode' => 'immediate']);

        // Convert type='reminder' to mode='gated' and build gate JSON
        DB::table('scheduled_actions')
            ->where('type', 'reminder')
            ->update(['mode' => 'gated']);

        // Build gate JSON for reminders (must do row by row for JSON construction)
        $reminders = DB::table('scheduled_actions')
            ->where('mode', 'gated')
            ->get(['id', 'message', 'escalation_rules', 'confirmation_mode', 'max_snoozes']);

        foreach ($reminders as $reminder) {
            $escalation = $reminder->escalation_rules ? json_decode($reminder->escalation_rules, true) : [];

            $gate = [
                'message' => $reminder->message,
                'recipients' => $escalation['recipients'] ?? [],
                'channels' => $escalation['channels'] ?? ['email'],
                'timeout' => isset($escalation['token_expiry_days'])
                    ? $escalation['token_expiry_days'].'d'
                    : '7d',
                'on_timeout' => 'expire',
                'max_snoozes' => $reminder->max_snoozes ?? 5,
                'confirmation_mode' => $reminder->confirmation_mode ?? 'first_response',
            ];

            // Add escalation settings if present
            if (! empty($escalation['escalate_after_hours']) || ! empty($escalation['escalation_contacts'])) {
                $gate['escalation'] = [
                    'after_hours' => $escalation['escalate_after_hours'] ?? null,
                    'contacts' => $escalation['escalation_contacts'] ?? [],
                ];
            }

            DB::table('scheduled_actions')
                ->where('id', $reminder->id)
                ->update(['gate' => json_encode($gate)]);
        }

        // Copy http_request to request
        DB::table('scheduled_actions')
            ->whereNotNull('http_request')
            ->update(['request' => DB::raw('http_request')]);

        // Step 3: Drop old columns
        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->dropColumn([
                'type',
                'message',
                'escalation_rules',
                'confirmation_mode',
                'max_snoozes',
                'http_request',
            ]);
        });

        // Step 4: Add index for mode-based queries
        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->index(['mode', 'resolution_status', 'execute_at_utc'], 'idx_mode_dispatch');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Re-add old columns
        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->dropIndex('idx_mode_dispatch');
        });

        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->string('type')->nullable()->after('description');
            $table->text('message')->nullable()->after('retry_strategy');
            $table->string('confirmation_mode')->nullable()->after('message');
            $table->json('escalation_rules')->nullable()->after('confirmation_mode');
            $table->unsignedInteger('max_snoozes')->default(5)->after('snooze_count');
            $table->json('http_request')->nullable()->after('failure_reason');
        });

        // Step 2: Migrate data back
        // mode='immediate' -> type='http'
        DB::table('scheduled_actions')
            ->where('mode', 'immediate')
            ->update(['type' => 'http']);

        // mode='gated' -> type='reminder'
        DB::table('scheduled_actions')
            ->where('mode', 'gated')
            ->update(['type' => 'reminder']);

        // Restore http_request from request
        DB::table('scheduled_actions')
            ->whereNotNull('request')
            ->update(['http_request' => DB::raw('request')]);

        // Restore reminder fields from gate JSON
        $gatedActions = DB::table('scheduled_actions')
            ->where('mode', 'gated')
            ->whereNotNull('gate')
            ->get(['id', 'gate']);

        foreach ($gatedActions as $action) {
            $gate = json_decode($action->gate, true);

            $escalationRules = [
                'recipients' => $gate['recipients'] ?? [],
                'channels' => $gate['channels'] ?? ['email'],
            ];

            if (isset($gate['timeout'])) {
                // Parse timeout like "7d" or "4h" back to token_expiry_days
                preg_match('/(\d+)([dhw])/', $gate['timeout'], $matches);
                if ($matches) {
                    $value = (int) $matches[1];
                    $unit = $matches[2];
                    $days = match ($unit) {
                        'h' => ceil($value / 24),
                        'w' => $value * 7,
                        default => $value,
                    };
                    $escalationRules['token_expiry_days'] = $days;
                }
            }

            if (isset($gate['escalation'])) {
                $escalationRules['escalate_after_hours'] = $gate['escalation']['after_hours'] ?? null;
                $escalationRules['escalation_contacts'] = $gate['escalation']['contacts'] ?? [];
            }

            DB::table('scheduled_actions')
                ->where('id', $action->id)
                ->update([
                    'message' => $gate['message'] ?? null,
                    'confirmation_mode' => $gate['confirmation_mode'] ?? 'first_response',
                    'escalation_rules' => json_encode($escalationRules),
                    'max_snoozes' => $gate['max_snoozes'] ?? 5,
                ]);
        }

        // Step 3: Make type required and drop new columns
        DB::table('scheduled_actions')
            ->whereNull('type')
            ->update(['type' => 'http']);

        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->dropColumn(['mode', 'gate', 'gate_passed_at', 'request']);
        });

        Schema::table('scheduled_actions', function (Blueprint $table) {
            $table->string('type')->nullable(false)->change();
        });
    }
};
