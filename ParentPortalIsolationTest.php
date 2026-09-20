<?php

namespace Tests\Feature;

use App\Models\AcademicYear;
use App\Models\ClassRoom;
use App\Models\GradeLevel;
use App\Models\Guardian;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The isolation test.
 *
 * This file is the actual guarantee that a parent sees only their own
 * children. The controller expresses the intention; this proves it, and
 * keeps proving it every time someone edits that controller a year from now.
 *
 * Run it before any parent account is created:
 *     php artisan test --filter=ParentPortalIsolationTest
 *
 * If a single assertion here fails, the parent portal is not fit to be
 * switched on. There is no "mostly isolated".
 */
class ParentPortalIsolationTest extends TestCase
{
    use RefreshDatabase;

    private User $sanneh;        // parent of one child
    private Student $ownChild;
    private Student $otherChild; // belongs to a different family

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(\Database\Seeders\RolePermissionSeeder::class);

        $year = AcademicYear::create([
            'name' => '2026/2027', 'start_date' => '2026-09-07',
            'end_date' => '2027-07-16', 'is_current' => true,
        ]);

        $year->terms()->create([
            'name' => 'Term 1', 'sequence' => 1,
            'start_date' => '2026-09-07', 'end_date' => '2026-12-11',
            'is_current' => true,
        ]);

        $level = GradeLevel::create(['name' => 'Grade 4-6', 'sequence' => 4]);
        $class = ClassRoom::create([
            'grade_level_id' => $level->id,
            'academic_year_id' => $year->id,
            'stream' => 'Blue',
        ]);

        // --- family one ---
        $this->sanneh = User::create([
            'name' => 'Mr Sanneh', 'username' => 'sanneh',
            'email' => 'sanneh@example.test', 'password' => 'Secret12345',
            'is_active' => true,
        ]);
        $this->sanneh->assignRole('parent');

        $guardianOne = Guardian::create([
            'user_id' => $this->sanneh->id, 'full_name' => 'Mr Sanneh',
            'relationship' => 'Father', 'phone' => '7000001',
        ]);

        $this->ownChild = Student::create([
            'admission_number' => 'HOA-2026-0001',
            'first_name' => 'Amadou', 'last_name' => 'Sanneh',
            'date_of_birth' => '2015-03-04', 'gender' => 'M',
            'admission_date' => '2026-09-07', 'status' => 'ACTIVE',
        ]);
        $guardianOne->students()->attach($this->ownChild->id, ['is_primary' => true]);
        $this->ownChild->enrollments()->create([
            'class_room_id' => $class->id, 'academic_year_id' => $year->id,
            'date_enrolled' => '2026-09-07',
        ]);

        // --- family two, entirely unrelated ---
        $guardianTwo = Guardian::create([
            'full_name' => 'Mrs Ceesay', 'relationship' => 'Mother',
            'phone' => '7000002',
        ]);

