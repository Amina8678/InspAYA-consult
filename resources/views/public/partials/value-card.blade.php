{{-- Core value card: icon circle (uploaded icon, or a check mark), title, text. --}}
<article class="card">
    <span class="iconbox">
        @if ($value['icon'])
            @include('public.partials.image', ['image' => $value['icon']])
        @else
            @include('public.partials.icon', ['name' => 'check'])
        @endif
    </span>
    <{{ $headingTag }}>{{ $value['title'] }}</{{ $headingTag }}>
    @if ($value['description'])
        <p class="prose">{{ $value['description'] }}</p>
    @endif
</article>
