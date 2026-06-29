<x-layouts::app :title="__('Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-6">
        <div class="flex items-center justify-between">
            <flux:heading size="xl">{{ __('Your Sends') }}</flux:heading>
            <flux:button :href="route('sends.create')" icon="plus" wire:navigate>
                {{ __('New Send') }}
            </flux:button>
        </div>

        @if (session('success'))
            <flux:callout variant="success">
                {{ session('success') }}
            </flux:callout>
        @endif

        @if ($sends->isEmpty())
            <flux:card class="p-8 text-center">
                <flux:text>{{ __('No sends yet.') }}</flux:text>
                <flux:button :href="route('sends.create')" variant="primary" class="mt-4" wire:navigate>
                    {{ __('Create your first send') }}
                </flux:button>
            </flux:card>
        @else
            <flux:card class="overflow-hidden p-0">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-zinc-200 dark:divide-zinc-700">
                        <thead class="bg-zinc-50 dark:bg-zinc-900">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                                    {{ __('Name') }}
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                                    {{ __('Expires') }}
                                </th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-zinc-500">
                                    {{ __('Viewers') }}
                                </th>
                                <th scope="col" class="px-4 py-3 text-right text-xs font-medium uppercase tracking-wider text-zinc-500">
                                    {{ __('Actions') }}
                                </th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                            @foreach ($sends as $send)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-4 text-sm font-medium">
                                        <flux:link :href="route('sends.show', $send)" wire:navigate>
                                            {{ $send->name }}
                                        </flux:link>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ \Illuminate\Support\Carbon::parse($send->valid_to)->timezone(config('app.timezone'))->format('M j, Y g:i A') }}
                                    </td>
                                    <td class="px-4 py-4 text-sm text-zinc-600 dark:text-zinc-400">
                                        {{ $send->authorizedUsers->pluck('email')->join(', ') ?: __('None') }}
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-right text-sm">
                                        <div class="inline-flex items-center justify-end gap-2">
                                            <flux:link :href="route('sends.show', $send)" wire:navigate>
                                                {{ __('View') }}
                                            </flux:link>
                                            <span
                                                x-data="copyText"
                                                data-copy-text="{{ route('sends.show', $send) }}"
                                                class="inline-flex"
                                            >
                                                <button
                                                    type="button"
                                                    @click="copy"
                                                    class="inline-flex cursor-pointer text-zinc-500 transition-colors hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200"
                                                    title="{{ __('Copy link') }}"
                                                >
                                                    <flux:icon.document-duplicate x-show="!copied" variant="outline" class="size-4" />
                                                    <flux:icon.check x-show="copied" variant="solid" class="size-4 text-green-500" />
                                                </button>
                                            </span>
                                            @can('forceDelete', $send)
                                                <form
                                                    method="POST"
                                                    action="{{ route('sends.destroy', $send) }}"
                                                    class="inline-flex"
                                                    onsubmit="return confirm(@js(__('Are you sure you want to delete this send?')))"
                                                >
                                                    @csrf
                                                    @method('DELETE')
                                                    <button
                                                        type="submit"
                                                        class="inline-flex cursor-pointer text-zinc-500 transition-colors hover:text-red-600 dark:text-zinc-400 dark:hover:text-red-400"
                                                        title="{{ __('Delete') }}"
                                                    >
                                                        <flux:icon.trash variant="outline" class="size-4" />
                                                    </button>
                                                </form>
                                            @endcan
                                        </div>
{{--                                        @can('update', $send)--}}
{{--                                            <span class="mx-2 text-zinc-300 dark:text-zinc-600">|</span>--}}
{{--                                            <flux:link :href="route('sends.edit', $send)" wire:navigate>--}}
{{--                                                {{ __('Edit') }}--}}
{{--                                            </flux:link>--}}
{{--                                        @endcan--}}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </flux:card>
        @endif
    </div>
</x-layouts::app>
