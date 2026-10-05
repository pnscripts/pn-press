<?php

namespace Database\Seeders;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Demo seed: roles, a local admin account and the original demo posts in DemoContent.
 *
 * Safe to run more than once: rows are matched by email or slug and existing
 * rows are left untouched, so edits made in the admin survive a re-seed.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'web']);

        $admin = User::query()->firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin',
                'password' => 'password',
            ]
        );

        $admin->assignRole($adminRole);

        $categories = collect(DemoContent::categories())
            ->mapWithKeys(fn (array $attributes) => [
                $attributes['slug'] => Category::query()->firstOrCreate(
                    ['slug' => $attributes['slug']],
                    $attributes
                ),
            ]);

        $today = now()->startOfDay();

        foreach (DemoContent::posts() as $index => $post) {
            $publishedAt = $post['status'] === PostStatus::Published && $post['days_ago'] !== null
                ? $today->copy()->subDays($post['days_ago'])->setTime(9, 0)->addMinutes(($index * 17) % 60)
                : null;

            $record = Post::query()->firstOrCreate(
                ['slug' => $post['slug']],
                [
                    'user_id' => $admin->id,
                    'category_id' => $categories[$post['category']]->id,
                    'title' => $post['title'],
                    'excerpt' => $post['excerpt'],
                    'body' => $post['body'],
                    'status' => $post['status'],
                    'published_at' => $publishedAt,
                    'featured_image' => DemoCoverGenerator::publicPath($post['slug']),
                ]
            );

            if ($record->wasRecentlyCreated) {
                // Give new demo rows believable history instead of "created a second ago".
                $writtenAt = $publishedAt !== null && $publishedAt->isPast()
                    ? $publishedAt->copy()->subHours(2)
                    : $today->copy()->subDays(2 - ($index % 2))->setTime(15, 0);

                $record->forceFill(['created_at' => $writtenAt, 'updated_at' => $writtenAt])->saveQuietly();
            }
        }
    }
}
