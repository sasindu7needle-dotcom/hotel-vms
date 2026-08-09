<?php

namespace Tests\Feature;

use App\Models\ExhibitorProfile;
use App\Models\User;
use App\Models\VerifiedVisitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExhibitorRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_the_exhibitor_directory_from_the_exhibitors_subtab(): void
    {
        $admin = User::factory()->create([
            'permissions' => ['Visitors'],
        ]);

        $this->withSession([
            'admin_authenticated' => true,
            'admin_user_id' => $admin->id,
        ])->get(route('admin.exhibitors.directory'))
            ->assertOk()
            ->assertSee('Profiles and members')
            ->assertSee('Exhibitor Directory');
    }

    public function test_admin_can_search_and_select_an_exhibitor_to_view_its_details(): void
    {
        $admin = User::factory()->create(['permissions' => ['Visitors']]);
        $user = User::factory()->create(['username' => 'madar-artica']);
        $exhibitor = ExhibitorProfile::create([
            'user_id' => $user->id,
            'registration_token' => str_repeat('b', 64),
            'company_name' => 'Madar Artica Reda',
            'ngja_file_number' => 'MAR-GEMS',
            'phone_number' => '+94771234567',
            'email' => 'sales@madar.test',
            'name_board' => 'Madar Artica',
            'package' => 'mini',
            'member_limit' => 2,
            'registered_at' => now(),
        ]);

        $this->withSession([
            'admin_authenticated' => true,
            'admin_user_id' => $admin->id,
        ])->get(route('admin.exhibitors.directory', [
            'search' => 'Madar Artica',
            'exhibitor' => $exhibitor->id,
        ]))->assertOk()
            ->assertSee('Madar Artica Reda')
            ->assertSee('MAR-GEMS')
            ->assertSee('No member cards have been issued');
    }

    public function test_admin_can_delete_a_member_from_the_selected_exhibitor_only(): void
    {
        $admin = User::factory()->create(['permissions' => ['Visitors']]);
        $exhibitorUser = User::factory()->create(['username' => 'gem-house']);
        $otherExhibitorUser = User::factory()->create(['username' => 'other-gems']);
        $exhibitor = ExhibitorProfile::create([
            'user_id' => $exhibitorUser->id,
            'registration_token' => str_repeat('c', 64),
            'company_name' => 'Gem House',
        ]);
        $otherExhibitor = ExhibitorProfile::create([
            'user_id' => $otherExhibitorUser->id,
            'registration_token' => str_repeat('d', 64),
            'company_name' => 'Other Gems',
        ]);
        $member = VerifiedVisitor::create([
            'verification_id' => 'delete-exhibitor-member',
            'full_name' => 'Delete Me',
            'exhibitor_profile_id' => $exhibitor->id,
            'payment_status' => 'paid',
        ]);
        $otherMember = VerifiedVisitor::create([
            'verification_id' => 'keep-exhibitor-member',
            'full_name' => 'Keep Me',
            'exhibitor_profile_id' => $otherExhibitor->id,
            'payment_status' => 'paid',
        ]);

        $this->withSession([
            'admin_authenticated' => true,
            'admin_user_id' => $admin->id,
        ])->delete(route('admin.exhibitors.members.destroy', [
            'exhibitorId' => $exhibitor->id,
            'member' => $member,
        ]))->assertRedirect(route('admin.exhibitors.directory', ['exhibitor' => $exhibitor->id]));

        $this->assertDatabaseMissing('verified_visitors', ['id' => $member->id]);
        $this->assertDatabaseHas('verified_visitors', ['id' => $otherMember->id]);
    }

    public function test_admin_can_create_exhibitor_credentials_and_a_unique_registration_profile(): void
    {
        $admin = User::factory()->create([
            'permissions' => ['Visitors'],
        ]);

        $this->withSession([
            'admin_authenticated' => true,
            'admin_user_id' => $admin->id,
        ])->post(route('admin.exhibitors.store'), [
            'username' => 'gem_house',
            'password' => 'temporary-password',
        ])->assertRedirect(route('admin.exhibitors.index'))
            ->assertSessionHas('new_exhibitor_password', 'temporary-password');

        $user = User::where('username', 'gem_house')->firstOrFail();
        $this->assertSame('Exhibitor', $user->role);
        $this->assertTrue(Hash::check('temporary-password', $user->password));
        $this->assertDatabaseHas('exhibitor_profiles', ['user_id' => $user->id]);
    }

    public function test_exhibitor_completes_profile_then_registers_a_member_through_manual_flow(): void
    {
        Storage::fake('local');
        $user = User::factory()->create([
            'username' => 'gem_house',
            'password' => Hash::make('temporary-password'),
            'role' => 'Exhibitor',
            'permissions' => [],
        ]);
        $exhibitor = ExhibitorProfile::create([
            'user_id' => $user->id,
            'registration_token' => str_repeat('a', 64),
        ]);

        $this->get(route('exhibitor.registration.show', $exhibitor))
            ->assertOk()
            ->assertSee('EXHIBITOR PORTAL');

        $this->post(route('exhibitor.login', $exhibitor), [
            'username' => 'gem_house',
            'password' => 'temporary-password',
        ])->assertRedirect(route('exhibitor.registration.show', $exhibitor));

        $this->post(route('exhibitor.registration.store', $exhibitor), [
            'company_name' => 'Gem House',
            'ngja_file_number' => 'NGJA-123',
            'phone_number' => '0771234567',
            'email' => 'sales@gemhouse.test',
            'name_board' => 'Gem House Sri Lanka',
            'package' => 'mini',
        ])->assertRedirect(route('exhibitor.dashboard', $exhibitor));

        $this->assertDatabaseHas('exhibitor_profiles', [
            'id' => $exhibitor->id,
            'company_name' => 'Gem House',
            'package' => 'mini',
            'member_limit' => 2,
        ]);

        $this->get(route('exhibitor.dashboard', $exhibitor))
            ->assertOk()
            ->assertSee('Member administration');

        $this->get(route('visitor.manual.create', ['exhibitor' => $exhibitor->registration_token]))
            ->assertOk()
            ->assertSee('Add exhibitor member')
            ->assertSee('value="Exhibitor"', false);

        $this->post(route('visitor.manual.store'), [
            'exhibitor' => $exhibitor->registration_token,
            'full_name' => 'Member One',
            'document_type' => 'nic',
            'document_number' => '199012345678',
            'mobile_number' => '+94771234567',
            'whatsapp_number' => '',
            'address' => '12 Galle Road, Colombo',
            'occupation' => 'Designer',
            'company' => 'Gem House',
            'entrance_fee' => '0.00',
            'document_front' => UploadedFile::fake()->image('nic-front.jpg'),
            'document_back' => UploadedFile::fake()->image('nic-back.jpg'),
            'face_photo' => UploadedFile::fake()->image('face.jpg'),
        ])->assertRedirect(route('visitor.thank-you'));

        $this->assertDatabaseHas('verified_visitors', [
            'full_name' => 'Member One',
            'category' => 'Exhibitor',
            'exhibitor_profile_id' => $exhibitor->id,
            'entrance_fee' => '0.00',
        ]);

        $member = VerifiedVisitor::where('full_name', 'Member One')->firstOrFail();
        $this->assertSame($exhibitor->id, $member->exhibitor_profile_id);
    }
}
