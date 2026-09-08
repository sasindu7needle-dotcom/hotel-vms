<?php

namespace Tests\Feature;

use App\Models\VerifiedVisitor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TicketRegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['vms.media_disk' => 'local']);
        Storage::fake('local');
    }

    public function test_ticket_registration_page_contains_the_required_fields(): void
    {
        $this->get(route('ticket-registration.create'))
            ->assertOk()
            ->assertSee('Ticket registration')
            ->assertSee('name="name"', false)
            ->assertSee('name="designation"', false)
            ->assertSee('name="company"', false)
            ->assertSee('name="whatsapp_number"', false)
            ->assertSee('name="email"', false)
            ->assertSee('name="ticket_number"', false)
            ->assertSee('name="profile_image"', false);
    }

    public function test_ticket_holder_is_registered_and_can_view_their_card(): void
    {
        $response = $this->post(route('ticket-registration.store'), $this->validRegistration());

        $visitor = VerifiedVisitor::sole();
        $response->assertRedirect(route('visitor.thank-you'));
        $this->assertSame('HIF-2026-00125', $visitor->ticket_number);
        $this->assertSame('Kasun Perera', $visitor->full_name);
        $this->assertSame('Director', $visitor->occupation);
        $this->assertSame('+94771234567', $visitor->whatsapp_number);
        $this->assertSame('Ticket Holder', $visitor->category);
        $this->assertSame('pending', $visitor->payment_status);
        $this->assertSame('payment_pending', $visitor->registration_status);
        $this->assertNull($visitor->paid_at);
        $this->assertSame('ticket_registration', $visitor->ocr_provider);
        Storage::disk('local')->assertExists($visitor->selfie_path);

        $this->get(route('visitor.thank-you'))
            ->assertOk()
            ->assertSee('HIF-2026-00125');
    }

    public function test_a_ticket_number_cannot_be_registered_twice(): void
    {
        VerifiedVisitor::create([
            'verification_id' => fake()->uuid(),
            'ticket_number' => 'HIF-2026-00125',
        ]);

        $this->from(route('ticket-registration.create'))
            ->post(route('ticket-registration.store'), $this->validRegistration())
            ->assertRedirect(route('ticket-registration.create'))
            ->assertSessionHasErrors('ticket_number');

        $this->assertDatabaseCount('verified_visitors', 1);
    }

    private function validRegistration(): array
    {
        return [
            'name' => 'Kasun Perera',
            'designation' => 'Director',
            'company' => 'HIF Sri Lanka',
            'whatsapp_number' => '0771234567',
            'email' => 'kasun@example.com',
            'ticket_number' => 'hif-2026-00125',
            'profile_image' => UploadedFile::fake()->image('profile.jpg', 600, 800),
        ];
    }
}
