<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BranchScopedAccessTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('branchlessClinicRoles')]
    public function test_branchless_staff_and_doctors_are_forbidden_from_clinic_access(string $role): void
    {
        $user = User::factory()->create([
            'role' => $role,
            'branch_id' => null,
        ]);

        $this->actingAs($user)
            ->get(route('appointments.index'))
            ->assertForbidden();
    }

    public function test_admin_without_a_branch_remains_allowed(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'branch_id' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public static function branchlessClinicRoles(): array
    {
        return [
            'staff' => ['staff'],
            'doctor' => ['doctor'],
        ];
    }
}
