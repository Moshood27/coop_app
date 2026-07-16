<style>
    /* Global Rounding & Aesthetics for Fintech Look */
    :root {
        --filament-widgets-chart-border-radius: 1.25rem;
        --fi-topbar-height: 4rem;
    }

    .fi-main-ctn {
        background-color: #f8fafc; /* Softer background */
    }

    .dark .fi-main-ctn {
        background-color: #020617; /* Deep Navy for premium feel */
    }

    /* Cards & Sections */
    .fi-section, .fi-card, .fi-ta-ctn {
        border-radius: 1.25rem !important;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.03), 0 1px 2px -1px rgba(0, 0, 0, 0.03) !important;
        border: 1px solid rgba(0, 0, 0, 0.1) !important;
    }

    .dark .fi-section, .dark .fi-card, .dark .fi-ta-ctn {
        background-color: #0f172a !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.2) !important;
    }

    .fi-section-header {
        border-bottom: 1px solid rgba(0, 0, 0, 0.03) !important;
    }

    /* Sidebar Refinement */
    .fi-sidebar {
        background-color: white !important;
        border-right: 1px solid rgba(0, 0, 0, 0.05) !important;
    }

    .dark .fi-sidebar {
        background-color: #0f172a !important;
        border-right: 1px solid rgba(255, 255, 255, 0.05) !important;
    }

    .fi-sidebar-header {
        border-bottom: 1px solid rgba(0, 0, 0, 0.03) !important;
        padding-top: 0.5rem !important;
        padding-bottom: 0.5rem !important;
    }

    .dark .fi-sidebar-header {
        border-bottom-color: rgba(255, 255, 255, 0.05) !important;
    }

    .fi-sidebar-item-button {
        border-radius: 0.75rem !important;
        margin: 0.125rem 0.5rem !important;
        transition: all 0.2s;
    }

    .fi-sidebar-group-label {
        letter-spacing: 0.05em;
        font-weight: 700;
        text-transform: uppercase;
        font-size: 0.7rem;
        color: #94a3b8;
        padding-left: 1.25rem !important;
        margin-top: 1.5rem !important;
    }

    /* Input Fields */
    .fi-fo-text-input, .fi-fo-select, .fi-fo-textarea, .fi-fo-datetime-picker, .fi-input-wrp {
        border-radius: 0.75rem !important;
        border: 1px solid #d1d5db !important; /* Slate-300 for better visibility */
        transition: all 0.2s ease-in-out !important;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
    }

    .fi-fo-text-input:focus-within, .fi-input-wrp:focus-within {
        border-color: #6366f1 !important; /* Indigo-500 focus */
        box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1) !important;
    }

    .dark .fi-fo-text-input, .dark .fi-fo-select, .dark .fi-fo-textarea, .dark .fi-fo-datetime-picker, .dark .fi-input-wrp {
        background-color: #1e293b !important;
        border-color: #475569 !important; /* Slate-600 for dark mode */
        color: white !important;
    }

    .dark .fi-fo-text-input:focus-within, .dark .fi-input-wrp:focus-within {
        border-color: #818cf8 !important; /* Indigo-400 focus */
        box-shadow: 0 0 0 3px rgba(129, 140, 248, 0.15) !important;
    }

    /* Buttons */
    .fi-btn {
        border-radius: 0.75rem !important;
        font-weight: 600;
        letter-spacing: -0.01em;
        transition: all 0.2s;
    }

    /* Navbar (Topbar) Design & Dropdown Fixes */
    .fi-topbar {
        background-color: rgba(255, 255, 255, 0.8) !important;
        backdrop-filter: blur(12px) !important;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05) !important;
        position: sticky !important;
        top: 0 !important;
        z-index: 40 !important;
        height: 4rem !important;
        display: flex !important;
        align-items: center !important;
        overflow: visible !important;
    }

    .dark .fi-topbar {
        background-color: rgba(15, 23, 42, 0.9) !important;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1) !important;
    }

    .fi-topbar > div {
        width: 100% !important;
        overflow: visible !important;
    }

    .fi-topbar-items-ctn {
        padding-left: 1rem !important;
        padding-right: 1rem !important;
        overflow: visible !important;
    }

    /* Topbar Icons & Buttons */
    .fi-topbar-item-button, .fi-icon-btn {
        border-radius: 0.75rem !important;
        transition: all 0.2s !important;
    }

    .fi-topbar-item-button:hover, .fi-icon-btn:hover {
        background-color: rgba(0, 0, 0, 0.03) !important;
    }

    .dark .fi-topbar-item-button:hover, .dark .fi-icon-btn:hover {
        background-color: rgba(255, 255, 255, 0.05) !important;
    }

    /* Theme Switcher & Dropdown Visibility Fix */
    /* Ensure dropdowns appear above the topbar and are positioned correctly */
    .fi-dropdown-panel {
        z-index: 9999 !important;
        border-radius: 1rem !important;
        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04) !important;
        border: 1px solid rgba(0, 0, 0, 0.1) !important;
        margin-top: 1rem !important;
    }

    .dark .fi-dropdown-panel {
        background-color: #1e293b !important;
        border-color: rgba(255, 255, 255, 0.1) !important;
    }

    /* Topbar Search */
    .fi-global-search-input-ctn input {
        border-radius: 0.75rem !important;
        background-color: #f1f5f9 !important;
        border: none !important;
        padding-left: 2.5rem !important;
        transition: all 0.2s;
    }

    .dark .fi-global-search-input-ctn input {
        background-color: #1e293b !important;
        color: white !important;
    }

    .fi-global-search-input-ctn input:focus {
        background-color: white !important;
        box-shadow: 0 0 0 2px rgba(99, 102, 241, 0.2) !important;
        border: 1px solid rgb(99, 102, 241) !important;
    }

    .dark .fi-global-search-input-ctn input:focus {
        background-color: #334155 !important;
    }

    /* Dashboard Widgets */
    .fi-wi-stats-overview-stat {
        border-radius: 1.25rem !important;
        border: 1px solid rgba(0, 0, 0, 0.05) !important;
        background-color: white !important;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05) !important;
    }

    .dark .fi-wi-stats-overview-stat {
        background-color: #0f172a !important;
        border-color: rgba(255, 255, 255, 0.05) !important;
    }

    /* Table refinement */
    .fi-ta-table {
        border-spacing: 0 0.5rem !important;
        border-collapse: separate !important;
    }

    .fi-ta-row {
        background-color: white !important;
        transition: transform 0.2s, box-shadow 0.2s;
    }

    .dark .fi-ta-row {
        background-color: #0f172a !important;
    }

    .fi-ta-row:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05) !important;
    }

    /* Badges */
    .fi-badge {
        border-radius: 9999px !important;
        padding: 0.25rem 0.75rem !important;
        font-weight: 600 !important;
    }

    /* Hide Tawk.to on Data Entry & Mobile */
    .fi-resource-create-page iframe[src*="tawk.to"],
    .fi-resource-edit-page iframe[src*="tawk.to"],
    .fi-resource-create-page .tawk-min-container,
    .fi-resource-edit-page .tawk-min-container {
        display: none !important;
    }

    @media (max-width: 768px) {
        iframe[src*="tawk.to"], .tawk-min-container {
            display: none !important;
        }
    }
</style>
