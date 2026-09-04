<x-app-layout>
    <x-slot name="header">Edit Agent</x-slot>

    <x-ui.card title="Update Agent Master" variant="primary">
        <form action="{{ route('masters.agents.update', $agent) }}" method="POST" novalidate class="js-agent-form">
            @csrf
            @method('PUT')
            @include('masters.agents._form', ['agent' => $agent])
        </form>
    </x-ui.card>
</x-app-layout>
