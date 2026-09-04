<x-app-layout>
    <x-slot name="header">Add Agent</x-slot>

    <x-ui.card title="Create Agent Master" variant="primary">
        {{-- novalidate: TomSelect hides the native <select>, and Chrome then
             fails HTML5 "required" on an empty/hidden control even when the
             user filled the visible field. Laravel still validates server-side. --}}
        <form action="{{ route('masters.agents.store') }}" method="POST" novalidate class="js-agent-form">
            @csrf
            @include('masters.agents._form')
        </form>
    </x-ui.card>
</x-app-layout>
