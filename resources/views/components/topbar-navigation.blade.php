<div class="hidden lg:flex items-center space-x-1 mr-4">
    {{-- POS --}}
    <a href="{{ url('/admin/enhanced-p-o-s') }}" 
       class="group flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200
              {{ request()->is('admin/enhanced-p-o-s*') 
                  ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' 
                  : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}">
        <x-filament::icon 
            icon="heroicon-o-shopping-cart" 
            class="w-5 h-5 {{ request()->is('admin/pages/pos*') ? 'text-primary-600 dark:text-primary-400' : 'text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-200' }}" 
        />
        <span>POS</span>
    </a>

    {{-- Products --}}
    <a href="{{ url('/admin/products') }}" 
       class="group flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200
              {{ request()->is('admin/products*') 
                  ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' 
                  : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}">
        <x-filament::icon 
            icon="heroicon-o-cube" 
            class="w-5 h-5 {{ request()->is('admin/resources/products*') ? 'text-primary-600 dark:text-primary-400' : 'text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-200' }}" 
        />
        <span>Products</span>
    </a>

    {{-- Variants --}}
    <a href="{{ url('/admin/product-variants') }}" 
       class="group flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200
              {{ request()->is('admin/product-variants*') 
                  ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' 
                  : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}">
        <x-filament::icon 
            icon="heroicon-o-rectangle-stack" 
            class="w-5 h-5 {{ request()->is('admin/resources/product-variants*') ? 'text-primary-600 dark:text-primary-400' : 'text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-200' }}" 
        />
        <span>Variants</span>
    </a>

    {{-- Inventory --}}
    <a href="{{ url('/admin/inventory-list') }}" 
       class="group flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200
              {{ request()->is('admin/inventory*') 
                  ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' 
                  : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}">
        <x-filament::icon 
            icon="heroicon-o-cube-transparent" 
            class="w-5 h-5 {{ request()->is('admin/resources/product-batches*') ? 'text-primary-600 dark:text-primary-400' : 'text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-200' }}" 
        />
        <span>Inventory</span>
    </a>

    {{-- Customers --}}
    <a href="{{ url('/admin/customers') }}" 
       class="group flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200
              {{ request()->is('admin/customers*') 
                  ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' 
                  : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}">
        <x-filament::icon 
            icon="heroicon-o-users" 
            class="w-5 h-5 {{ request()->is('admin/resources/customers*') ? 'text-primary-600 dark:text-primary-400' : 'text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-200' }}" 
        />
        <span>Customers</span>
    </a>

    {{-- Suppliers --}}
    <a href="{{ url('/admin/suppliers') }}" 
       class="group flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200
              {{ request()->is('admin/suppliers*') 
                  ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' 
                  : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}">
        <x-filament::icon 
            icon="heroicon-o-rectangle-stack" 
            class="w-5 h-5 {{ request()->is('admin/resources/suppliers*') ? 'text-primary-600 dark:text-primary-400' : 'text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-200' }}" 
        />
        <span>Suppliers</span>
    </a>

    {{-- Sales --}}
    <a href="{{ url('/admin/sales') }}" 
       class="group flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200
              {{ request()->is('admin/sales*') 
                  ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' 
                  : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}">
        <x-filament::icon 
            icon="heroicon-o-shopping-cart" 
            class="w-5 h-5 {{ request()->is('admin/resources/sales*') ? 'text-primary-600 dark:text-primary-400' : 'text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-200' }}" 
        />
        <span>Sales</span>
    </a>

    {{-- Purchases --}}
    <a href="{{ url('/admin/purchases') }}" 
       class="group flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200
              {{ request()->is('admin/purchases*') 
                  ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' 
                  : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}">
        <x-filament::icon 
            icon="heroicon-o-shopping-bag" 
            class="w-5 h-5 {{ request()->is('admin/resources/purchases*') ? 'text-primary-600 dark:text-primary-400' : 'text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-200' }}" 
        />
        <span>Purchases</span>
    </a>

    {{-- Divider --}}
    <div class="h-6 w-px bg-gray-300 dark:bg-gray-600 mx-2"></div>

    {{-- Reports --}}
    <a href="{{ url('/admin/sales-report') }}" 
       class="group flex items-center gap-2 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-200
              {{ request()->is('admin/*report*') 
                  ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-400' 
                  : 'text-gray-700 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800' }}">
        <x-filament::icon 
            icon="heroicon-o-chart-bar" 
            class="w-5 h-5 {{ request()->is('admin/pages/*report*') ? 'text-primary-600 dark:text-primary-400' : 'text-gray-500 group-hover:text-gray-700 dark:text-gray-400 dark:group-hover:text-gray-200' }}" 
        />
        <span>Reports</span>
    </a>

    {{-- Quick Search Hint (Optional) --}}
    <div class="ml-2 hidden xl:flex items-center gap-1.5 px-2.5 py-1.5 text-xs text-gray-500 dark:text-gray-400 bg-gray-100 dark:bg-gray-800 rounded-md border border-gray-200 dark:border-gray-700">
        <x-filament::icon icon="heroicon-o-magnifying-glass" class="w-3.5 h-3.5" />
        <span>Press</span>
        <kbd class="px-1.5 py-0.5 text-xs font-semibold bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 rounded">⌘K</kbd>
    </div>
</div>
