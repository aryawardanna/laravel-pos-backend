<div class="main-sidebar sidebar-style-2">
    <aside id="sidebar-wrapper">
        <div class="sidebar-brand">
            <a href="index.html">DISENJA</a>
        </div>
        <div class="sidebar-brand sidebar-brand-sm">
            <a href="index.html">RW</a>
        </div>
        <ul class="sidebar-menu">
            <li class="{{ Request::is('home*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ url('/home') }}"><i class="fa fa-home"></i><span>Dashboard</span></a>
            </li>

            {{-- <li class="{{ Request::is('product*') ? 'active' : '' }}">
                <a class="nav-link" href="{{ route('product.index') }}"><i class="fa fa-archive" aria-hidden="true"></i><span>Products</span></a>
            </li> --}}
            <li class="dropdown">
                <a href="#" class="nav-link has-dropdown"><i class="fas fa-shopping-cart ml-0"></i> <span>Transaksi</span></a>
                <ul class="dropdown-menu">
                    <li class="{{ Request::is('user*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('user.index') }}"><i class="fas fa-laptop"></i><span>Penjualan / Kasir</span></a>
                    </li>
                    <li class="{{ Request::is('user*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('user.index') }}"><i class="fas fa-money-bill"></i></i><span>Pembelian</span></a>
                    </li>
                </ul>
            </li>
            <li class="dropdown">
                <a href="#" class="nav-link has-dropdown"><i class="fas fa-warehouse ml-0"></i> <span>Inventory</span></a>
                <ul class="dropdown-menu">
                    <li class="{{ Request::is('category*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('category.index') }}"><i class="fab fa-stack-exchange"></i></i><span>Stok Bahan Baku</span></a>
                    </li>
                    <li class="{{ Request::is('category*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('category.index') }}"><i class="fas fa-chart-line"></i></i><span>Kartu Stok</span></a>
                    </li>
                    <li class="{{ Request::is('category*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('category.index') }}"><i class="far fa-clipboard"></i><span>Stock Opname</span></a>
                    </li>
                </ul>
            </li>
            <li class="dropdown">
                <a href="#" class="nav-link has-dropdown"><i class="far fa-file-excel ml-0"></i> <span>Laporan</span></a>
                <ul class="dropdown-menu">
                    <li class="{{ Request::is('category*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('category.index') }}"><i class="far fa-chart-bar"></i></i><span>Penjualan</span></a>
                    </li>
                    <li class="{{ Request::is('category*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('category.index') }}"><i class="fas fa-box-open"></i><span>Barang Masuk</span></a>
                    </li>
                    <li class="{{ Request::is('category*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('category.index') }}"><i class="fas fa-boxes"></i><span>Stok</span></a>
                    </li>
                </ul>
            </li>
            <li class="dropdown">
                <a href="#" class="nav-link has-dropdown"><i class="fas fa-database ml-0"></i> <span>Master Data</span></a>
                <ul class="dropdown-menu">
                    <li class="{{ Request::is('category*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('category.index') }}"><i class="fas fa-layer-group"></i><span>Kategori Menu</span></a>
                    </li>
                    <li class="{{ Request::is('category*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('category.index') }}"><i class="fas fa-utensils"></i><span>Produk / Menu</span></a>
                    </li>
                    <li class="{{ Request::is('category*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('category.index') }}"><i class="fa fa-list" aria-hidden="true"></i><span>Bahan Baku</span></a>
                    </li>
                    <li class="{{ Request::is('satuan*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('satuan.index') }}"><i class="fas fa-i-cursor"></i><span>Satuan</span></a>
                    </li>
                    <li class="{{ Request::is('supplier*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('supplier.index') }}"><i class="fas fa-building"></i><span>Supplier</span></a>
                    </li>
                </ul>
            </li>
            <li class="dropdown">
                <a href="#" class="nav-link has-dropdown"><i class="fas fa-cog ml-0"></i> <span>Pengaturan</span></a>
                <ul class="dropdown-menu">
                    <li class="{{ Request::is('user*') ? 'active' : '' }}">
                        <a class="nav-link" href="{{ route('user.index') }}"><i class="fa fa-users" aria-hidden="true"></i><span>Users</span></a>
                    </li>
                </ul>
            </li>

        </ul>
    </aside>
</div>
