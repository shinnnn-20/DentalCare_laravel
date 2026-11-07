<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StaffAccountBranchAccessTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('branchUsers')]
    public function test_doctors_can_only_manage_staff_accounts_in_their_branch(
        string $role,
        string $branchSlug,
        string $otherBranchSlug,
    ): void {
        $branch = $this->branch($branchSlug);
        $otherBranch = $this->branch($otherBranchSlug);
        $actor = User::factory()->create([
            'name' => 'Branch Manager',
            'role' => $role,
            'branch_id' => $branch->id,
        ]);
        $sameBranchAccount = User::factory()->create([
            'name' => 'Same Branch Assistant',
            'role' => 'staff',
            'branch_id' => $branch->id,
        ]);
        $otherBranchAccount = User::factory()->create([
            'name' => 'Other Branch Doctor',
            'role' => 'doctor',
            'branch_id' => $otherBranch->id,
        ]);

        $this->actingAs($actor)
            ->get(route('admin.staff.index', ['branch_id' => $otherBranch->id]))
            ->assertOk()
            ->assertSee('Branch Manager')
            ->assertSee('Same Branch Assistant')
            ->assertSee($branch->name)
            ->assertDontSee('Other Branch Doctor')
            ->assertDontSee($otherBranch->name)
            ->assertSee($role === 'doctor' ? 'Create clinic account' : 'Branch account management')
            ->assertDontSee('Assigned branch for Branch Manager');

        $this->patch(route('admin.staff.toggle', $sameBranchAccount))
            ->assertRedirect(route('admin.staff.index', ['branch_id' => $otherBranch->id]))
            ->assertSessionHas('status', 'Staff account status updated.');
        $this->assertFalse($sameBranchAccount->fresh()->is_active);

        $this->put(route('admin.staff.password', $sameBranchAccount), [
            'password' => 'Branch-reset-password!',
            'password_confirmation' => 'Branch-reset-password!',
            'branch_id' => $otherBranch->id,
        ])->assertSessionHas('status', 'Staff password reset.');

        $this->assertTrue(Hash::check('Branch-reset-password!', $sameBranchAccount->fresh()->password));
        $this->assertSame($branch->id, (int) $sameBranchAccount->fresh()->branch_id);

        $this->patch(route('admin.staff.toggle', $otherBranchAccount))->assertNotFound();
        $this->put(route('admin.staff.password', $otherBranchAccount), [
            'password' => 'Cross-branch-password!',
            'password_confirmation' => 'Cross-branch-password!',
            'branch_id' => $branch->id,
        ])->assertNotFound();
        $this->patch(route('admin.staff.branch.update', $otherBranchAccount), [
            'branch_id' => $branch->id,
        ])->assertForbidden();
        $this->patch(route('admin.staff.toggle', $actor))->assertNotFound();
        $this->put(route('admin.staff.password', $actor), [
            'password' => 'Self-reset-password!',
            'password_confirmation' => 'Self-reset-password!',
        ])->assertNotFound();

        $this->assertTrue($otherBranchAccount->fresh()->is_active);
        $this->assertTrue(Hash::check('password', $otherBranchAccount->fresh()->password));
        $this->assertSame($otherBranch->id, (int) $otherBranchAccount->fresh()->branch_id);
    }

    public function test_admin_can_view_and_manage_accounts_across_branches(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $firstBranchAccount = User::factory()->create([
            'name' => 'General Trias Staff',
            'role' => 'staff',
            'branch_id' => $this->branch('general-trias')->id,
        ]);
        $secondBranchAccount = User::factory()->create([
            'name' => 'Dasmarinas Doctor',
            'role' => 'doctor',
            'branch_id' => $this->branch('dasmarinas')->id,
        ]);
        $thirdBranchAccount = User::factory()->create([
            'name' => 'Trece Staff',
            'role' => 'staff',
            'branch_id' => $this->branch('trece')->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.staff.index'))
            ->assertOk()
            ->assertSee('Create clinic account')
            ->assertSee('<option value="doctor"', false)
            ->assertSee('General Trias Staff')
            ->assertSee('Dasmarinas Doctor')
            ->assertSee('Trece Staff');
        $this->assertSame(8, substr_count($response->getContent(), 'data-password-toggle'));

        $this->patch(route('admin.staff.toggle', $thirdBranchAccount))
            ->assertSessionHas('status', 'Staff account status updated.');

        $this->put(route('admin.staff.password', $secondBranchAccount), [
            'password' => 'Admin-reset-password!',
            'password_confirmation' => 'Admin-reset-password!',
        ])->assertSessionHas('status', 'Doctor password reset.');

        $this->assertFalse($thirdBranchAccount->fresh()->is_active);
        $this->assertTrue(Hash::check('Admin-reset-password!', $secondBranchAccount->fresh()->password));
        $this->assertSame(
            $this->branch('general-trias')->id,
            (int) $firstBranchAccount->fresh()->branch_id,
        );
    }

    public function test_doctor_cannot_manage_another_doctor_account_but_can_manage_staff_in_the_same_branch(): void
    {
        $branch = $this->branch('dasmarinas');
        $doctor = User::factory()->create([
            'name' => 'Branch Doctor',
            'role' => 'doctor',
            'branch_id' => $branch->id,
        ]);
        $otherDoctor = User::factory()->create([
            'name' => 'Other Branch Doctor',
            'role' => 'doctor',
            'branch_id' => $branch->id,
        ]);
        $staff = User::factory()->create([
            'name' => 'Branch Staff',
            'role' => 'staff',
            'branch_id' => $branch->id,
        ]);
        $crossBranchDoctor = User::factory()->create([
            'role' => 'doctor',
            'branch_id' => $this->branch('trece')->id,
        ]);

        $this->actingAs($doctor)
            ->get(route('admin.staff.index'))
            ->assertSee('Branch Staff')
            ->assertSee('Edit account')
            ->assertSee('Your account')
            ->assertSee("Another doctor's account (admin only)")
            ->assertDontSee(route('admin.staff.update', $otherDoctor))
            ->assertDontSee(route('admin.staff.password', $otherDoctor));

        $this->patch(route('admin.staff.update', $staff), [
            'name' => 'Updated Staff',
            'email' => 'updated.staff@example.test',
            'phone' => '09171234567',
        ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Staff account updated.');

        $staff->refresh();
        $this->assertSame('Updated Staff', $staff->name);
        $this->assertSame('updated.staff@example.test', $staff->email);
        $this->assertSame('09171234567', $staff->phone);
        $this->assertSame('staff', $staff->role);
        $this->assertSame($branch->id, (int) $staff->branch_id);

        $this->patch(route('admin.staff.update', $otherDoctor), [
            'name' => 'Unauthorized Doctor Change',
            'email' => 'unauthorized.doctor@example.test',
        ])->assertNotFound();

        $this->patch(route('admin.staff.toggle', $otherDoctor))->assertNotFound();
        $this->put(route('admin.staff.password', $otherDoctor), [
            'password' => 'Doctor-account-reset!',
            'password_confirmation' => 'Doctor-account-reset!',
        ])->assertNotFound();

        $this->patch(route('admin.staff.update', $crossBranchDoctor), [
            'name' => 'Unauthorized Change',
            'email' => 'unauthorized.change@example.test',
        ])->assertNotFound();

        $this->assertSame('Other Branch Doctor', $otherDoctor->fresh()->name);
        $this->assertSame('doctor', $otherDoctor->fresh()->role);
        $this->assertSame($branch->id, (int) $otherDoctor->fresh()->branch_id);
        $this->assertTrue($otherDoctor->fresh()->is_active);
        $this->assertTrue(Hash::check('password', $otherDoctor->fresh()->password));
    }

    public function test_admin_created_doctor_can_log_in_and_use_the_doctor_portal(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $dasmarinas = $this->branch('dasmarinas');

        $this->actingAs($admin)
            ->post(route('admin.staff.store'), [
                'name' => 'Dasmarinas Dentist',
                'email' => 'dasmarinas.dentist@example.test',
                'role' => 'doctor',
                'branch_id' => $dasmarinas->id,
                'password' => 'Temporary-doctor-password!',
                'password_confirmation' => 'Temporary-doctor-password!',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Doctor account created.');

        $doctor = User::query()->where('email', 'dasmarinas.dentist@example.test')->firstOrFail();
        $this->assertSame('doctor', $doctor->role);
        $this->assertSame($dasmarinas->id, (int) $doctor->branch_id);

        $this->post(route('logout'))
            ->assertRedirect(route('login'));

        $this->post(route('login.store'), [
            'email' => 'dasmarinas.dentist@example.test',
            'password' => 'Temporary-doctor-password!',
        ])->assertRedirect(route('dashboard'));

        $this->get(route('dashboard'))->assertOk();
        $this->get(route('clinic.availability.index'))
            ->assertOk()
            ->assertSee('Appointment availability');
        $this->get(route('admin.staff.index'))
            ->assertOk()
            ->assertSee('Dasmarinas Dentist')
            ->assertSee('Create clinic account');
    }

    public function test_doctor_can_create_clinic_accounts_only_for_their_assigned_branch(): void
    {
        $branch = $this->branch('dasmarinas');
        $otherBranch = $this->branch('trece');
        $doctor = User::factory()->create([
            'role' => 'doctor',
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($doctor)
            ->post(route('admin.staff.store'), [
                'name' => 'Branch Assistant',
                'email' => 'branch.assistant@example.test',
                'role' => 'staff',
                'password' => 'Temporary-account-password!',
                'password_confirmation' => 'Temporary-account-password!',
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Staff account created.');

        $account = User::query()->where('email', 'branch.assistant@example.test')->firstOrFail();
        $this->assertSame($branch->id, (int) $account->branch_id);

        $this->get(route('admin.staff.index'))
            ->assertSee('<option value="staff"', false)
            ->assertDontSee('<option value="doctor"', false);

        $this->post(route('admin.staff.store'), [
            'name' => 'Unauthorized Doctor',
            'email' => 'unauthorized.doctor@example.test',
            'role' => 'doctor',
            'password' => 'Temporary-account-password!',
            'password_confirmation' => 'Temporary-account-password!',
        ])->assertSessionHasErrors('role');

        $this->assertDatabaseMissing('users', ['email' => 'unauthorized.doctor@example.test']);

        $this->post(route('admin.staff.store'), [
            'name' => 'Cross-branch Assistant',
            'email' => 'cross.branch.assistant@example.test',
            'role' => 'staff',
            'branch_id' => $otherBranch->id,
            'password' => 'Temporary-account-password!',
            'password_confirmation' => 'Temporary-account-password!',
        ])->assertSessionHasErrors('branch_id');

        $this->assertDatabaseMissing('users', ['email' => 'cross.branch.assistant@example.test']);
    }

    public function test_branch_users_cannot_create_accounts_or_access_account_management_without_a_branch(): void
    {
        $branch = $this->branch('trece');
        $staff = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branch->id,
        ]);
        $unassignedDoctor = User::factory()->create(['role' => 'doctor']);

        $this->actingAs($staff)
            ->post(route('admin.staff.store'), [
                'name' => 'Unauthorized Account',
                'email' => 'unauthorized.account@example.test',
                'role' => 'doctor',
                'branch_id' => $this->branch('general-trias')->id,
                'password' => 'Temporary-doctor-password!',
                'password_confirmation' => 'Temporary-doctor-password!',
            ])
            ->assertForbidden();

        $this->actingAs($unassignedDoctor)
            ->get(route('admin.staff.index'))
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'unauthorized.account@example.test']);
    }

    public function test_patients_cannot_access_staff_account_management(): void
    {
        $patient = User::factory()->create(['role' => 'patient']);

        $this->actingAs($patient)
            ->get(route('admin.staff.index'))
            ->assertForbidden();
    }

    public function test_staff_portal_hides_and_denies_staff_account_management(): void
    {
        $branch = $this->branch('general-trias');
        $staff = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branch->id,
        ]);
        $doctor = User::factory()->create([
            'name' => 'Branch Doctor',
            'role' => 'doctor',
            'branch_id' => $branch->id,
        ]);
        $branchStaff = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branch->id,
        ]);

        $this->actingAs($staff)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee(route('admin.staff.index'))
            ->assertDontSee('Staff &amp; doctor accounts');

        $this->get(route('admin.staff.index'))->assertForbidden();
        $this->patch(route('admin.staff.toggle', $branchStaff))->assertForbidden();
        $this->patch(route('admin.staff.update', $doctor), [
            'name' => 'Unauthorized edit',
            'email' => 'unauthorized.edit@example.test',
        ])->assertForbidden();
        $this->put(route('admin.staff.password', $doctor), [
            'password' => 'Unauthorized-password!',
            'password_confirmation' => 'Unauthorized-password!',
        ])->assertForbidden();

        $this->assertSame('doctor', $doctor->fresh()->role);
        $this->assertSame('Branch Doctor', $doctor->fresh()->name);
        $this->assertTrue($doctor->fresh()->is_active);
        $this->assertTrue(Hash::check('password', $doctor->fresh()->password));
    }

    public static function branchUsers(): array
    {
        return [
            'doctor at Dasmarinas' => ['doctor', 'dasmarinas', 'general-trias'],
        ];
    }

    private function branch(string $slug): Branch
    {
        return Branch::query()->where('slug', $slug)->firstOrFail();
    }
}
