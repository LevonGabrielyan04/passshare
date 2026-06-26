<x-layouts::app :title="__('New Send')">
    <div class="mx-auto w-full max-w-2xl">
        <h1 class="mb-6 text-2xl font-semibold text-zinc-900 dark:text-white">{{ __('Configure Send') }}</h1>

        <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-xs sm:p-10 dark:border-zinc-700 dark:bg-zinc-900">
            <form
                method="POST"
                action="{{ route('sends.store') }}"
                x-data="viewerManager"
                data-initial-viewers='@json(old('viewers', []))'
                data-min-password-length="{{ config('send.password.min_length') }}"
                @submit.prevent="submitForm"
            >
                @csrf

                <div class="mb-5">
                    <label for="name" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Send name') }}</label>
                    <input
                        type="text"
                        id="name"
                        name="name"
                        maxlength="255"
                        value="{{ old('name') }}"
                        placeholder="{{ __('Enter a name for this send') }}"
                        class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-xs focus:border-zinc-500 focus:outline-hidden focus:ring-2 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-800 @error('name') border-red-500 @enderror"
                        required
                    />
                    @error('name')
                        <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-5">
                    <div class="flex items-center justify-between">
                        <label for="viewer" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Viewers (Up to 100)') }}</label>
                        <span class="text-sm font-medium text-red-600" x-show="error" x-text="error" x-cloak></span>
                    </div>

                    <input
                        type="email"
                        id="viewer"
                        :value="newViewer"
                        @input="setNewViewer"
                        @keydown.enter.prevent="addViewer"
                        @keydown.comma.prevent="addViewer"
                        @keydown.space.prevent="addViewer"
                        placeholder="{{ __('Enter an email address and press Enter') }}"
                        class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-xs focus:border-zinc-500 focus:outline-hidden focus:ring-2 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-800 @error('viewers') border-red-500 @enderror"
                        :required="isViewerInputRequired"
                    />

                    <template x-for="(email, index) in viewers" :key="index">
                        <input type="hidden" name="viewers[]" :value="email" />
                    </template>

                    <div class="mt-3 flex flex-wrap gap-2" x-show="hasViewers" x-cloak>
                        <template x-for="(email, index) in viewers" :key="index">
                            <span class="inline-flex items-center gap-1 rounded-full bg-zinc-100 px-3 py-1 text-sm font-medium text-zinc-800 dark:bg-zinc-800 dark:text-zinc-200">
                                <span x-text="email"></span>
                                <button type="button" @click="removeViewerFromEvent" :data-index="index" class="ml-1 hover:text-red-600 focus:outline-none">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                    </svg>
                                </button>
                            </span>
                        </template>
                    </div>

                    @error('viewers')
                        <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                    @enderror
                    @error('viewers.*')
                        <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-5">
                    <label for="message" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Text to send') }}</label>
                    <textarea
                        id="message"
                        name="message"
                        maxlength="{{ config('send.message.max_length') }}"
                        rows="5"
                        placeholder="{{ __('Type the message content here...') }}"
                        class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-xs focus:border-zinc-500 focus:outline-hidden focus:ring-2 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-800 @error('message') border-red-500 @enderror"
                        required
                    >{{ old('message') }}</textarea>
                    @error('message')
                        <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-5">
                    <label for="expire_after" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">{{ __('Expire after') }}</label>
                    <select
                        id="expire_after"
                        name="expire_after"
                        class="mt-2 block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm shadow-xs focus:border-zinc-500 focus:outline-hidden focus:ring-2 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-800 @error('expire_after') border-red-500 @enderror"
                        required
                    >
                        @foreach(\App\Enums\TimePeriod::cases() as $duration)
                            <option value="{{ $duration->value }}" @selected(old('expire_after', \App\Enums\TimePeriod::ONE_DAY->value) === $duration->value)>
                                {{ $duration->value }}
                            </option>
                        @endforeach
                    </select>
                    @error('expire_after')
                        <span class="mt-1 block text-sm text-red-600">{{ $message }}</span>
                    @enderror
                </div>

                <div class="mb-8">
                    <div class="flex items-center gap-2">
                        <label for="password" class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                            {{ __('Password') }}
                            <span class="font-normal text-zinc-500">({{ __('Optional') }})</span>
                        </label>

                        <div x-data="passwordTooltip" class="relative flex items-center">
                            <button
                                type="button"
                                @mouseenter="show"
                                @mouseleave="hide"
                                @focus="show"
                                @blur="hide"
                                class="text-zinc-500 hover:text-zinc-700 focus:outline-none dark:hover:text-zinc-300"
                                aria-label="{{ __('Password information') }}"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                                </svg>
                            </button>

                            <div
                                x-show="showTooltip"
                                x-transition.opacity.duration.200ms
                                class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 w-52 -translate-x-1/2 rounded-md bg-zinc-900 p-2 text-center text-xs text-white shadow-xl"
                                x-cloak
                            >
                                {{ __('If a password is set, the transfer becomes end-to-end encrypted. Passwords must be at least :length characters.', ['length' => config('send.password.min_length')]) }}
                                <div class="absolute top-full left-1/2 -translate-x-1/2 border-4 border-transparent border-t-zinc-900"></div>
                            </div>
                        </div>
                    </div>

                    <div class="relative mt-2" x-data="{ showPassword: false }">
                        <input
                            :type="showPassword ? 'text' : 'password'"
                            id="password"
                            name="password"
                            minlength="{{ config('send.password.min_length') }}"
                            passwordrules="{{ \Illuminate\Validation\Rules\Password::min(16)->mixedCase()->numbers()->symbols()->toPasswordRulesString() }}"
                            placeholder="{{ __('Minimum :length characters, or leave blank', ['length' => config('send.password.min_length')]) }}"
                            class="block w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 pr-10 text-sm shadow-xs focus:border-zinc-500 focus:outline-hidden focus:ring-2 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-800"
                            @input="clearPasswordError"
                        />
                        <button
                            type="button"
                            @click="showPassword = !showPassword"
                            class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-500 hover:text-zinc-700 focus:outline-none dark:hover:text-zinc-300"
                            :aria-label="showPassword ? 'Hide password' : 'Show password'"
                        >
                            <svg x-show="!showPassword" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                            <svg x-show="showPassword" x-cloak xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 001.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.45 10.45 0 0112 4.5c4.756 0 8.773 3.162 10.065 7.498a10.523 10.523 0 01-4.293 5.774M6.228 6.228L3 3m3.228 3.228l3.65 3.65m7.894 7.894L21 21m-3.228-3.228l-3.65-3.65m0 0a3 3 0 01-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
                    <span class="mt-1 block text-sm text-red-600" x-show="passwordError" x-text="passwordError" x-cloak></span>
                </div>

                <x-send-encryption-indicator />

                <button
                    type="submit"
                    class="inline-flex w-full items-center justify-center rounded-lg bg-zinc-900 px-4 py-2 text-sm font-medium text-white hover:bg-zinc-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-white dark:text-zinc-900 dark:hover:bg-zinc-100"
                    :disabled="isEncrypting"
                >
                    {{ __('Generate') }}
                </button>
                <span class="mt-1 block text-sm text-red-600" x-show="encryptionError" x-text="encryptionError" x-cloak></span>
            </form>
        </div>
    </div>
</x-layouts::app>
