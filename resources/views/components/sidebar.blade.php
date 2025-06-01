<div class="main-sidebar sidebar-style-2">
    <aside id="sidebar-wrapper">
        <div class="sidebar-brand">
            <a href="index.html">RESTO WARDEV</a>
        </div>
        <div class="sidebar-brand sidebar-brand-sm">
            <a href="index.html">RW</a>
        </div>
        <ul class="sidebar-menu">
            <li class="{{ Request::is('/') ? 'active' : '' }}">
                <a class="nav-link" href="{{ url('/') }}"><i class="fa fa-home"></i><span>Dashboard</span></a>
            </li>
            <li class="{{ Request::is('/users') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('user.index') }}"><i class="fa fa-users" aria-hidden="true"></i><span>Users</span></a>
            </li>
            <li class="{{ Request::is('/users') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('user.index') }}"><i class="fa fa-list" aria-hidden="true"></i><span>Categories</span></a>
            </li>
            <li class="{{ Request::is('/users') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('user.index') }}"><i class="fa fa-archive" aria-hidden="true"></i><span>Products</span></a>
            </li>
        </ul>
    </aside>
</div>
