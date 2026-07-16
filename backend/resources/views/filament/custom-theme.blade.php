<style>
    /* Global Rounding & Aesthetics for Fintech Look */
    :root {
        --filament-widgets-chart-border-radius: 1.25rem;
    }

    .fi-main-ctn {
        background-color: #f9fafb; /* Slightly softer background */
    }

    /* Cards & Sections */
    .fi-section, .fi-card, .fi-ta-ctn {
        border-radius: 1.25rem !important;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05), 0 1px 2px -1px rgba(0, 0, 0, 0.05) !important;
        border: 1px solid rgba(0, 0, 0, 0.05) !important;
    }

    .fi-section-header {
        border-bottom: 1px solid rgba(0, 0, 0, 0.03) !important;
    }

    /* Sidebar Refinement */
    .fi-sidebar {
        background-color: white !important;
        border-right: 1px solid rgba(0, 0, 0, 0.05) !important;
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
        margin-top: 1rem !important;
    }

    /* Input Fields */
    .fi-fo-text-input, .fi-fo-select, .fi-fo-textarea, .fi-fo-datetime-picker {
        border-radius: 0.75rem !important;
        border-color: rgba(0, 0, 0, 0.1) !important;
        transition: all 0.2s;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05) !important;
    }

    /* Buttons */
    .fi-btn {
        border-radius: 0.75rem !important;
        font-weight: 600;
        letter-spacing: -0.01em;
        transition: all 0.2s;
        padding-top: 0.625rem !important;
        padding-bottom: 0.625rem !important;
    }

    /* Topbar */
    .fi-topbar {
        background-color: rgba(255, 255, 255, 0.8) !important;
        backdrop-filter: blur(12px) !important;
        border-bottom: 1px solid rgba(0, 0, 0, 0.05) !important;
    }

    /* Dashboard Widgets */
    .fi-wi-stats-overview-stat {
        border-radius: 1.25rem !important;
        border: 1px solid rgba(0, 0, 0, 0.05) !important;
        background-color: white !important;
        box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.05) !important;
    }

    /* Modal Styling */
    .fi-modal-window {
        border-radius: 1.5rem !important;
        box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25) !important;
    }

    /* Table refinement */
    .fi-ta-header-ctn {
        padding: 1.25rem !important;
    }

    .fi-ta-table {
        border-spacing: 0 0.5rem !important;
        border-collapse: separate !important;
    }

    .fi-ta-row {
        background-color: white !important;
        transition: transform 0.2s, box-shadow 0.2s;
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

    /* Table refinement */
    .fi-ta-header-cell-label {
        text-transform: uppercase;
        font-size: 0.7rem;
        letter-spacing: 0.05em;
        font-weight: 700;
        color: #64748b;
    }

    /* Stats Widget refinement */
    .fi-wi-stats-overview-stat-label {
        color: #64748b !important;
        font-weight: 600 !important;
        font-size: 0.875rem !important;
    }

    .fi-wi-stats-overview-stat-value {
        letter-spacing: -0.025em !important;
    }

    /* Topbar search */
    .fi-global-search-input-ctn input {
        border-radius: 0.75rem !important;
        background-color: #f1f5f9 !important;
        border: none !important;
    }

    /* Hide Tawk.to widget on mobile and data-entry views */
    @media (max-width: 1024px) {
        .tawk-min-container, [id^="tawk-"], iframe[src*="tawk.to"] {
            display: none !important;
        }
    }

    /* Also hide on any create/edit resource pages to prevent overlap with actions */
    .fi-resource-create-page .tawk-min-container,
    .fi-resource-edit-page .tawk-min-container,
    .fi-resource-create-page [id^="tawk-"],
    .fi-resource-edit-page [id^="tawk-"],
    .fi-resource-create-page iframe[src*="tawk.to"],
    .fi-resource-edit-page iframe[src*="tawk.to"] {
        display: none !important;
    }
</style>
