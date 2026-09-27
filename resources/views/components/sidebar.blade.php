@php
    // Menu diambil dari config/menu.php lalu disaring sesuai hak access user
    // (lihat App\Support\Menu). Grup tanpa menu yang terlihat tidak dirender.
    $menuTree = $menuTree ?? app(\App\Support\Menu::class)->tree(auth()->user());

    // Menu aktif hanya bila URL sama persis atau berada di bawahnya.
    // Pakai "/*" (bukan "*") supaya "menu" tidak ikut menyalakan "menu-access".
    $isActive = fn (?string $uri) => $uri && (Request::is($uri) || Request::is($uri . '/*'));
@endphp
<div class="main-sidebar sidebar-style-2">
    <aside id="sidebar-wrapper">
        <div class="sidebar-brand">
            <a href="index.html">DISENJA</a>
        </div>
        <div class="sidebar-brand sidebar-brand-sm">
            <a href="index.html">RW</a>
        </div>
        <ul class="sidebar-menu">
            @foreach ($menuTree as $menu)
                @if (empty($menu['items']))
                    <li class="{{ $isActive($menu['uri']) ? 'active' : '' }}">
                        <a class="nav-link" href="{{ $menu['route'] ? route($menu['route']) : '#' }}"><i class="{{ $menu['icon'] }}"></i><span>{{ $menu['label'] }}</span></a>
                    </li>
                @else
                    <li class="dropdown">
                        <a href="#" class="nav-link has-dropdown"><i class="{{ $menu['icon'] }}"></i> <span>{{ $menu['label'] }}</span></a>
                        <ul class="dropdown-menu">
                            @foreach ($menu['items'] as $item)
                                <li class="{{ $isActive($item['uri']) ? 'active' : '' }}">
                                    <a class="nav-link" href="{{ $item['route'] ? route($item['route']) : '#' }}"><i class="{{ $item['icon'] }}"></i><span>{{ $item['label'] }}</span></a>
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endif
            @endforeach
        </ul>
    </aside>
</div>
