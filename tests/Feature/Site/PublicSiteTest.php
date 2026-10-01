<?php

declare(strict_types=1);

namespace Tests\Feature\Site;

use App\Models\ContactMessage;
use App\Models\SitePage;
use App\Models\SiteSetting;
use Database\Seeders\SiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(SiteContentSeeder::class);
    }

    public function test_every_public_page_renders(): void
    {
        $this->get('/')->assertOk()->assertSee('How it works');

        foreach (['about-us', 'services', 'contact-us', 'privacy-policy', 'refund-policy', 'terms-and-conditions', 'disclaimer'] as $slug) {
            $this->get("/{$slug}")->assertOk();
        }
    }

    public function test_unpublished_page_is_404(): void
    {
        SitePage::query()->where('slug', 'disclaimer')->update(['is_published' => false]);

        $this->get('/disclaimer')->assertNotFound();
    }

    public function test_admin_authored_html_is_escaped_on_the_page(): void
    {
        SitePage::query()->where('slug', 'about-us')->update(['body' => '<script>alert(1)</script> hello']);
        SiteSetting::write('business_name', 'Acme <b>Ltd</b>');

        $res = $this->get('/about-us')->assertOk();

        $res->assertDontSee('<script>alert(1)</script>', false);
        $res->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false);
        $res->assertDontSee('<b>Ltd</b>', false);
    }

    public function test_legal_tokens_use_the_saved_business_details(): void
    {
        SiteSetting::write('business_name', 'Acme Media');
        SiteSetting::write('contact_email', 'help@acme.example');

        $this->get('/privacy-policy')->assertOk()->assertSee('Acme Media')->assertSee('help@acme.example');
    }

    public function test_contact_form_stores_a_message(): void
    {
        $this->post('/contact-us', ['name' => 'Ravi', 'email' => 'ravi@example.com', 'message' => 'Please call me back about pricing.'])
            ->assertRedirect('/contact-us');

        $this->assertDatabaseHas('contact_messages', ['email' => 'ravi@example.com']);
    }

    public function test_contact_honeypot_discards_bot_submissions(): void
    {
        $this->post('/contact-us', ['name' => 'Bot', 'email' => 'bot@example.com', 'message' => 'Buy cheap stuff now please', 'website' => 'http://spam.example'])
            ->assertRedirect('/contact-us');

        $this->assertSame(0, ContactMessage::query()->count());
    }

    public function test_contact_form_validates_input(): void
    {
        $this->post('/contact-us', ['name' => '', 'email' => 'nope', 'message' => 'short'])->assertSessionHasErrors(['name', 'email', 'message']);
    }
}
