<?php

namespace Database\Seeders;

use App\Models\Project;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Services\SpecificationVersionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Local-only mock data for manual testing: two users who share a project,
 * two specifications that have been through multiple versions (by alternating
 * authors) and threaded comments.
 *
 * Run with:  php artisan db:seed --class=DevSeeder
 *
 * Guarded to the local environment and idempotent — safe to re-run.
 */
class DevSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('DevSeeder only runs in the local environment. Skipping.');

            return;
        }

        if (User::where('email', 'ana@reqflow.test')->exists()) {
            $this->command?->warn('DevSeeder data already present. Skipping.');

            return;
        }

        $versions = app(SpecificationVersionService::class);

        DB::transaction(function () use ($versions) {
            // --- Two users who will share a project -------------------------
            $ana = User::factory()->create([
                'name' => 'Ana Petrova',
                'email' => 'ana@reqflow.test',
                'email_verified_at' => now(),
            ]);

            $boris = User::factory()->create([
                'name' => 'Boris Ilievski',
                'email' => 'boris@reqflow.test',
                'email_verified_at' => now(),
            ]);

            // --- Shared team (Ana owns, Boris is a member) ------------------
            $team = Team::create(['name' => 'Product Squad', 'created_by' => $ana->id]);
            $team->teamMembers()->create(['user_id' => $ana->id, 'role' => TeamMember::ROLE_OWNER]);
            $team->teamMembers()->create(['user_id' => $boris->id, 'role' => TeamMember::ROLE_MEMBER]);

            $project = Project::create([
                'team_id' => $team->id,
                'name' => 'Mobile Banking App',
                'description' => 'Requirements for the new mobile banking application.',
                'status' => Project::STATUS_IN_PROGRESS,
                'created_by' => $ana->id,
            ]);

            // --- Spec A: three versions by alternating authors --------------
            $specA = $project->specifications()->create([
                'title' => 'User Authentication',
                'description' => 'How customers sign in to the mobile app.',
                'goals' => 'Let customers access their accounts quickly and securely.',
                'scope' => 'Covers email/password sign-in. Biometric login is a separate spec.',
                'functional_requirements' => "- Users can sign in with email and password.\n- Failed attempts are rate-limited.",
                'non_functional_requirements' => '- Sign-in must complete within two seconds.',
                'current_version' => 1,
                'created_by' => $ana->id,
            ]);
            $versions->recordInitialVersion($specA, $ana); // v1

            // v2 — Boris expands scope + functional requirements
            $orig = $versions->snapshot($specA);
            $specA->update([
                'scope' => 'Covers email/password and biometric (Face ID / fingerprint) sign-in.',
                'functional_requirements' => "- Users can sign in with email and password.\n- Users can enable biometric sign-in after first login.\n- Failed attempts are rate-limited and lock the account after five tries.",
            ]);
            $versions->recordVersionIfChanged($specA, $orig, $boris);

            // v3 — Ana tightens the non-functional requirements
            $orig = $versions->snapshot($specA);
            $specA->update([
                'non_functional_requirements' => "- Sign-in must complete within two seconds on a 4G connection.\n- All credentials are transmitted over TLS 1.2+.\n- The feature meets WCAG 2.1 AA accessibility guidelines.",
            ]);
            $versions->recordVersionIfChanged($specA, $orig, $ana);

            // Threaded comments on Spec A
            $top = $specA->comments()->create([
                'user_id' => $ana->id,
                'body' => 'Should we support social login (Google/Apple) here, or keep that separate?',
            ]);
            $reply = $specA->comments()->create([
                'user_id' => $boris->id,
                'body' => "Let's keep it separate — this spec is already covering biometric now.",
                'parent_id' => $top->id,
            ]);
            $specA->comments()->create([
                'user_id' => $ana->id,
                'body' => 'Agreed, I\'ll open a dedicated spec for social login.',
                'parent_id' => $reply->id,
            ]);
            $specA->comments()->create([
                'user_id' => $boris->id,
                'body' => 'One more thing: can we clarify the account-lockout duration in v3?',
            ]);

            // --- Spec B: two versions -------------------------------------
            $specB = $project->specifications()->create([
                'title' => 'Payments & Transfers',
                'description' => 'Moving money between accounts and to third parties.',
                'goals' => 'Enable fast, safe transfers from the mobile app.',
                'scope' => 'Domestic transfers only. International transfers are out of scope for now.',
                'functional_requirements' => "- Users can transfer money between their own accounts.\n- Users can send money to a saved payee.",
                'non_functional_requirements' => '- Transfers must be confirmed with a second factor.',
                'current_version' => 1,
                'created_by' => $boris->id,
            ]);
            $versions->recordInitialVersion($specB, $boris); // v1

            // v2 — Ana adds scheduled transfers
            $orig = $versions->snapshot($specB);
            $specB->update([
                'functional_requirements' => "- Users can transfer money between their own accounts.\n- Users can send money to a saved payee.\n- Users can schedule a transfer for a future date.",
            ]);
            $versions->recordVersionIfChanged($specB, $orig, $ana);

            $topB = $specB->comments()->create([
                'user_id' => $ana->id,
                'body' => 'Do scheduled transfers need their own confirmation on the send date?',
            ]);
            $specB->comments()->create([
                'user_id' => $boris->id,
                'body' => 'Good question — I think a notification is enough, no second factor needed.',
                'parent_id' => $topB->id,
            ]);
        });

        $this->command?->info('DevSeeder complete: ana@reqflow.test / boris@reqflow.test (password: "password").');
    }
}
