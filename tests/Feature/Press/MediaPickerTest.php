<?php

namespace Tests\Feature\Press;

use App\Filament\Forms\MediaPicker;
use App\Filament\Resources\CmsPages\Pages\EditPage;
use App\Filament\Resources\Media\Pages\CreateMedia;
use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use App\Press\Permissions;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MediaPickerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function image(string $name, array $attributes = []): Media
    {
        Storage::disk('public')->put("media/{$name}", 'x');

        return Media::query()->create($attributes + [
            'disk' => 'public', 'path' => "media/{$name}", 'name' => $name, 'mime_type' => 'image/png', 'size' => 1,
        ]);
    }

    public function test_options_show_thumbnails_and_escape_names(): void
    {
        $image = $this->image('sea.png');
        $evil = $this->image('<b onmouseover=x>.png');
        Media::query()->create(['disk' => 'public', 'path' => 'media/a.pdf', 'name' => 'a.pdf', 'mime_type' => 'application/pdf', 'size' => 1]);

        $options = MediaPicker::options(MediaPicker::images()->get());

        $this->assertEqualsCanonicalizing([$image->id, $evil->id], array_keys($options), 'Only images are offered.');
        $this->assertStringContainsString('<img src="'.e($image->url()).'"', $options[$image->id]);
        $this->assertStringContainsString('sea.png', $options[$image->id]);
        $this->assertStringNotContainsString('<b onmouseover', $options[$evil->id]);
        $this->assertStringContainsString('&lt;b onmouseover=x&gt;.png', $options[$evil->id]);
    }

    public function test_search_matches_name_alt_and_caption(): void
    {
        $this->image('harbour.png', ['alt' => 'Boats at dusk']);
        $this->image('field.png', ['caption' => 'Poppies in June']);
        $this->image('100%.png');

        $this->assertSame(['harbour.png'], MediaPicker::search('boats')->pluck('name')->all());
        $this->assertSame(['field.png'], MediaPicker::search('poppies')->pluck('name')->all());
        $this->assertSame(['100%.png'], MediaPicker::search('%')->pluck('name')->all());
        $this->assertCount(3, MediaPicker::search(''));

        $tile = MediaPicker::tiles(MediaPicker::search('boats'))[0];
        $this->assertSame('Boats at dusk', $tile['alt']);
    }

    public function test_uploading_in_the_media_library_records_size_and_dimensions(): void
    {
        Permissions::sync();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateMedia::class)
            ->fillForm(['path' => UploadedFile::fake()->image('cover.jpg', 64, 48), 'alt' => 'A cover'])
            ->call('create')
            ->assertHasNoFormErrors();

        $media = Media::query()->sole();
        $this->assertSame('A cover', $media->alt);
        $this->assertSame([64, 48], [(int) $media->width, (int) $media->height]);
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertSame($admin->id, (int) $media->user_id);
    }

    public function test_the_page_editor_browses_and_uploads_images_for_image_blocks(): void
    {
        Permissions::sync();
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->actingAs($admin);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $old = $this->image('old.png');
        $new = $this->image('new.png', ['alt' => 'The new one']);
        $page = Page::factory()->create(['blocks' => [['type' => 'image', 'data' => ['media_id' => $old->id, 'caption' => 'Hi']]]]);

        $livewire = Livewire::test(EditPage::class, ['record' => $page->getRouteKey()]);
        $key = array_key_first($livewire->get('data.blocks'));
        $picker = TestAction::make('browseMedia')->schemaComponent("blocks.{$key}.data.media_id", 'form');

        // The selected image shows its thumbnail.
        $this->assertMatchesRegularExpression('#initialOptionLabel: .*img src=.{0,8}/storage.{0,2}/media.{0,2}/old\.png#', $livewire->html());

        // Browse: the grid lists the library, choosing one sets the block's image.
        $livewire
            ->mountAction($picker)
            ->assertActionMounted($picker)
            ->assertSet('mountedActions.0.data.media', $old->id)
            ->set('mountedActions.0.data.media', $new->id)
            ->callMountedAction()
            ->assertHasNoErrors()
            ->assertSet("data.blocks.{$key}.data.media_id", $new->id);

        // Upload: a new image goes into the library and into the block.
        $livewire
            ->callAction($picker, ['upload' => UploadedFile::fake()->image('fresh.png', 40, 30)])
            ->assertHasNoErrors();

        $fresh = Media::query()->latest('id')->first();
        $this->assertNotSame($new->id, $fresh->id);
        $this->assertSame('image/png', $fresh->mime_type);
        $this->assertSame(40, (int) $fresh->width);
        $this->assertSame($admin->id, (int) $fresh->user_id);
        Storage::disk('public')->assertExists($fresh->path);
        $livewire->assertSet("data.blocks.{$key}.data.media_id", $fresh->id);

        $livewire->call('save')->assertHasNoErrors();
        $this->assertSame($fresh->id, (int) $page->fresh()->blocks[0]['data']['media_id']);
    }
}
