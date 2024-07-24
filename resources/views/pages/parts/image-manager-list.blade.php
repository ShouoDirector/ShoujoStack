@if($warning ?? '')
    <div class="image-manager-list-warning image-manager-warning px-m py-xs flex-container-row gap-xs items-center">
        <div>@icon('warning')</div>
        <div class="flex">{{ $warning }}</div>
    </div>
@endif
@foreach($images as $index => $image)
<div>
    <button component="event-emit-select"
         option:event-emit-select:name="image"
         option:event-emit-select:data="{{ json_encode($image) }}"
         class="image anim fadeIn text-link"
         style="animation-delay: {{ min($index * 10, 260) . 'ms' }};">
        <img src="{{ $image->thumbs['gallery'] ?? '' }}"
             alt="{{ $image->name }}"
             role="none"
             width="150"
             height="150"
             loading="lazy">
        <div class="image-meta">
            <span class="name">{{ $image->name }}</span>
            <span class="date">{{ trans('components.image_uploaded', ['uploadedDate' => $image->created_at->format('Y-m-d')]) }}</span>
        </div>
    </button>
</div>
@endforeach
@if(count($images) === 0)
    <p class="m-m text-bigger italic text-muted">{{ trans('common.no_items') }}</p>
@endif
@if($hasMore)
    <div class="load-more">
        <button type="button" class="button small outline">{{ trans('components.image_load_more') }}</button>
    </div>
@endif