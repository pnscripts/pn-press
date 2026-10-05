<?php

namespace Database\Seeders;

use App\Enums\PostStatus;

/**
 * Original demo content for a fresh PN Press install.
 *
 * Every post is about running a small blog, so the demo doubles as a short
 * manual. Covers are drawn by DemoCoverGenerator and served from public/.
 */
final class DemoContent
{
    /**
     * @return list<array{name: string, slug: string, description: string}>
     */
    public static function categories(): array
    {
        return [
            ['name' => 'Getting started', 'slug' => 'getting-started', 'description' => 'First steps after installing PN Press: what to change, what to keep and where things live.'],
            ['name' => 'Writing', 'slug' => 'writing', 'description' => 'Titles, excerpts, structure and the small habits that make posts easier to read.'],
            ['name' => 'Workflow', 'slug' => 'workflow', 'description' => 'Drafts, scheduling, roles and the routines that keep a blog moving.'],
            ['name' => 'Design', 'slug' => 'design', 'description' => 'Cover images, categories and the parts of the site readers see first.'],
        ];
    }

    /**
     * Posts in the order they are seeded. `days_ago` is relative to the day the
     * seeder runs; a negative value schedules the post in the future.
     *
     * @return list<array{title: string, slug: string, category: string, status: PostStatus, days_ago: int|null, cover: string, excerpt: string, body: string}>
     */
    public static function posts(): array
    {
        return [
            [
                'title' => 'Welcome to your new PN Press blog',
                'slug' => 'welcome-to-your-new-pn-press-blog',
                'category' => 'getting-started',
                'status' => PostStatus::Published,
                'days_ago' => 1,
                'cover' => 'orbits',
                'excerpt' => 'A quick tour of the demo site, the admin panel and the three settings worth changing before you write anything.',
                'body' => <<<'HTML'
<p>This blog was filled with sample posts by the demo seeder, so you can click around before writing a word. Every post here is about running a small blog, which makes the demo a short manual as well.</p>
<h2>What you are looking at</h2>
<p>The public site has three kinds of page: the post list on the home page, a page for each post, and an archive for each category. Drafts and posts scheduled for later never show up here, no matter how you reach the URL.</p>
<p>Everything else happens in the admin panel at <code>/admin</code>. Sign in with the demo account from the README and you will land on a dashboard that shows what is published, what is still a draft and what is coming up.</p>
<h2>Change these first</h2>
<ul>
<li><strong>The site name.</strong> Set <code>APP_NAME</code> in your <code>.env</code> file. It appears in the header, the footer and the browser tab.</li>
<li><strong>The admin password.</strong> The seeded account is for local use only. Change the email and password before the site goes anywhere public.</li>
<li><strong>The demo posts.</strong> Edit them, unpublish them or delete them. They exist to show the layout, not to stay.</li>
</ul>
<p>When you are ready, open <em>Posts</em> in the admin and press <em>New post</em>. The next article walks through that screen.</p>
HTML,
            ],
            [
                'title' => 'Write your first post in five minutes',
                'slug' => 'write-your-first-post-in-five-minutes',
                'category' => 'writing',
                'status' => PostStatus::Published,
                'days_ago' => 3,
                'cover' => 'columns',
                'excerpt' => 'Title, slug, category, excerpt, body: a field-by-field walk through the post editor so nothing on that screen is a mystery.',
                'body' => <<<'HTML'
<p>The post editor has eight fields. Five are required, and you can save a draft long before the post is finished.</p>
<h2>Title and slug</h2>
<p>Type the title first. When you leave the field, the slug fills itself in from the title. The slug is the last part of the post URL, so keep it short and leave it alone once the post is public: changing it breaks every link that already points to the post.</p>
<h2>Category and status</h2>
<p>Pick one category. Readers use categories to find related posts, so a post that fits nowhere is a hint that you need a new category or a different post.</p>
<p>Leave the status on <em>Draft</em> while you work. Nobody outside the admin can see a draft.</p>
<h2>Excerpt and body</h2>
<p>The excerpt is the short summary shown on the home page and at the top of the post. Write it last, once you know what the post actually says.</p>
<p>The body uses a rich text editor. Use headings to break up anything longer than a few paragraphs, and lists when you are describing steps.</p>
<blockquote><p>Save early. A draft that exists is easier to finish than a perfect post that only lives in your head.</p></blockquote>
HTML,
            ],
            [
                'title' => 'Drafts, publish dates and scheduled posts',
                'slug' => 'drafts-publish-dates-and-scheduled-posts',
                'category' => 'workflow',
                'status' => PostStatus::Published,
                'days_ago' => 6,
                'cover' => 'grid',
                'excerpt' => 'How PN Press decides what readers can see, and how to use a future publish date to line up posts ahead of time.',
                'body' => <<<'HTML'
<p>A post appears on the public site only when two things are true: its status is <em>Published</em>, and its publish date is now or in the past.</p>
<h2>Drafts</h2>
<p>Drafts are private. They do not show up on the home page, in category archives or at their own URL. Use them for ideas, half-written posts and anything waiting for a second pair of eyes.</p>
<h2>Publish dates</h2>
<p>If you publish without picking a date, PN Press uses the current time. You can also set the date yourself, for example to keep the original date of a post you are moving from another blog.</p>
<h2>Scheduling</h2>
<p>Set the status to <em>Published</em> and choose a date in the future. The post stays hidden until that moment and then appears on its own. There is no cron job to set up; the check happens every time a page loads.</p>
<p>The dashboard lists scheduled posts separately, so you can see at a glance what is lined up for the coming days.</p>
<ul>
<li>Draft: hidden, whatever the date says.</li>
<li>Published, date in the past: visible.</li>
<li>Published, date in the future: hidden until that date.</li>
</ul>
HTML,
            ],
            [
                'title' => 'Choosing categories readers will actually use',
                'slug' => 'choosing-categories-readers-will-actually-use',
                'category' => 'design',
                'status' => PostStatus::Published,
                'days_ago' => 9,
                'cover' => 'orbits',
                'excerpt' => 'Fewer, broader categories beat a long list nobody clicks. Here is a simple way to pick them and when to add another.',
                'body' => <<<'HTML'
<p>Categories are the main way readers move around a small blog. Each one gets its own archive page, and each post belongs to at most one.</p>
<h2>Start with three to five</h2>
<p>Look at the posts you have written, or plan to write in the next few months, and group them by what a reader is trying to do: learn the basics, fix a problem, follow news. Those groups are your categories.</p>
<p>Name them the way a reader would say them out loud. "Getting started" works better than "Onboarding resources".</p>
<h2>Write a one-line description</h2>
<p>Every category has an optional description that appears at the top of its archive. One sentence is enough. It tells new readers whether they are in the right place.</p>
<h2>When to add a category</h2>
<p>Add one when you notice several posts that do not really fit anywhere, not before. An empty category looks unfinished, and a category with one post feels like a dead end.</p>
<h2>Renaming later</h2>
<p>You can rename a category at any time. Keep the slug if other sites link to the archive page, because the slug is what appears in the URL.</p>
HTML,
            ],
            [
                'title' => 'Writing excerpts that earn the click',
                'slug' => 'writing-excerpts-that-earn-the-click',
                'category' => 'writing',
                'status' => PostStatus::Published,
                'days_ago' => 13,
                'cover' => 'waves',
                'excerpt' => 'An excerpt is a promise about what the post delivers. Two sentences, concrete, no teaser tricks: here is how to write one.',
                'body' => <<<'HTML'
<p>On the home page, most readers see only the title and the excerpt. Those two lines decide whether they open the post.</p>
<h2>Say what the reader gets</h2>
<p>A good excerpt answers one question: what will I know or be able to do after reading this? "How to schedule a post for next Monday" is more useful than "Some thoughts on publishing".</p>
<h2>Keep it short</h2>
<p>One or two sentences is plenty. Long excerpts get cut off visually and push the next post further down the page.</p>
<h2>Avoid teaser tricks</h2>
<p>"You won't believe what happened next" may get a click once. It also teaches readers to distrust every excerpt after it.</p>
<h2>Make each one different</h2>
<p>If two posts have nearly the same excerpt, readers cannot tell them apart. That is often a sign the posts overlap and could be merged into one better post.</p>
<ul>
<li>Write the excerpt after the post, not before.</li>
<li>Read it next to the title. Together they should not repeat each other.</li>
<li>Check it on a phone, where space is tightest.</li>
</ul>
HTML,
            ],
            [
                'title' => 'Giving editors access without handing over the keys',
                'slug' => 'giving-editors-access-without-handing-over-the-keys',
                'category' => 'workflow',
                'status' => PostStatus::Published,
                'days_ago' => 17,
                'cover' => 'grid',
                'excerpt' => 'PN Press ships with two roles, admin and editor. What each one is for, and a safe way to add a guest writer.',
                'body' => <<<'HTML'
<p>PN Press comes with two roles. Only users with one of them can sign in to the admin panel at all; everyone else is turned away at the door.</p>
<h2>Admin</h2>
<p>Admins run the site. Give this role to the people who own it and nobody else.</p>
<h2>Editor</h2>
<p>Editors write and manage content: posts and categories. This is the right role for regular writers and for anyone helping out with the blog.</p>
<h2>Adding a new writer</h2>
<ol>
<li>Create their user account with their own email address. Never share one login between people.</li>
<li>Assign the <em>editor</em> role.</li>
<li>Ask them to change the password you set the first time they sign in.</li>
</ol>
<h2>When someone leaves</h2>
<p>Remove their role, or the whole account. Their posts stay on the site, because posts belong to the blog rather than to the person who wrote them.</p>
<p>If you need finer control, such as editors who can write but not publish, the roles come from Spatie Permission and can be extended in code.</p>
HTML,
            ],
            [
                'title' => 'Cover images: sizes, formats and alt text',
                'slug' => 'cover-images-sizes-formats-and-alt-text',
                'category' => 'design',
                'status' => PostStatus::Published,
                'days_ago' => 22,
                'cover' => 'columns',
                'excerpt' => 'Use 1200 by 630 pixels, keep files small and only use images you have the right to publish. A short checklist for covers.',
                'body' => <<<'HTML'
<p>Each post can have one cover image. It appears on the home page and at the top of the post, cropped to fit the layout.</p>
<h2>Size</h2>
<p>Use images that are 1200 by 630 pixels. That ratio crops well on wide screens and phones, and it is the size most social networks expect for link previews.</p>
<h2>Format and weight</h2>
<ul>
<li>Photos: JPEG or WebP, under about 200 KB.</li>
<li>Illustrations and diagrams: SVG or PNG.</li>
<li>Avoid text inside the image. It becomes unreadable when the image is cropped or shrunk.</li>
</ul>
<h2>Where the file lives</h2>
<p>The cover field takes a full URL, a path on the public storage disk, or a path inside the <code>public</code> folder that starts with a slash. The demo covers on this site use the last option and are drawn by a small generator in the repository.</p>
<h2>Rights</h2>
<p>Only use images you made, paid for, or that come with a licence allowing it. Linking straight to someone else's server is fragile as well: if they move the file, your post loses its cover.</p>
HTML,
            ],
            [
                'title' => 'A simple weekly publishing routine',
                'slug' => 'a-simple-weekly-publishing-routine',
                'category' => 'workflow',
                'status' => PostStatus::Published,
                'days_ago' => 28,
                'cover' => 'waves',
                'excerpt' => 'One planning session, one writing block and one review pass per week is enough to keep a small blog alive. Here is the routine.',
                'body' => <<<'HTML'
<p>Most small blogs do not stop because the writer runs out of ideas. They stop because publishing never becomes a habit. A fixed weekly routine fixes that.</p>
<h2>Monday: plan</h2>
<p>Open the dashboard and look at the drafts list. Pick one draft to finish this week, or create a new one with just a title and a few notes.</p>
<h2>Midweek: write</h2>
<p>Block an hour. Write the body first, then the title, then the excerpt. Save as a draft whenever you stop.</p>
<h2>Thursday: review</h2>
<p>Read the post on a phone. Fix anything that is hard to follow, add a cover image and check the category.</p>
<h2>Friday: schedule</h2>
<p>Set the status to published and pick a date. Scheduling ahead means a busy week does not break the streak.</p>
<blockquote><p>One finished post a week adds up to fifty a year. That is a real archive.</p></blockquote>
HTML,
            ],
            [
                'title' => 'What to check before you launch',
                'slug' => 'what-to-check-before-you-launch',
                'category' => 'getting-started',
                'status' => PostStatus::Published,
                'days_ago' => -3,
                'cover' => 'grid',
                'excerpt' => 'Scheduled for later: a launch checklist covering credentials, mail, backups and the settings that differ between local and live.',
                'body' => <<<'HTML'
<p>This post is scheduled for a future date, so it is visible in the admin but not yet on the public site. It is part of the demo to show how scheduling works.</p>
<h2>Before going live</h2>
<ul>
<li>Replace the demo admin account and use a strong password.</li>
<li>Set <code>APP_ENV=production</code> and <code>APP_DEBUG=false</code>.</li>
<li>Configure a real mail driver so password resets reach people.</li>
<li>Set up database backups and test a restore once.</li>
<li>Run <code>npm run build</code> so the theme loads from your own server.</li>
</ul>
HTML,
            ],
            [
                'title' => 'Ideas for next month\'s roundup',
                'slug' => 'ideas-for-next-months-roundup',
                'category' => 'writing',
                'status' => PostStatus::Draft,
                'days_ago' => null,
                'cover' => 'orbits',
                'excerpt' => 'Draft only: a running list of links and notes for a monthly roundup post. Drafts like this never appear on the public blog.',
                'body' => <<<'HTML'
<p>This is a seeded draft. It shows up in the admin under drafts and on the dashboard, but never on the public site.</p>
<ul>
<li>Most-read post of the month and why it worked.</li>
<li>Two reader questions worth answering in full.</li>
<li>One thing we changed on the site.</li>
</ul>
HTML,
            ],
        ];
    }
}
