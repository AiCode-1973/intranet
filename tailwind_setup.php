<script src="https://cdn.tailwindcss.com?plugins=typography,aspect-ratio"></script>
<script src="https://unpkg.com/lucide@latest"></script>
<script>
    tailwind.config = {
        theme: {
            extend: {
                colors: {
                    primary: 'var(--color-primary)',
                    'primary-hover': 'var(--color-primary-hover)',
                    background: 'var(--color-background)',
                    surface: 'var(--color-surface)',
                    'surface-hover': 'var(--color-surface-hover)',
                    border: 'var(--color-border)',
                    text: {
                        DEFAULT: 'var(--color-text-primary)',
                        muted: 'var(--color-text-secondary)',
                        inverted: 'var(--color-text-inverted)',
                    },
                    'sidebar': 'var(--color-sidebar)',
                    'card-blue': 'var(--color-card-blue, #cbdfe8)',
                    'card-mint': 'var(--color-card-mint, #d9ede6)',
                    'card-orange': 'var(--color-card-orange, #f8ebd5)',
                    'card-cyan': 'var(--color-card-cyan, #d6f2f7)',
                    // Mappings for backward compatibility
                    'background-dark': 'var(--color-background)', 
                    'background-light': 'var(--color-text-primary)',
                    'card-dark': 'var(--color-surface)',
                    'border-dark': 'var(--color-border)',
                }
            }
        }
    }
</script>
<style>
    :root {
        /* Default Theme (Dark/Hacker - Green) */
        --color-primary: #13ec6a;
        --color-primary-hover: #0eb857;
        --color-background: #102217;
        --color-surface: #162b20;
        --color-surface-hover: #1c3628;
        --color-border: #234832;
        --color-text-primary: #f6f8f7;
        --color-text-secondary: #9ca3af;
        --color-text-inverted: #000000;
    }

    [data-theme="hospital"] {
        /* Hospital Theme (Inspired by Reference) */
        --color-primary: #008eb4; 
        --color-primary-hover: #007a9b;
        --color-sidebar: #007291;
        --color-background: #e6f2f5;
        --color-surface: #ffffff;
        --color-surface-hover: #f8fafc;
        --color-border: #d1e2e6;
        --color-text-primary: #2c4e58;
        --color-text-secondary: #5e808a;
        --color-text-inverted: #ffffff;
        
        /* Card Background Tones */
        --color-card-blue: #cbdfe8;
        --color-card-mint: #d9ede6;
        --color-card-orange: #f8ebd5;
        --color-card-cyan: #d6f2f7;
    }

    body {
        background-color: var(--color-background);
        color: var(--color-text-primary);
        transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease;
    }

    /* Common overrides */
    a { color: var(--color-primary); transition: color 0.2s; }
    a:hover { color: var(--color-primary-hover); }
    
    .bg-card { background-color: var(--color-surface); }
    .border-custom { border-color: var(--color-border); }
    .no-scrollbar::-webkit-scrollbar {
        display: none;
    }
    .no-scrollbar {
        -ms-overflow-style: none;
        scrollbar-width: none;
    }
</style>
<script>
    // Theme Management
    function toggleTheme() {
        const html = document.documentElement;
        const currentTheme = html.getAttribute('data-theme');
        const newTheme = currentTheme === 'hospital' ? 'default' : 'hospital';
        
        html.setAttribute('data-theme', newTheme);
        localStorage.setItem('theme', newTheme);
        
        // Update theme icon
        const icon = document.getElementById('theme-icon');
        if (icon) {
            if (newTheme === 'hospital') {
                icon.setAttribute('data-lucide', 'sun');
            } else {
                icon.setAttribute('data-lucide', 'moon');
            }
        }
        
        // Refresh icons
        setTimeout(() => lucide.createIcons(), 10);
    }

    // Initialize Theme
    (function() {
        const savedTheme = localStorage.getItem('theme') || 'hospital';
        document.documentElement.setAttribute('data-theme', savedTheme);
        
        // Initial icon creation
        window.addEventListener('DOMContentLoaded', () => {
            lucide.createIcons();
        });
    })();
</script>
