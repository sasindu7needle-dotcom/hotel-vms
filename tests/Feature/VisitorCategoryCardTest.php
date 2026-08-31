<?php

namespace Tests\Feature;

use App\Mail\PaymentConfirmationMail;
use App\Models\VerifiedVisitor;
use App\Models\VisitorCategory;
use App\Services\PaymentConfirmationEmailService;
use App\Services\VisitorCategoryCardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class VisitorCategoryCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_admin_can_upload_preview_replace_and_remove_category_card_artwork(): void
    {
        $session = ['admin_authenticated' => true, 'admin_username' => 'admin'];

        $this->withSession($session)
            ->post(route('admin.configurations.categories.store'), [
                'name' => 'VIP Guest',
                'entrance_fee' => '2500.00',
                'badge_color' => '#c8e063',
                'is_active' => '1',
                'card_image' => UploadedFile::fake()->image('vip-front.png', 680, 1190),
                'access_schedule' => [[
                    'date' => '',
                    'from' => '',
                    'to' => '',
                ]],
            ])
            ->assertRedirect();

        $category = VisitorCategory::firstOrFail();
        $firstPath = $category->card_image_path;
        $this->assertSame('vip-front.png', $category->card_image_name);
        $this->assertSame('image/png', $category->card_image_mime);
        Storage::disk('public')->assertExists($firstPath);

        $this->withSession($session)
            ->get(route('admin.configurations.categories.index', ['category' => $category->id]))
            ->assertOk()
            ->assertSee('Upload card artwork')
            ->assertSee('vip-front.png')
            ->assertSee('Custom artwork');

        $this->withSession($session)
            ->get(route('admin.configurations.categories.card_image', $category))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $this->withSession($session)
            ->post(route('admin.configurations.categories.card_image.update', $category), [
                'card_image' => UploadedFile::fake()->image('vip-revised.jpg', 800, 1400),
            ])
            ->assertRedirect(route('admin.configurations.categories.index', ['category' => $category->id]));

        $category->refresh();
        $secondPath = $category->card_image_path;
        Storage::disk('public')->assertMissing($firstPath);
        Storage::disk('public')->assertExists($secondPath);
        $this->assertSame('vip-revised.jpg', $category->card_image_name);

        $this->withSession($session)
            ->delete(route('admin.configurations.categories.card_image.destroy', $category))
            ->assertRedirect();

        Storage::disk('public')->assertMissing($secondPath);
        $this->assertNull($category->fresh()->card_image_path);
    }

    public function test_category_form_allows_an_empty_schedule_but_rejects_a_partial_window(): void
    {
        $session = ['admin_authenticated' => true, 'admin_username' => 'admin'];

        $this->withSession($session)
            ->get(route('admin.configurations.categories.index'))
            ->assertOk()
            ->assertDontSee('name="access_schedule[0][date]" value="" required', false)
            ->assertDontSee('name="access_schedule[0][from]" value="" required', false)
            ->assertDontSee('name="access_schedule[0][to]" value="" required', false);

        $this->withSession($session)
            ->post(route('admin.configurations.categories.store'), [
                'name' => 'Partial Schedule',
                'entrance_fee' => '0.00',
                'badge_color' => '#c8e063',
                'card_image' => UploadedFile::fake()->image('partial.png', 680, 1190),
                'access_schedule' => [[
                    'date' => '2026-09-01',
                    'from' => '',
                    'to' => '',
                ]],
            ])
            ->assertSessionHasErrors(['access_schedule.0.from', 'access_schedule.0.to']);

        $this->assertDatabaseMissing('visitor_categories', ['name' => 'Partial Schedule']);
    }

    public function test_editing_page_has_an_explicit_new_category_action(): void
    {
        $category = VisitorCategory::create([
            'name' => 'Existing Category',
            'code' => 'existing-category',
            'badge_color' => '#c8e063',
            'entrance_fee' => 0,
            'is_active' => true,
        ]);

        $this->withSession(['admin_authenticated' => true, 'admin_username' => 'admin'])
            ->get(route('admin.configurations.categories.index', ['category' => $category->id]))
            ->assertOk()
            ->assertSee('+ New Category')
            ->assertSee('href="'.route('admin.configurations.categories.index').'"', false)
            ->assertSee('Update Category');

        $this->withSession(['admin_authenticated' => true, 'admin_username' => 'admin'])
            ->get(route('admin.configurations.categories.index'))
            ->assertOk()
            ->assertSee('Create Visitor Category')
            ->assertSee('Submit Category');
    }

    public function test_category_card_is_identical_for_visitor_download_admin_download_and_email(): void
    {
        Mail::fake();
        $category = VisitorCategory::create([
            'name' => 'Sponsor',
            'code' => 'sponsor',
            'badge_color' => '#8b5cf6',
            'entrance_fee' => 5000,
            'is_active' => true,
        ]);
        app(VisitorCategoryCardService::class)->replace(
            $category,
            UploadedFile::fake()->image('sponsor-card.png', 680, 1190),
        );
        $visitor = VerifiedVisitor::create([
            'verification_id' => (string) Str::uuid(),
            'visitor_category_id' => $category->id,
            'full_name' => 'Category Card Visitor',
            'email' => 'category-card@example.test',
            'category' => 'Sponsor',
            'occupation' => 'Director',
            'company' => 'Example Sponsor',
            'entrance_fee' => 5000,
            'payment_method' => 'visa_master',
            'payment_status' => 'paid',
            'registration_status' => 'registered',
            'paid_at' => now(),
        ]);
        $legacyVisitor = VerifiedVisitor::create([
            'verification_id' => (string) Str::uuid(),
            'full_name' => 'Legacy Sponsor',
            'category' => 'Sponsor',
            'payment_status' => 'paid',
            'registration_status' => 'registered',
        ]);
        $this->assertTrue($category->is(app(VisitorCategoryCardService::class)->categoryFor($legacyVisitor)));
        $registration = [
            'record_id' => $visitor->id,
            'full_name' => $visitor->full_name,
            'payment_method' => 'visa_master',
            'payment_status' => 'paid',
        ];

        $this->withSession(['visitor_registration' => $registration])
            ->get(route('visitor.thank-you'))
            ->assertOk()
            ->assertSee('category-entrance-card-preview')
            ->assertSee(route('visitor.card.preview'))
            ->assertDontSee('badge-topbar');
        $this->get(route('visitor.card.artwork'))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');

        $visitorDownload = $this->withSession(['visitor_registration' => $registration])
            ->get(route('visitor.card.download'))
            ->assertOk();
        $customCard = $visitorDownload->getContent();

        $adminSession = [
            'admin_authenticated' => true,
            'admin_username' => 'admin',
            'admin_permissions' => ['Visitors'],
        ];
        $this->withSession($adminSession)
            ->get(route('admin.visitors.card_artwork', $visitor))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->withSession($adminSession)
            ->get(route('admin.visitors.badge', $visitor))
            ->assertOk()
            ->assertSee('generated-card-preview')
            ->assertSee(route('admin.visitors.card.preview', $visitor))
            ->assertDontSee('<div class="card-topbar">', false);
        $this->withSession($adminSession)
            ->get(route('admin.visitors.card.preview', $visitor))
            ->assertOk()
            ->assertHeader('Content-Disposition', 'inline; filename="category-card-visitor-entrance-card.png"');
        $adminDownload = $this->withSession($adminSession)
            ->get(route('admin.visitors.card.download', $visitor))
            ->assertOk()
            ->assertDownload('category-card-visitor-entrance-card.png');
        $this->assertSame($customCard, $adminDownload->getContent());

        $this->assertTrue(app(PaymentConfirmationEmailService::class)->sendIfNeeded($visitor));
        Mail::assertSent(PaymentConfirmationMail::class, function (PaymentConfirmationMail $mail) use ($customCard) {
            $attachment = collect($mail->attachments())
                ->first(fn ($attachment) => $attachment->mime === 'image/png');
            $resolved = $attachment?->attachWith(
                fn () => null,
                fn ($data) => $data(),
            );

            return $resolved === $customCard;
        });

        app(VisitorCategoryCardService::class)->remove($category->fresh());
        $defaultCard = $this->withSession(['visitor_registration' => $registration])
            ->get(route('visitor.card.download'))
            ->assertOk()
            ->getContent();

        $this->assertNotSame($customCard, $defaultCard);
    }
}
