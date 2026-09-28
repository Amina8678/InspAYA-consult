{{--
    Centred section heading (original style): small uppercase label, heading,
    optional intro. Parameters are prefixed because @include inherits the
    parent's variables (a parent's $tag or $id must never leak in):
    $sectionHeading, $sectionId (for aria-labelledby), optional
    $sectionEyebrow, $sectionIntro, $sectionTag (default h2).
--}}
@php($sectionTag = $sectionTag ?? 'h2')
<div class="section-header">
    @if (! empty($sectionEyebrow))
        <p class="eyebrow">{{ $sectionEyebrow }}</p>
    @endif
    <{{ $sectionTag }} id="{{ $sectionId }}">{{ $sectionHeading }}</{{ $sectionTag }}>
    @if (! empty($sectionIntro))
        <p class="lead">{{ $sectionIntro }}</p>
    @endif
</div>
