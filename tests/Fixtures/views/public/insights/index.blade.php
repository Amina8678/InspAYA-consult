{{-- Test stub for public.insights.index: real views are owned by the frontend. --}}
stub:public.insights.index|{{ $seo['title'] }}
@foreach ($posts as $post){{ $post['title'] }}
@endforeach
{{ $posts->links() }}
