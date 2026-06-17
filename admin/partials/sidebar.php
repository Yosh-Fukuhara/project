<?php
$active = $active ?? '';
?>
<aside class="w-64 bg-white border-r border-slate-200 hidden md:flex md:flex-col">
    <div class="p-5 border-b border-slate-200">
        <a href="dashboard.php" class="flex items-center gap-3">
            <div class="w-10 h-10 bg-gradient-to-br from-blue-900 to-cyan-800 rounded-xl flex items-center justify-center">
                <span class="text-white font-bold">CS</span>
            </div>
            <div class="min-w-0">
                <p class="font-bold text-slate-900 leading-tight">CyberSphere</p>
                <p class="text-xs text-slate-500">Admin Panel</p>
            </div>
        </a>
    </div>

    <nav class="p-3 space-y-1">
        <a href="dashboard.php" class="<?php echo $active === 'dashboard' ? 'bg-blue-50 text-blue-900' : 'text-slate-700 hover:bg-slate-50'; ?> flex items-center gap-2 px-3 py-2 rounded-lg font-semibold">
            <span>Dashboard</span>
        </a>
        <a href="users.php" class="<?php echo $active === 'users' ? 'bg-blue-50 text-blue-900' : 'text-slate-700 hover:bg-slate-50'; ?> flex items-center gap-2 px-3 py-2 rounded-lg font-semibold">
            <span>Users</span>
        </a>
        <a href="employers.php" class="<?php echo $active === 'employers' ? 'bg-blue-50 text-blue-900' : 'text-slate-700 hover:bg-slate-50'; ?> flex items-center gap-2 px-3 py-2 rounded-lg font-semibold">
            <span>Employers</span>
        </a>
        <a href="posts.php" class="<?php echo $active === 'posts' ? 'bg-blue-50 text-blue-900' : 'text-slate-700 hover:bg-slate-50'; ?> flex items-center gap-2 px-3 py-2 rounded-lg font-semibold">
            <span>Posts</span>
        </a>
        <a href="job_posts.php" class="<?php echo $active === 'job_posts' ? 'bg-blue-50 text-blue-900' : 'text-slate-700 hover:bg-slate-50'; ?> flex items-center gap-2 px-3 py-2 rounded-lg font-semibold">
            <span>Job Posts</span>
        </a>
        <a href="job_applications.php" class="<?php echo $active === 'job_applications' ? 'bg-blue-50 text-blue-900' : 'text-slate-700 hover:bg-slate-50'; ?> flex items-center gap-2 px-3 py-2 rounded-lg font-semibold">
            <span>Job Applications</span>
        </a>
        <a href="community_posts.php" class="<?php echo $active === 'community_posts' ? 'bg-blue-50 text-blue-900' : 'text-slate-700 hover:bg-slate-50'; ?> flex items-center gap-2 px-3 py-2 rounded-lg font-semibold">
            <span>Community Posts</span>
        </a>
        <a href="product_categories.php" class="<?php echo $active === 'product_categories' ? 'bg-blue-50 text-blue-900' : 'text-slate-700 hover:bg-slate-50'; ?> flex items-center gap-2 px-3 py-2 rounded-lg font-semibold">
            <span>Product Categories</span>
        </a>
        <a href="products.php" class="<?php echo $active === 'products' ? 'bg-blue-50 text-blue-900' : 'text-slate-700 hover:bg-slate-50'; ?> flex items-center gap-2 px-3 py-2 rounded-lg font-semibold">
            <span>Products</span>
        </a>
        <a href="orders.php" class="<?php echo $active === 'orders' ? 'bg-blue-50 text-blue-900' : 'text-slate-700 hover:bg-slate-50'; ?> flex items-center gap-2 px-3 py-2 rounded-lg font-semibold">
            <span>Orders</span>
        </a>
        <a href="attachments.php" class="<?php echo $active === 'attachments' ? 'bg-blue-50 text-blue-900' : 'text-slate-700 hover:bg-slate-50'; ?> flex items-center gap-2 px-3 py-2 rounded-lg font-semibold">
            <span>Attachments</span>
        </a>
    </nav>

    <div class="mt-auto p-3 border-t border-slate-200">
        <a href="../index.php" class="block text-slate-700 hover:bg-slate-50 px-3 py-2 rounded-lg font-semibold">Back to site</a>
        <a href="logout.php" class="block text-red-600 hover:bg-red-50 px-3 py-2 rounded-lg font-semibold mt-1">Logout</a>
    </div>
</aside>

