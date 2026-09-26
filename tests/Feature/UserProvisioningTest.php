<?php

namespace Tests\Feature;

use App\Enums\SignupSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserProvisioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_provisioned_account_is_stored_under_a_normalised_address(): void
    {
        $user = User::provision('  Keegi.Uus@Naide.EE ', SignupSource::TeamMember);

        $this->assertTrue($user->wasRecentlyCreated);
        $this->assertSame('keegi.uus@naide.ee', $user->fresh()->email);
        $this->assertSame(SignupSource::TeamMember, $user->fresh()->signup_source);
    }

    public function test_an_unnamed_account_is_named_after_its_address(): void
    {
        $this->assertSame('keegi', User::provision('keegi@naide.ee', SignupSource::AdminCreated)->name);
        $this->assertSame('Keegi Teine', User::provision('teine@naide.ee', SignupSource::CsvImport, 'Keegi Teine')->name);
        $this->assertSame('kolmas', User::provision('kolmas@naide.ee', SignupSource::AuthentikSso, '')->name);
    }

    public function test_a_provisioned_account_is_unverified_unless_the_caller_vouches_for_it(): void
    {
        $this->assertNull(User::provision('keegi@naide.ee', SignupSource::AdminCreated)->fresh()->email_verified_at);
        $this->assertNotNull(User::provision('teine@naide.ee', SignupSource::CsvImport, verified: true)->fresh()->email_verified_at);
    }

    public function test_a_provisioned_account_has_a_password_nobody_knows(): void
    {
        $user = User::provision('keegi@naide.ee', SignupSource::AdminCreated)->fresh();

        $this->assertNotEmpty($user->password);
        $this->assertTrue(Hash::isHashed($user->password));
    }

    public function test_every_write_of_an_address_is_normalised(): void
    {
        $user = User::factory()->create(['email' => ' Suur.Taht@Naide.ee']);

        $this->assertSame('suur.taht@naide.ee', $user->fresh()->email);

        $user->update(['email' => 'UUS@NAIDE.EE']);

        $this->assertSame('uus@naide.ee', $user->fresh()->email);
    }
}
