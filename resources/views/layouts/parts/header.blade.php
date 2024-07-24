<header id="header" component="header-mobile-toggle" class="primary-background px-xl grid print-hidden">
    <div class="flex-container-row justify-space-between gap-s items-center">
        @include('layouts.parts.header-logo')
        <div class="hide-over-l py-s">
            <button type="button"
                    refs="header-mobile-toggle@toggle"
                    title="{{ trans('common.header_menu_expand') }}"
                    aria-expanded="false"
                    class="mobile-menu-toggle">@icon('more')</button>
        </div>
    </div>

    <div class="flex-container-column items-center justify-center hide-under-l">
    @if(user()->hasAppAccess())
        @include('layouts.parts.header-search')
    @endif
    </div>

    <nav refs="header-mobile-toggle@menu" class="header-links">
        <div class="links text-center">
            @include('layouts.parts.header-links')
        </div>
        @if(!user()->isGuest())
            @include('layouts.parts.header-user-menu', ['user' => user()])
        @endif
    </nav>
</header>
