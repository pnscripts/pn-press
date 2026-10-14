<section id="contact-form" class="press-container max-w-3xl py-12">
    <div class="rounded-sm border border-rule bg-surface p-6 sm:p-10">
        <h2 class="font-display text-3xl font-semibold tracking-tight">{{ $data['heading'] ?? 'Get in touch' }}</h2>
        @if (!empty($data['intro']))
            <p class="mt-2 text-muted">{{ $data['intro'] }}</p>
        @endif

        @if (session('contact-form.sent'))
            <p role="status" class="mt-6 rounded-sm border-l-4 border-accent bg-paper p-4 font-medium">Thank you. Your message has been sent.</p>
        @else
            <form method="POST" action="{{ route('contact-form.send') }}" class="mt-6 grid gap-4 sm:grid-cols-2">
                @csrf
                <div class="hidden" aria-hidden="true">
                    <label>Website <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
                </div>
                <label class="grid gap-1 text-sm font-medium">Name
                    <input name="name" value="{{ old('name') }}" required maxlength="120" class="rounded-sm border border-rule bg-paper px-3 py-2 text-base font-normal">
                    @error('name') <span class="text-sm text-red-700">{{ $message }}</span> @enderror
                </label>
                <label class="grid gap-1 text-sm font-medium">Email
                    <input type="email" name="email" value="{{ old('email') }}" required maxlength="190" class="rounded-sm border border-rule bg-paper px-3 py-2 text-base font-normal">
                    @error('email') <span class="text-sm text-red-700">{{ $message }}</span> @enderror
                </label>
                <label class="grid gap-1 text-sm font-medium sm:col-span-2">Message
                    <textarea name="message" rows="5" required maxlength="5000" class="rounded-sm border border-rule bg-paper px-3 py-2 text-base font-normal">{{ old('message') }}</textarea>
                    @error('message') <span class="text-sm text-red-700">{{ $message }}</span> @enderror
                </label>
                <div class="sm:col-span-2">
                    <button type="submit" class="press-button">{{ $data['button_label'] ?? 'Send message' }}</button>
                </div>
            </form>
        @endif
    </div>
</section>
