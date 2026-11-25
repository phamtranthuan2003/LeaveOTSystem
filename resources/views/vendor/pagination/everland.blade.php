@php
    $isLengthAware = method_exists($paginator, 'total') && method_exists($paginator, 'lastPage');
    $showBar = $paginator->hasPages() || ($isLengthAware && $paginator->total() > 0);
@endphp

@if ($showBar)
<nav class="pagination" role="navigation" aria-label="Phân trang">
    @if ($isLengthAware)
    <span class="page-info">
        Hiển thị <strong>{{ $paginator->firstItem() ?? 0 }}</strong>–<strong>{{ $paginator->lastItem() ?? 0 }}</strong>
        trong <strong>{{ $paginator->total() }}</strong> bản ghi
    </span>
    @endif

    @if ($paginator->onFirstPage())
        <span class="page-link disabled" aria-disabled="true">
            <svg class="icon icon-sm"><use href="#i-chevron-left"></use></svg>
        </span>
    @else
        <a class="page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Trang trước">
            <svg class="icon icon-sm"><use href="#i-chevron-left"></use></svg>
        </a>
    @endif

    @if ($isLengthAware && isset($elements))
        @if ($paginator->currentPage() > 3)
            <a class="page-link" href="{{ $paginator->url(1) }}">1</a>
            @if ($paginator->currentPage() > 4)
                <span class="page-dots">…</span>
            @endif
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="page-dots">{{ $element }}</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="page-link active" aria-current="page">{{ $page }}</span>
                    @else
                        <a class="page-link" href="{{ $url }}">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->currentPage() < $paginator->lastPage() - 2)
            @if ($paginator->currentPage() < $paginator->lastPage() - 3)
                <span class="page-dots">…</span>
            @endif
            <a class="page-link" href="{{ $paginator->url($paginator->lastPage()) }}">{{ $paginator->lastPage() }}</a>
        @endif
    @else
        <span class="page-link active">{{ $paginator->currentPage() }}</span>
    @endif

    @if ($paginator->hasMorePages())
        <a class="page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Trang sau">
            <svg class="icon icon-sm"><use href="#i-chevron-right"></use></svg>
        </a>
    @else
        <span class="page-link disabled" aria-disabled="true">
            <svg class="icon icon-sm"><use href="#i-chevron-right"></use></svg>
        </span>
    @endif
</nav>
@endif
