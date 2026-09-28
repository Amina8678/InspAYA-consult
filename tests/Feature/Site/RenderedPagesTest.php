<?php

namespace Tests\Feature\Site;

use App\Http\Controllers\Site\ContactController;
use App\Models\BlogPost;
use App\Models\Consultant;
use App\Models\CoreValue;
use App\Models\Media;
use App\Models\Page;
use App\Models\Service;
use Database\Seeders\SiteSettingsSeeder;
use Illuminate\Support\Facades\Notification;

/**
 * The real Blade views: structure, head tags, escaping and key content.
 */
class RenderedPagesTest extends SiteTestCase
{
    private Service $service;

    private BlogPost $post;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(SiteSettingsSeeder::class);

        foreach (['home', 'about', 'privacy-policy', 'terms-of-service'] as $slug) {
            Page::factory()->published()->create(['slug' => $slug, 'title' => 'Page '.$slug]);
        }
        $this->service = Service::factory()->create(['title' => 'Energy Policy Evaluations', 'slug' => 'energy-policy-evaluations']);
        $this->service->consultants()->attach(Consultant::factory()->withPhoto()->create(['name' => 'Placeholder Person']), ['is_lead' => true]);
        CoreValue::factory()->create(['title' => 'Integrity']);
        $this->post = BlogPost::factory()->published()->withFeaturedImage()->categorised()->create(['title' => 'An article title', 'slug' => 'an-article-title']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function pages(): array
    {
        return [
            'home' => ['/'],
            'about' => ['/about'],
            'services' => ['/services'],
            'service' => ['/services/energy-policy-evaluations'],
            'consultants' => ['/consultants'],
            'core values' => ['/core-values'],
            'insights' => ['/insights'],
            'article' => ['/insights/an-article-title'],
            'contact' => ['/contact'],
            'privacy' => ['/privacy-policy'],
            'terms' => ['/terms-of-service'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_every_page_has_landmarks_one_h1_and_seo_head_tags(string $url): void
    {
        $response = $this->get($url)->assertOk();
        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, '<h1'), 'Exactly one <h1>');
        $this->assertStringContainsString('<a class="skip-link" href="#main">', $html);
        foreach (['<header', '<nav class="main-nav" aria-label="Main">', '<main id="main"', '<footer'] as $landmark) {
            $this->assertStringContainsString($landmark, $html);
        }
        $this->assertMatchesRegularExpression('~<title>[^<]+</title>~', $html);
        $this->assertStringContainsString('<link rel="canonical" href="http', $html);
        $this->assertStringContainsString('<meta property="og:title"', $html);
        $this->assertStringContainsString('<meta property="og:url"', $html);
        $this->assertStringContainsString('<html lang="en">', $html);
        $this->assertStringNotContainsString('stub:', $html);
    }

    public function test_home_shows_featured_content_and_contact_cta(): void
    {
        $this->get('/')
            ->assertSeeInOrder(['What we do', 'Energy Policy Evaluations', 'Our core values', 'Integrity',
                'Our consultants', 'Placeholder Person', 'Latest insights', 'An article title', 'Talk to us'])
            ->assertSee(route('services.show', 'energy-policy-evaluations'), false)
            ->assertSee(route('contact'), false);
    }

    public function test_service_page_shows_capabilities_consultants_and_breadcrumb(): void
    {
        $this->get(route('services.show', 'energy-policy-evaluations'))
            ->assertSeeInOrder(['Breadcrumb', 'Services', 'Energy Policy Evaluations', 'Capabilities', 'Outcomes', 'Lead consultant', 'Placeholder Person'])
            ->assertSee('aria-current="page"', false);
    }

    public function test_article_page_has_article_metadata_share_links_and_copy_button(): void
    {
        $html = $this->get(route('insights.show', 'an-article-title'))->assertOk()->getContent();

        $this->assertStringContainsString('<meta property="og:type" content="article">', $html);
        $this->assertStringContainsString('<meta property="og:image"', $html);
        $this->assertStringContainsString('"@type":"Article"', $html);
        $this->assertStringContainsString('linkedin.com/sharing/share-offsite/?url='.rawurlencode(route('insights.show', 'an-article-title')), $html);
        $this->assertStringContainsString('data-copy-url="'.route('insights.show', 'an-article-title').'"', $html);
        $this->assertMatchesRegularExpression('~<time datetime="[^"]+">~', $html);
    }

    public function test_user_content_is_escaped_including_script_tags(): void
    {
        BlogPost::factory()->published()->create([
            'title' => '<script>alert("x")</script> Title',
            'slug' => 'xss-title',
            'content' => "<img src=x onerror=alert(1)>\nSecond line",
        ]);

        $index = $this->get(route('insights.index'))->assertOk();
        $index->assertDontSee('<script>alert("x")</script>', false);
        $index->assertSee('&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; Title', false);

        $show = $this->get(route('insights.show', 'xss-title'))->assertOk();
        $show->assertDontSee('<script>alert("x")</script>', false);
        $show->assertDontSee('<img src=x onerror=alert(1)>', false);
        $show->assertSee('&lt;img src=x onerror=alert(1)&gt;', false);
        // JSON-LD escapes angle brackets too, so the title can't close the <script>.
        $this->assertStringNotContainsString('</script> Title', $show->getContent());
    }

    public function test_unsafe_cta_links_from_the_cms_are_not_rendered(): void
    {
        Page::where('slug', 'home')->update(['structured_content' => json_encode([
            ['type' => 'hero', 'data' => [
                'heading' => 'Welcome',
                'primary_cta' => ['label' => 'Bad', 'url' => 'javascript:alert(1)'],
                'secondary_cta' => ['label' => 'Good', 'url' => '/services'],
            ]],
        ])]);

        $this->get('/')->assertOk()
            ->assertDontSee('javascript:alert', false)
            ->assertSee('href="/services"', false);
    }

    public function test_images_have_dimensions_alt_text_and_lazy_loading_below_the_fold(): void
    {
        $html = $this->get('/')->getContent();

        // Consultant photo on the home page is below the fold.
        $this->assertMatchesRegularExpression('~<img src="[^"]+"\s+alt="[^"]*"\s+width="\d+"\s+height="\d+"\s+loading="lazy"~', $html);

        // The article's featured image is the first thing on the page: not lazy.
        $article = $this->get(route('insights.show', 'an-article-title'))->getContent();
        $this->assertMatchesRegularExpression('~class="article__image">\s*<img [^>]*fetchpriority="high"~', $article);
    }

    public function test_contact_form_has_labelled_fields_consent_and_hidden_honeypot(): void
    {
        $html = $this->get(route('contact'))->getContent();

        foreach (['name', 'email', 'phone', 'organization', 'subject', 'message', 'consent'] as $field) {
            $this->assertStringContainsString('for="field-'.$field.'"', $html, "Label for {$field}");
            $this->assertStringContainsString('id="field-'.$field.'"', $html);
        }
        $this->assertStringContainsString('autocomplete="email"', $html);
        $this->assertMatchesRegularExpression('~<div class="form__trap" aria-hidden="true">\s*<label for="field-website">~', $html);
        $this->assertStringContainsString('tabindex="-1" autocomplete="off"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringContainsString('href="'.route('privacy-policy').'">privacy policy</a>', $html);
    }

    public function test_contact_success_message_is_shown_after_saving(): void
    {
        Notification::fake();

        $this->followingRedirects()->post(route('contact.store'), [
            'name' => 'Ada', 'email' => 'ada@example.com', 'subject' => 'Hi', 'message' => 'Hello', 'consent' => '1',
        ])->assertOk()->assertSee(ContactController::SUCCESS_MESSAGE)->assertSee('role="status"', false);

        $this->assertDatabaseCount('contact_submissions', 1);
    }

    public function test_contact_errors_are_summarised_and_linked_to_fields(): void
    {
        $response = $this->followingRedirects()->from(route('contact'))->post(route('contact.store'), [
            'name' => '', 'email' => 'bad', 'subject' => '', 'message' => '', 'old_value_check' => 'x',
        ]);

        $response->assertOk()
            ->assertSee('There is a problem with your message')
            ->assertSee('href="#field-name"', false)
            ->assertSee('href="#field-consent"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('aria-describedby="field-email-error"', false)
            ->assertSee('value="bad"', false);
    }

    public function test_custom_404_page_is_used_and_not_indexed(): void
    {
        $response = $this->get('/services/does-not-exist')->assertNotFound();

        $response->assertSee('Page not found')
            ->assertSee('<meta name="robots" content="noindex">', false)
            ->assertDontSee('rel="canonical"', false)
            ->assertSee('<nav class="main-nav" aria-label="Main">', false)
            ->assertSee('class="site-footer"', false)
            ->assertSee(route('contact'), false);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_css_and_js_are_local_files_with_no_remote_requests(): void
    {
        $html = $this->get('/')->getContent();

        $this->assertStringContainsString(asset('css/theme.css'), $html);
        $this->assertStringContainsString(asset('css/site.css'), $html);
        $this->assertDoesNotMatchRegularExpression('~<(link|script)[^>]+(href|src)="https?://(?!localhost)~', $html);
        $this->assertFileExists(public_path('css/theme.css'));
        $this->assertFileExists(public_path('js/site.js'));
    }

    public function test_views_contain_no_unescaped_output(): void
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(resource_path('views/public')));
        $views = array_merge(
            array_filter(iterator_to_array($files), fn ($f) => str_ends_with($f, '.blade.php')),
            [new \SplFileInfo(resource_path('views/layouts/public.blade.php')), new \SplFileInfo(resource_path('views/errors/404.blade.php'))],
        );

        foreach ($views as $file) {
            $this->assertStringNotContainsString('{!!', file_get_contents($file), $file->getPathname());
        }
    }
}
