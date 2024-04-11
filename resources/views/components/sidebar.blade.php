<div class="main-sidebar sidebar-style-2">
    <aside id="sidebar-wrapper">
        <div class="sidebar-brand">
            <a href="index.html">POS - WARDEV STUDIO</a>
        </div>
        <div class="sidebar-brand sidebar-brand-sm">
            <a href="index.html">St</a>
        </div>
        <ul class="sidebar-menu">

            <li class="{{ Request::is('products') ? 'active' : '' }} nav-item ">
                <a class="nav-link" href="{{ route('products.index') }}">
                    <i class="fa fa-truck" aria-hidden="true"></i><span>Products</span>
                </a>
            </li>

            <li class="{{ Request::is('categories') ? 'active' : '' }} nav-item ">
                <a class="nav-link" href="{{ route('categories.index') }}">
                    <i class="fa fa-calendar"></i><span>Categories</span>
                </a>
            </li>

            <li class="nav-item {{ Request::is('user') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('user.index') }}">
                    <i class="fas fa-users"></i><span>Users</span>
                </a>
            </li>
        </ul>
    </aside>
</div>
