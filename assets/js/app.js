// Basic JS for symptom chips and tooltip on calendar

document.addEventListener('DOMContentLoaded', () => {
    // Symptom chip toggles
    document.querySelectorAll('.chip-toggle').forEach(chip => {
        chip.addEventListener('click', () => {
            chip.classList.toggle('active');
            const checkbox = document.getElementById(chip.dataset.for);
            if (checkbox) {
                checkbox.checked = chip.classList.contains('active');
            }
        });
    });

    // Tooltip for calendar cells
    const tooltip = document.querySelector('.tooltip');
    if (tooltip) {
        document.querySelectorAll('.calendar-cell').forEach(cell => {
            cell.addEventListener('mouseenter', (e) => {
                const text = cell.dataset.tooltip;
                if (!text) return;
                tooltip.textContent = text;
                tooltip.classList.add('visible');
                moveTooltip(e);
            });
            cell.addEventListener('mousemove', moveTooltip);
            cell.addEventListener('mouseleave', () => {
                tooltip.classList.remove('visible');
            });
        });
    }

    function moveTooltip(e) {
        const tooltip = document.querySelector('.tooltip');
        if (!tooltip) return;
        const offset = 12;
        tooltip.style.left = e.pageX + offset + 'px';
        tooltip.style.top = e.pageY + offset + 'px';
    }

    // Sidebar navigation: show only selected main section
    const navLinks = document.querySelectorAll('.nav-list .nav-item a[data-section]');
    const sections = document.querySelectorAll('.main-section');

    navLinks.forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = link.getAttribute('data-section');
            if (!targetId) return;

            // Toggle active nav state
            navLinks.forEach(l => l.classList.remove('active'));
            link.classList.add('active');

            // Show selected section only
            sections.forEach(sec => {
                if (sec.id === targetId) {
                    sec.classList.add('active');
                    // optional scroll to top of main for better UX
                    window.scrollTo({ top: 0, behavior: 'smooth' });
                } else {
                    sec.classList.remove('active');
                }
            });
        });
    });
});


