<?php

namespace Tests\Feature\Press;

use App\Models\User;
use App\Press\Permissions;
use App\Press\Plugins\PluginManager;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;
use PnPress\ContactForm\ContactMessage;
use PnPress\ContactForm\Filament\ContactMessageResource;
use PnPress\ContactForm\Filament\Pages\ListContactMessages;
use PnPress\ContactForm\Filament\Pages\ViewContactMessage;
use PnPress\ContactForm\MessagesCsv;
use Tests\Concerns\RebootsWithAFileDatabase;
use Tests\TestCase;

/**
 * Plugins add admin screens through a Filament plugin (plugin.json "admin"); the Contact Form
 * plugin uses it for its inbox.
 */
class ContactInboxTest extends TestCase
{
    use RebootsWithAFileDatabase;

    protected function setUp(): void
    {
        $this->useFileDatabase();
        parent::setUp();

        Artisan::call('migrate', ['--force' => true]);
        Permissions::sync();
        app(PluginManager::class)->activate('contact-form');

        // The panel is built while the application boots: boot again with the plugin active.
        $this->reboot();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->restoreEnvironment();
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function message(array $attributes = []): ContactMessage
    {
        return ContactMessage::query()->create($attributes + [
            'name' => 'Ada Reader',
            'email' => 'ada@example.com',
            'message' => 'Hello, I liked the article about menus.',
            'page_url' => 'http://localhost/contact',
        ]);
    }

    public function test_the_inbox_is_in_the_admin_navigation_with_an_unhandled_count(): void
    {
        $this->message();
        $this->message(['name' => 'Bo'])->markHandled();

        $this->actingAs($this->user('admin'))
            ->get('/admin/contact-messages')
            ->assertOk()
            ->assertSee('Ada Reader')
            ->assertSee('Messages');

        $this->assertSame('1', ContactMessageResource::getNavigationBadge());
        $this->get('/admin')->assertOk()->assertSee(url('/admin/contact-messages'), false);
    }

    public function test_only_admins_and_granted_roles_open_the_inbox(): void
    {
        $editor = $this->user('editor');
        $this->actingAs($editor)->get('/admin/contact-messages')->assertForbidden();

        $editor->givePermissionTo(ContactMessage::PERMISSION);
        $this->actingAs($editor->fresh())->get('/admin/contact-messages')->assertOk();
    }

    public function test_reading_a_message_marks_it_read_and_it_can_be_handled_and_deleted(): void
    {
        $message = $this->message(['message' => "Line one\n<script>alert(1)</script>"]);
        $this->actingAs($this->user('admin'));

        $this->get("/admin/contact-messages/{$message->id}")
            ->assertOk()
            ->assertSee('Line one')
            ->assertDontSee('<script>alert(1)</script>', false);
        $this->assertNotNull($message->fresh()->read_at);

        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ViewContactMessage::class, ['record' => $message->id])->callAction('toggleHandled');
        $this->assertTrue($message->fresh()->isHandled());

        Livewire::test(ListContactMessages::class)->callTableAction('toggleHandled', $message);
        $this->assertFalse($message->fresh()->isHandled());

        Livewire::test(ListContactMessages::class)->callTableBulkAction('markHandled', [$message]);
        $this->assertTrue($message->fresh()->isHandled());

        Livewire::test(ListContactMessages::class)
            ->filterTable('handled', false)
            ->assertCanNotSeeTableRecords([$message]);

        Livewire::test(ListContactMessages::class)->callTableAction('delete', $message);
        $this->assertModelMissing($message);
    }

    public function test_messages_export_to_csv(): void
    {
        $this->message();
        $this->message(['name' => 'Bo', 'email' => 'bo@example.com']);
        $this->actingAs($this->user('admin'));
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(ListContactMessages::class)
            ->callAction('export')
            ->assertFileDownloaded('contact-messages-'.now()->format('Y-m-d').'.csv');

        $csv = MessagesCsv::toString(ContactMessage::query()->oldest('id')->get());
        $lines = array_values(array_filter(explode("\n", $csv)));

        $this->assertSame(implode(',', MessagesCsv::HEADER), $lines[0]);
        $this->assertCount(3, $lines);
        $this->assertStringContainsString('bo@example.com', $lines[2]);
    }

    public function test_csv_cells_cannot_become_spreadsheet_formulas(): void
    {
        $this->assertSame("'=HYPERLINK(\"x\")", MessagesCsv::cell('=HYPERLINK("x")'));
        $this->assertSame("'+1", MessagesCsv::cell('+1'));
        $this->assertSame("'@SUM(A1)", MessagesCsv::cell('@SUM(A1)'));
        $this->assertSame('plain text', MessagesCsv::cell('plain text'));
        $this->assertSame('', MessagesCsv::cell(null));
    }

    public function test_a_deactivated_plugin_takes_its_screens_with_it(): void
    {
        app(PluginManager::class)->deactivate('contact-form');
        $this->reboot();

        $this->actingAs($this->user('admin'))->get('/admin/contact-messages')->assertNotFound();
        $this->get('/admin')->assertOk()->assertDontSee(url('/admin/contact-messages'), false);
    }
}