        $this->otherChild = Student::create([
            'admission_number' => 'HOA-2026-0002',
            'first_name' => 'Fatoumata', 'last_name' => 'Ceesay',
            'date_of_birth' => '2015-07-19', 'gender' => 'F',
            'admission_date' => '2026-09-07', 'status' => 'ACTIVE',
        ]);
        $guardianTwo->students()->attach($this->otherChild->id, ['is_primary' => true]);
        $this->otherChild->enrollments()->create([
            'class_room_id' => $class->id, 'academic_year_id' => $year->id,
            'date_enrolled' => '2026-09-07',
        ]);
    }

    /* ------------------------------------------------------------------ */

    public function test_parent_sees_only_their_own_children_in_the_list(): void
    {
        $response = $this->actingAs($this->sanneh)
            ->getJson('/api/parent/children');

        $response->assertOk()
            ->assertJsonCount(1, 'children')
            ->assertJsonPath('children.0.name', 'Amadou Sanneh');

        // The other family's child must not appear anywhere in the payload,
        // by name or by student id.
        $response->assertJsonMissing(['name' => 'Fatoumata Ceesay']);
        $response->assertDontSee('HOA-2026-0002');
    }

    public function test_parent_can_open_their_own_child(): void
    {
        $this->actingAs($this->sanneh)
            ->getJson("/api/parent/children/{$this->ownChild->id}")
            ->assertOk()
            ->assertJsonPath('name', 'Amadou Sanneh');
    }

    /**
     * The one that matters. Guessing another id must fail, and must fail with
     * 404 rather than 403 — a 403 would confirm the record exists.
     */
    public function test_parent_cannot_open_another_familys_child(): void
    {
        $this->actingAs($this->sanneh)
            ->getJson("/api/parent/children/{$this->otherChild->id}")
            ->assertNotFound();
    }

    public function test_every_child_endpoint_refuses_another_familys_child(): void
    {
        $id = $this->otherChild->id;

        // Each of these leaks something different if it is wrong: money,
        // whereabouts, academic record. All four must refuse.
        $endpoints = [
            "/api/parent/children/{$id}",
            "/api/parent/children/{$id}/statement",
            "/api/parent/children/{$id}/attendance",
            "/api/parent/children/{$id}/report-card",
        ];

        foreach ($endpoints as $url) {
            $this->actingAs($this->sanneh)
                ->getJson($url)
                ->assertNotFound("$url should not be reachable");
        }
    }

    public function test_a_nonexistent_id_is_indistinguishable_from_another_familys_child(): void
    {
        $missing = $this->actingAs($this->sanneh)
            ->getJson('/api/parent/children/999999');

        $theirs = $this->actingAs($this->sanneh)
            ->getJson("/api/parent/children/{$this->otherChild->id}");

        // Same status and same body: a parent walking the ids learns nothing
        // about which children exist.
        $this->assertSame($missing->status(), $theirs->status());
        $this->assertSame($missing->json('message'), $theirs->json('message'));
    }

    public function test_a_parent_account_with_no_guardian_record_sees_nothing(): void
    {
        $orphan = User::create([
            'name' => 'Unlinked Parent', 'username' => 'unlinked',
            'email' => 'unlinked@example.test', 'password' => 'Secret12345',
            'is_active' => true,
        ]);
        $orphan->assignRole('parent');

        // A setup mistake, not an attack — but it must fail closed.
        $this->actingAs($orphan)
            ->getJson('/api/parent/children')
            ->assertNotFound();
    }

    public function test_a_signed_out_visitor_gets_nothing(): void
    {
        $this->getJson('/api/parent/children')->assertUnauthorized();
        $this->getJson("/api/parent/children/{$this->ownChild->id}")->assertUnauthorized();
    }

    public function test_a_teacher_cannot_use_the_parent_endpoints(): void
    {
        $teacher = User::create([
            'name' => 'Mrs Jallow', 'username' => 'mjallow',
            'email' => 'jallow@example.test', 'password' => 'Secret12345',
            'is_active' => true,
        ]);
        $teacher->assignRole('teacher');

        // Teachers have their own routes. Letting a teacher through here
        // would hand them a guardian's fee statements as well.
        $this->actingAs($teacher)
            ->getJson('/api/parent/children')
            ->assertForbidden();
    }

    public function test_a_second_guardian_of_the_same_child_also_sees_that_child(): void
    {
        // Two parents, one child: both must see it. Isolation must not be so
        // tight that it locks out the mother.
        $mother = User::create([
            'name' => 'Mrs Sanneh', 'username' => 'msanneh',
            'email' => 'msanneh@example.test', 'password' => 'Secret12345',
            'is_active' => true,
        ]);
        $mother->assignRole('parent');

        $guardian = Guardian::create([
            'user_id' => $mother->id, 'full_name' => 'Mrs Sanneh',
            'relationship' => 'Mother', 'phone' => '7000003',
        ]);
        $guardian->students()->attach($this->ownChild->id);

        $this->actingAs($mother)
            ->getJson('/api/parent/children')
            ->assertOk()
            ->assertJsonCount(1, 'children')
            ->assertJsonPath('children.0.name', 'Amadou Sanneh');
    }

    public function test_siblings_both_appear_for_the_same_guardian(): void
    {
        $sibling = Student::create([
            'admission_number' => 'HOA-2026-0003',
            'first_name' => 'Binta', 'last_name' => 'Sanneh',
            'date_of_birth' => '2017-01-11', 'gender' => 'F',
            'admission_date' => '2026-09-07', 'status' => 'ACTIVE',
        ]);

        Guardian::where('user_id', $this->sanneh->id)
            ->first()
            ->students()
            ->attach($sibling->id);

        $this->actingAs($this->sanneh)
            ->getJson('/api/parent/children')
            ->assertOk()
            ->assertJsonCount(2, 'children');
    }
}
