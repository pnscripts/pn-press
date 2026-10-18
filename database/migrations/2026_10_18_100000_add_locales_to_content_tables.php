<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multilingual content (1.1.0): every page, post, category and menu gets a language. Existing rows
 * take the site's default language. Slugs and paths become unique per language, so "/about" and
 * "/bg/about" can both exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        $default = $this->defaultLocale();

        foreach (['pages', 'posts', 'categories'] as $table) {
            Schema::table($table, function (Blueprint $table) use ($default) {
                $table->string('locale', 12)->default($default)->index();
                $table->string('translation_group', 36)->nullable()->index();
            });
        }

        Schema::table('pages', function (Blueprint $table) {
            $table->dropUnique(['path']);
            $table->unique(['locale', 'path']);
        });

        Schema::table('posts', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['locale', 'slug']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->unique(['locale', 'slug']);
        });

        // A menu for one language; null = shown in every language that has no menu of its own.
        Schema::table('menus', function (Blueprint $table) {
            $table->string('locale', 12)->nullable()->after('location');
            $table->dropUnique(['location']);
            $table->index(['location', 'locale']);
        });
    }

    public function down(): void
    {
        // Rolling back would merge every language into one and break the unique slugs/paths/menu locations.
        // Refuse before changing anything (MySQL cannot undo half a migration).
        $default = $this->defaultLocale();
        $other = DB::table('menus')->whereNotNull('locale')->exists();
        foreach (['pages', 'posts', 'categories'] as $table) {
            $other = $other || DB::table($table)->where('locale', '!=', $default)->exists();
        }

        if ($other) {
            throw new RuntimeException('Content in languages other than "'.$default.'" exists (pages, posts, categories or menus). Delete it first, then roll back.');
        }

        Schema::table('menus', function (Blueprint $table) {
            $table->dropIndex(['location', 'locale']);
            $table->dropColumn('locale');
            $table->unique('location');
        });

        foreach (['categories' => 'slug', 'posts' => 'slug', 'pages' => 'path'] as $name => $column) {
            Schema::table($name, function (Blueprint $table) use ($column) {
                $table->dropUnique(['locale', $column]);
                $table->unique($column);
            });

            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['locale']);
                $table->dropIndex(['translation_group']);
                $table->dropColumn(['locale', 'translation_group']);
            });
        }
    }

    private function defaultLocale(): string
    {
        $codes = array_values(array_filter(array_map('trim', (array) config('press.locales', ['en']))));
        $default = (string) config('press.default_locale');

        return in_array($default, $codes, true) ? $default : ($codes[0] ?? 'en');
    }
};
