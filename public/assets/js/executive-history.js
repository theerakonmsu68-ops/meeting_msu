
document.getElementById('toggle-sidebar')?.addEventListener('click', function () {
    const sidebar = document.querySelector('.sidebar') || document.querySelector('.sidebar-wrapper');
    const mainContent = document.getElementById('mainContent');
    if (!sidebar) return;

    sidebar.classList.toggle('collapsed');

    if (!window.matchMedia('(max-width: 768px)').matches) {
        mainContent?.classList.toggle('expanded', sidebar.classList.contains('collapsed'));
    } else {
        mainContent?.classList.remove('expanded');
    }
});
if (window.lucide) window.lucide.createIcons();
