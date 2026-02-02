<?php
// PDF-optimized styles that maintain the same visual design
if ($print_mode) {
?>
<style>
/* PDF-Optimized Styles - Maintaining Original Design */
:root {
    --wpmu-primary: #003366;
    --wpmu-primary-light: #E6F3FF;
    --wpmu-secondary: #FFCC00;
    --wpmu-secondary-light: #FFF9E6;
    --wpmu-accent: #17A2B8;
    --wpmu-danger: #DC3545;
    --wpmu-warning: #FFC107;
    --wpmu-success: #28a745;
    --wpmu-white: #FFFFFF;
    --wpmu-gray-100: #F8F9FA;
    --wpmu-gray-200: #E9ECEF;
    --wpmu-gray-300: #DEE2E6;
    --wpmu-gray-600: #6C757D;
    --wpmu-gray-900: #212529;
}

body {
    font-family: Arial, sans-serif;
    background: #f8f9fa;
    color: var(--wpmu-gray-900);
    margin: 0;
    line-height: 1.65;
    font-size: 14px;
    padding: 0;
}

.container {
    max-width: 100%;
    margin: 0 auto;
    padding: 20px;
}

/* Hero Header - Maintain Original Design */
.hero-header {
    background: var(--wpmu-primary);
    color: var(--wpmu-white);
    padding: 40px 30px;
    border-radius: 24px;
    margin-bottom: 32px;
}

.hero-content {
    position: relative;
    z-index: 2;
}

.hero-top {
    display: block;
    margin-bottom: 24px;
    text-align: center;
}

.hero-logo .logo-text {
    font-size: 24px;
    font-weight: 900;
    color: var(--wpmu-secondary);
    text-transform: uppercase;
    letter-spacing: 1px;
}

.brand-tag {
    background: rgba(255, 204, 0, 0.2);
    color: var(--wpmu-secondary);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-top: 8px;
    display: inline-block;
}

.hero-status {
    text-align: right;
}

.status-badge {
    background: rgba(40, 167, 69, 0.2);
    color: #00D084;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: 1px solid rgba(0, 208, 132, 0.3);
}

.title-main {
    font-size: 32px;
    font-weight: 900;
    color: var(--wpmu-white);
    margin-bottom: 8px;
    line-height: 1.2;
}

.title-sub {
    font-size: 16px;
    color: rgba(255, 255, 255, 0.8);
    font-weight: 400;
    margin-bottom: 24px;
}

.info-grid {
    display: block;
}

.info-item {
    display: block;
    background: rgba(255, 255, 255, 0.1);
    padding: 12px 16px;
    border-radius: 12px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    margin-bottom: 12px;
}

.info-icon {
    font-size: 20px;
    width: 24px;
    text-align: center;
}

.info-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    opacity: 0.8;
    margin-bottom: 4px;
}

.info-value {
    font-size: 14px;
    font-weight: 600;
    color: var(--wpmu-white);
}

/* Executive Summary - Enhanced Cards */
.executive-summary {
    background: var(--wpmu-gray-100);
    border: 1px solid var(--wpmu-gray-200);
    border-radius: 24px;
    padding: 32px;
    margin-bottom: 32px;
}

.summary-title {
    font-size: 24px;
    font-weight: 900;
    color: var(--wpmu-primary);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.summary-icon {
    font-size: 28px;
}

.summary-subtitle {
    color: var(--wpmu-gray-600);
    font-size: 16px;
    margin-bottom: 24px;
}

.summary-grid {
    display: block;
    margin-bottom: 24px;
}

.summary-card {
    display: block;
    width: 100%;
    background: var(--wpmu-white);
    border-radius: 16px;
    padding: 20px;
    text-align: center;
    border: 2px solid transparent;
    margin-bottom: 16px;
    page-break-inside: avoid;
}

.summary-card.critical {
    border-color: var(--wpmu-danger);
    background: linear-gradient(135deg, #fff5f5 0%, #ffffff 100%);
}

.summary-card.medium {
    border-color: var(--wpmu-warning);
    background: linear-gradient(135deg, #fffbf0 0%, #ffffff 100%);
}

.summary-card.low {
    border-color: var(--wpmu-success);
    background: linear-gradient(135deg, #f0fff4 0%, #ffffff 100%);
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 12px;
}

.card-icon {
    font-size: 24px;
}

.card-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.card-badge.critical {
    background: var(--wpmu-danger);
    color: white;
}

.card-badge.medium {
    background: var(--wpmu-warning);
    color: #000;
}

.card-badge.low {
    background: var(--wpmu-success);
    color: white;
}

.card-number {
    font-size: 32px;
    font-weight: 900;
    margin-bottom: 8px;
    line-height: 1;
}

.summary-card.critical .card-number {
    color: var(--wpmu-danger);
}

.summary-card.medium .card-number {
    color: #e6b800;
}

.summary-card.low .card-number {
    color: var(--wpmu-success);
}

.card-description {
    font-size: 14px;
    color: var(--wpmu-gray-600);
    margin-bottom: 12px;
}

/* Content Cards */
.card {
    background: var(--wpmu-white);
    border: 1px solid var(--wpmu-gray-200);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    page-break-inside: avoid;
}

.h1 {
    font-size: 24px;
    font-weight: 900;
    color: var(--wpmu-primary);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.h2 {
    font-size: 20px;
    font-weight: 700;
    color: var(--wpmu-primary);
    margin-bottom: 16px;
    display: flex;
    align-items: center;
    gap: 8px;
}

/* Screenshots */
.screenshot-gallery {
    display: block;
}

.screenshot-item {
    display: block;
    width: 100%;
    background: var(--wpmu-white);
    border: 1px solid var(--wpmu-gray-200);
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 20px;
    page-break-inside: avoid;
}

.screenshot-header {
    margin-bottom: 12px;
}

.screenshot-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
    display: inline-block;
}

.screenshot-badge.before {
    background: rgba(255, 193, 7, 0.2);
    color: #b8860b;
    border: 1px solid rgba(255, 193, 7, 0.3);
}

.screenshot-badge.after {
    background: rgba(40, 167, 69, 0.2);
    color: #155724;
    border: 1px solid rgba(40, 167, 69, 0.3);
}

.screenshot-title {
    font-size: 14px;
    font-weight: 600;
    color: var(--wpmu-gray-900);
}

.screenshot-container img {
    width: 100%;
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    border: 1px solid var(--wpmu-gray-300);
}

/* Lists and Content */
ul {
    padding-left: 20px;
}

li {
    margin-bottom: 8px;
    line-height: 1.6;
}

/* Remove animations for PDF but keep the visual design */
* {
    animation: none !important;
    transition: none !important;
}

/* Ensure proper page breaks */
.card, .summary-card, .screenshot-item {
    page-break-inside: avoid;
}

h1, h2, h3 {
    page-break-after: avoid;
}

/* Print-specific adjustments */
@page {
    margin: 1cm;
}
</style>
<?php
} else {
    // Regular web styles
?><!doctype html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo esc_html($data['title']); ?> – Report</title>
<style>
<?php echo $brand_css; ?>
:root {
    /* WPMU DEV Official Brand Colors - Updated */
    --wpmu-primary: #003366;        /* WPMU DEV Navy Blue */
    --wpmu-primary-dark: #002244;   /* Darker navy for depth */
    --wpmu-primary-light: #E6F3FF;  /* Very light blue background */
    --wpmu-secondary: #FFCC00;      /* WPMU DEV Yellow/Gold */
    --wpmu-secondary-dark: #E6B800; /* Darker yellow */
    --wpmu-secondary-light: #FFF9E6; /* Light yellow background */
    --wpmu-accent: #17A2B8;         /* Teal accent */
    --wpmu-accent-light: #E6F9FC;   /* Light teal background */
    --wpmu-warning: #FFC107;        /* Warning yellow */
    --wpmu-warning-light: #FFF9E5;  /* Light warning background */
    --wpmu-danger: #DC3545;         /* Error red */
    --wpmu-danger-light: #FFEBEE;   /* Light error background */
    --wpmu-dark: #212529;           /* Primary dark text */
    --wpmu-gray-900: #212529;       /* Darkest gray */
    --wpmu-gray-800: #343A40;       /* Dark gray */
    --wpmu-gray-700: #495057;       /* Medium-dark gray */
    --wpmu-gray-600: #6C757D;       /* Medium gray */
    --wpmu-gray-500: #ADB5BD;       /* Mid gray */
    --wpmu-gray-400: #CED4DA;       /* Light gray */
    --wpmu-gray-300: #DEE2E6;       /* Lighter gray */
    --wpmu-gray-200: #E9ECEF;       /* Very light gray */
    --wpmu-gray-100: #F8F9FA;       /* Background gray */
    --wpmu-white: #FFFFFF;          /* Pure white */
}

html {
    scroll-behavior: smooth;
}

body {
    font-family: Arial, sans-serif;
    background: #f8f9fa;
    color: #212529;
    margin: 0;
    line-height: 1.6;
    font-size: 14px;
    font-weight: 400;
    padding: 20px;
}

.container {
    max-width: 1200px;
    margin: 32px auto;
    padding: 0 24px;
}

.card {
    background: var(--wpmu-white);
    border: 1px solid var(--wpmu-gray-200);
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(44, 62, 80, 0.08), 0 1px 3px rgba(44, 62, 80, 0.06);
    padding: 32px;
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 30px rgba(44, 62, 80, 0.12), 0 2px 6px rgba(44, 62, 80, 0.08);
}

.card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--wpmu-primary) 0%, var(--wpmu-secondary) 100%);
}

.grid {
    display: grid;
    gap: 24px;
}

.grid-3 {
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
}

.grid-2 {
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
}

.header {
    background: var(--wpmu-white);
    border: 1px solid var(--wpmu-gray-200);
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(44, 62, 80, 0.08);
    padding: 32px;
    margin-bottom: 32px;
    display: flex;
    align-items: center;
    gap: 24px;
    position: relative;
    overflow: hidden;
}

.header::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--wpmu-primary) 0%, var(--wpmu-secondary) 100%);
}

.logo {
    height: 64px;
    object-fit: contain;
    border-radius: 12px;
    box-shadow: 0 4px 8px rgba(26, 32, 44, 0.1);
}

.badge {
    display: inline-flex;
    align-items: center;
    padding: 8px 16px;
    border-radius: 50px;
    font-size: 14px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.tile {
    border-radius: 12px;
    border: 1px solid var(--wpmu-gray-200);
    padding: 24px;
    background: var(--wpmu-white);
    box-shadow: 0 2px 8px rgba(44, 62, 80, 0.06);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.tile::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: currentColor;
    opacity: 0.9;
}

.tile:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(44, 62, 80, 0.12);
    border-color: var(--wpmu-primary);
}

/* Enhanced hover effects for clickable summary tiles */
a.tile:hover {
    transform: translateY(-4px) scale(1.015);
    box-shadow: 0 12px 35px rgba(44, 62, 80, 0.15);
    border-width: 2px;
}

a.tile.red:hover {
    box-shadow: 0 12px 35px rgba(220, 53, 69, 0.25);
    border-color: var(--wpmu-danger);
}

a.tile.amber:hover {
    box-shadow: 0 12px 35px rgba(255, 193, 7, 0.25);
    border-color: var(--wpmu-warning);
}

a.tile.green:hover {
    box-shadow: 0 12px 35px rgba(255, 204, 0, 0.25);
    border-color: var(--wpmu-secondary);
}

a.tile::after {
    content: '👆 Click to view action items';
    position: absolute;
    bottom: -35px;
    left: 50%;
    transform: translateX(-50%);
    background: var(--wpmu-gray-800);
    color: white;
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
    opacity: 0;
    pointer-events: none;
    transition: all 0.3s ease;
}

a.tile:hover::after {
    opacity: 1;
    bottom: -30px;
}

.tile .num {
    font-size: 42px;
    font-weight: 800;
    margin: 8px 0;
    display: block;
}

.red {
    background: var(--wpmu-danger-light);
    color: var(--wpmu-danger);
    border-color: var(--wpmu-danger);
}

.amber {
    background: var(--wpmu-warning-light);
    color: var(--wpmu-warning);
    border-color: var(--wpmu-warning);
}

.green {
    background: var(--wpmu-secondary-light);
    color: var(--wpmu-secondary);
    border-color: var(--wpmu-secondary);
}

.small {
    font-size: 14px;
    color: var(--wpmu-gray-600);
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.h1 {
    font-size: 32px;
    font-weight: 800;
    margin: 0;
    color: var(--wpmu-gray-900);
    background: linear-gradient(135deg, var(--wpmu-primary) 0%, var(--wpmu-secondary) 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.h2 {
    font-size: 24px;
    font-weight: 700;
    margin: 0 0 16px;
    color: var(--wpmu-gray-800);
    position: relative;
    padding-bottom: 12px;
}

.h2::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    width: 60px;
    height: 3px;
    background: linear-gradient(90deg, var(--wpmu-primary) 0%, var(--wpmu-secondary) 100%);
    border-radius: 2px;
}

.kv {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 12px;
}

.kv div {
    padding: 16px;
    border: 1px solid var(--wpmu-gray-200);
    border-radius: 12px;
    background: var(--wpmu-gray-50);
    font-weight: 500;
}

hr {
    border: 0;
    border-top: 2px solid var(--wpmu-gray-200);
    margin: 32px 0;
    border-radius: 1px;
}

.footer {
    font-size: 14px;
    color: var(--wpmu-gray-600);
    margin-top: 32px;
    text-align: center;
    padding: 24px;
    background: var(--wpmu-gray-50);
    border-radius: 16px;
    border: 1px solid var(--wpmu-gray-200);
}

.btnbar {
    display: flex;
    gap: 16px;
    margin-top: 24px;
    justify-content: center;
}

.btn {
    background: linear-gradient(135deg, var(--wpmu-primary) 0%, var(--wpmu-primary-dark) 100%);
    color: var(--wpmu-white);
    text-decoration: none;
    padding: 16px 32px;
    border-radius: 50px;
    font-weight: 700;
    font-size: 16px;
    transition: all 0.3s ease;
    box-shadow: 0 4px 15px rgba(0, 51, 102, 0.3);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 51, 102, 0.4);
}

.btn.outline {
    background: var(--wpmu-white);
    color: var(--wpmu-primary);
    border: 2px solid var(--wpmu-primary);
    box-shadow: 0 4px 15px rgba(0, 51, 102, 0.1);
}

.btn.outline:hover {
    background: var(--wpmu-primary-light);
    box-shadow: 0 8px 25px rgba(0, 51, 102, 0.2);
}

.section-title {
    font-size: 16px;
    letter-spacing: 1px;
    color: var(--wpmu-gray-700);
    font-weight: 800;
    text-transform: uppercase;
    margin-bottom: 20px;
    padding: 12px 0;
    border-bottom: 2px solid var(--wpmu-gray-200);
}

.checkgrid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 16px;
}

.check {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    border: 1px solid var(--wpmu-gray-200);
    border-radius: 16px;
    background: var(--wpmu-gray-50);
    transition: all 0.3s ease;
}

.check:hover {
    background: var(--wpmu-primary-light);
    border-color: var(--wpmu-primary);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 51, 102, 0.15);
}

.check .label {
    font-weight: 700;
    color: var(--wpmu-gray-800);
}

.ss {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
}

.ss img {
    width: 100%;
    border: 1px solid var(--wpmu-gray-200);
    border-radius: 16px;
    object-fit: contain;
    max-height: 400px;
    box-shadow: 0 8px 25px rgba(44, 62, 80, 0.1);
    transition: all 0.3s ease;
}

.ss img:hover {
    transform: scale(1.02);
    box-shadow: 0 12px 35px rgba(44, 62, 80, 0.15);
}

.kicker {
    font-weight: 700;
    color: var(--wpmu-gray-800);
    margin-bottom: 12px;
    padding: 8px 16px;
    background: var(--wpmu-primary-light);
    border-radius: 8px;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Status indicators */
.status-critical {
    background: var(--wpmu-danger);
    color: var(--wpmu-white);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-medium {
    background: var(--wpmu-warning);
    color: var(--wpmu-white);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.status-low {
    background: var(--wpmu-secondary);
    color: var(--wpmu-white);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Enhanced list styling */
ul {
    list-style: none;
    padding: 0;
}

ul li {
    position: relative;
    padding: 12px 0 12px 32px;
    border-bottom: 1px solid var(--wpmu-gray-200);
    font-weight: 500;
    color: var(--wpmu-gray-700);
}

ul li:last-child {
    border-bottom: none;
}

ul li::before {
    content: '✓';
    position: absolute;
    left: 0;
    top: 12px;
    width: 20px;
    height: 20px;
    background: var(--wpmu-secondary);
    color: var(--wpmu-white);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    font-weight: 700;
}

/* Responsive design */
@media (max-width: 768px) {
    .container {
        margin: 16px auto;
        padding: 0 16px;
    }
    
    .grid-2, .grid-3 {
        grid-template-columns: 1fr;
    }
    
    .header {
        flex-direction: column;
        text-align: center;
    }
    
    .ss {
        grid-template-columns: 1fr;
    }
    
    .h1 {
        font-size: 24px;
    }
    
    .h2 {
        font-size: 20px;
    }
    
    .tile .num {
        font-size: 32px;
    }
}

/* Modern visual enhancements */
.pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% {
        box-shadow: 0 0 0 0 rgba(0, 51, 102, 0.4);
    }
    70% {
        box-shadow: 0 0 0 10px rgba(0, 51, 102, 0);
    }
    100% {
        box-shadow: 0 0 0 0 rgba(0, 51, 102, 0);
    }
}

/* Subtle background pattern */
body::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-image: 
        radial-gradient(circle at 20% 50%, rgba(0, 51, 102, 0.03) 0%, transparent 50%),
        radial-gradient(circle at 80% 20%, rgba(255, 204, 0, 0.02) 0%, transparent 50%),
        radial-gradient(circle at 40% 80%, rgba(23, 162, 184, 0.02) 0%, transparent 50%);
    pointer-events: none;
    z-index: -1;
}

/* Enhanced gradient overlays */
.card::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: linear-gradient(135deg, transparent 0%, rgba(0, 51, 102, 0.01) 100%);
    pointer-events: none;
}

/* Amazing Hero Header Styles */
.hero-header {
    position: relative;
    min-height: 500px;
    background: linear-gradient(135deg, var(--wpmu-primary) 0%, var(--wpmu-primary-dark) 50%, #001a33 100%);
    color: white;
    overflow: hidden;
    margin: -32px -24px 48px -24px;
    border-radius: 0 0 32px 32px;
}

.hero-bg {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background-image: 
        radial-gradient(circle at 20% 20%, rgba(255, 204, 0, 0.1) 0%, transparent 50%),
        radial-gradient(circle at 80% 80%, rgba(23, 162, 184, 0.1) 0%, transparent 50%),
        linear-gradient(45deg, transparent 0%, rgba(255, 255, 255, 0.03) 100%);
    background-size: 200% 200%;
    animation: gradientShift 15s ease infinite;
}

@keyframes gradientShift {
    0% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
    100% { background-position: 0% 50%; }
}

.hero-content {
    position: relative;
    z-index: 2;
    padding: 48px 32px;
    max-width: 1200px;
    margin: 0 auto;
}

.hero-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 40px;
}

.hero-logo {
    display: flex;
    align-items: center;
    gap: 16px;
}

.logo-img {
    height: 48px;
    width: auto;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.logo-text {
    font-size: 32px;
    font-weight: 900;
    background: linear-gradient(45deg, #FFCC00, #FFE066);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.brand-tag {
    background: rgba(255, 204, 0, 0.2);
    color: #FFCC00;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border: 1px solid rgba(255, 204, 0, 0.3);
}

.hero-status {
    text-align: right;
}

.status-badge {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    padding: 12px 20px;
    border-radius: 50px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 8px;
}

.status-icon {
    font-size: 16px;
}

.status-text {
    font-weight: 600;
    font-size: 14px;
}

.report-id {
    font-size: 12px;
    opacity: 0.8;
    font-weight: 500;
}

.hero-main {
    text-align: center;
    margin-bottom: 48px;
}

.hero-title {
    margin: 0 0 32px 0;
}

.title-main {
    display: block;
    font-size: 48px;
    font-weight: 900;
    margin-bottom: 8px;
    background: linear-gradient(45deg, #ffffff, #e6f3ff);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.title-sub {
    display: block;
    font-size: 18px;
    font-weight: 400;
    opacity: 0.9;
    font-style: italic;
}

.hero-info {
    margin-top: 32px;
}

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 24px;
    max-width: 800px;
    margin: 0 auto;
}

.info-item {
    background: rgba(255, 255, 255, 0.1);
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.2);
    padding: 20px;
    border-radius: 16px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.3s ease;
}

.info-item:hover {
    background: rgba(255, 255, 255, 0.15);
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}

.info-icon {
    font-size: 24px;
    width: 48px;
    height: 48px;
    background: rgba(255, 204, 0, 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(255, 204, 0, 0.3);
}

.info-content {
    flex: 1;
}

.info-label {
    font-size: 12px;
    opacity: 0.8;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
    font-weight: 600;
}

.info-value {
    font-size: 16px;
    font-weight: 600;
}

.site-link {
    color: #FFCC00;
    text-decoration: none;
    transition: all 0.3s ease;
}

.site-link:hover {
    color: #FFE066;
    text-decoration: underline;
}

/* Responsive adjustments for hero */
@media (max-width: 768px) {
    .hero-header {
        margin: -24px -24px 32px -24px;
        min-height: 400px;
    }
    
    .hero-content {
        padding: 32px 24px;
    }
    
    .hero-top {
        flex-direction: column;
        gap: 24px;
        text-align: center;
    }
    
    .title-main {
        font-size: 32px;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
    }
}

/* Executive Summary Styles */
.executive-summary {
    background: linear-gradient(135deg, rgba(0, 51, 102, 0.02) 0%, rgba(255, 204, 0, 0.02) 100%);
    border: 1px solid var(--wpmu-gray-200);
    border-radius: 24px;
    padding: 40px;
    margin-bottom: 40px;
    position: relative;
    overflow: hidden;
}

.executive-summary::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, var(--wpmu-primary) 0%, var(--wpmu-secondary) 100%);
}

.summary-header {
    text-align: center;
    margin-bottom: 40px;
}

.summary-title {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    font-size: 32px;
    font-weight: 800;
    color: var(--wpmu-primary);
    margin: 0 0 8px 0;
}

.summary-icon {
    font-size: 36px;
}

.summary-subtitle {
    color: var(--wpmu-gray-600);
    font-size: 16px;
    font-weight: 500;
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 24px;
    margin-bottom: 32px;
}

.summary-card {
    background: var(--wpmu-white);
    border: 2px solid var(--wpmu-gray-200);
    border-radius: 20px;
    padding: 32px;
    text-decoration: none;
    color: inherit;
    transition: all 0.4s ease;
    position: relative;
    overflow: hidden;
    display: block;
}

.summary-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 6px;
    background: currentColor;
    transform: scaleX(0);
    transition: transform 0.3s ease;
}

.summary-card:hover::before {
    transform: scaleX(1);
}

.summary-card:hover {
    transform: translateY(-8px) scale(1.02);
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
}

.summary-card.critical {
    color: #DC3545;
    border-color: rgba(220, 53, 69, 0.2);
}

.summary-card.critical:hover {
    border-color: #DC3545;
    box-shadow: 0 20px 40px rgba(220, 53, 69, 0.2);
}

.summary-card.medium {
    color: #FFC107;
    border-color: rgba(255, 193, 7, 0.2);
}

.summary-card.medium:hover {
    border-color: #FFC107;
    box-shadow: 0 20px 40px rgba(255, 193, 7, 0.2);
}

.summary-card.low {
    color: var(--wpmu-secondary);
    border-color: rgba(255, 204, 0, 0.2);
}

.summary-card.low:hover {
    border-color: var(--wpmu-secondary);
    box-shadow: 0 20px 40px rgba(255, 204, 0, 0.2);
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 20px;
}

.card-icon {
    font-size: 32px;
    width: 60px;
    height: 60px;
    background: rgba(0, 0, 0, 0.05);
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.card-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.card-badge.critical {
    background: rgba(220, 53, 69, 0.1);
    color: #DC3545;
}

.card-badge.medium {
    background: rgba(255, 193, 7, 0.1);
    color: #FFC107;
}

.card-badge.low {
    background: rgba(255, 204, 0, 0.1);
    color: var(--wpmu-secondary);
}

.card-number {
    font-size: 64px;
    font-weight: 900;
    line-height: 1;
    margin-bottom: 12px;
}

.card-description {
    font-size: 16px;
    color: var(--wpmu-gray-600);
    margin-bottom: 16px;
    line-height: 1.5;
}

.card-action {
    font-size: 14px;
    font-weight: 600;
    opacity: 0;
    transform: translateY(10px);
    transition: all 0.3s ease;
}

.summary-card:hover .card-action {
    opacity: 1;
    transform: translateY(0);
}

.summary-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 24px;
    background: rgba(0, 51, 102, 0.03);
    padding: 24px;
    border-radius: 16px;
    border: 1px solid var(--wpmu-gray-200);
}

.stat-item {
    text-align: center;
}

.stat-number {
    font-size: 28px;
    font-weight: 800;
    color: var(--wpmu-primary);
    margin-bottom: 4px;
}

.stat-label {
    font-size: 14px;
    color: var(--wpmu-gray-600);
    font-weight: 600;
}

/* Enhanced Card Styles */
.enhanced-card {
    background: var(--wpmu-white);
    border: 1px solid var(--wpmu-gray-200);
    border-radius: 20px;
    padding: 0;
    margin-bottom: 32px;
    overflow: hidden;
    box-shadow: 0 4px 20px rgba(0, 51, 102, 0.08);
    transition: all 0.3s ease;
}

.enhanced-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 30px rgba(0, 51, 102, 0.12);
}

.card-header-enhanced {
    background: linear-gradient(135deg, var(--wpmu-primary) 0%, var(--wpmu-primary-dark) 100%);
    color: white;
    padding: 32px;
    display: flex;
    align-items: center;
    gap: 24px;
}

.card-icon-large {
    font-size: 48px;
    width: 80px;
    height: 80px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(10px);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.card-title-section {
    flex: 1;
}

.card-title {
    font-size: 28px;
    font-weight: 800;
    margin: 0 0 8px 0;
    line-height: 1.2;
}

.card-subtitle {
    font-size: 16px;
    opacity: 0.9;
    font-weight: 400;
    margin: 0;
}

.card-status {
    text-align: center;
}

.status-indicator {
    width: 16px;
    height: 16px;
    border-radius: 50%;
    margin: 0 auto 8px auto;
    position: relative;
}

.status-indicator::before {
    content: '';
    position: absolute;
    top: -4px;
    left: -4px;
    right: -4px;
    bottom: -4px;
    border-radius: 50%;
    background: currentColor;
    opacity: 0.3;
    animation: pulse 2s infinite;
}

.status-indicator.critical {
    background: #FF6B6B;
    color: #FF6B6B;
}

.status-indicator.warning {
    background: #FFD93D;
    color: #FFD93D;
}

.status-indicator.clean {
    background: #6BCF7F;
    color: #6BCF7F;
}

.status-text {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.card-content {
    padding: 32px;
}

.infection-details {
    font-size: 16px;
    line-height: 1.8;
    color: var(--wpmu-gray-700);
    white-space: pre-wrap;
}

.infection-details p {
    margin-bottom: 16px;
}

.infection-details strong {
    color: var(--wpmu-primary);
    font-weight: 700;
}

/* Screenshot Section Styles */
.screenshots-section .card-header-enhanced {
    background: linear-gradient(135deg, #17A2B8 0%, #138496 100%);
}

.screenshot-gallery {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 32px;
}

.screenshot-item {
    border: 1px solid var(--wpmu-gray-200);
    border-radius: 16px;
    overflow: hidden;
    background: var(--wpmu-gray-50);
    transition: all 0.3s ease;
}

.screenshot-item:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 25px rgba(44, 62, 80, 0.1);
}

.screenshot-header {
    padding: 20px;
    background: var(--wpmu-white);
    border-bottom: 1px solid var(--wpmu-gray-200);
    display: flex;
    align-items: center;
    gap: 16px;
}

.screenshot-badge {
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
}

.screenshot-badge.before {
    background: rgba(255, 193, 7, 0.1);
    color: #FFC107;
    border: 1px solid rgba(255, 193, 7, 0.3);
}

.screenshot-badge.after {
    background: rgba(40, 167, 69, 0.1);
    color: #28A745;
    border: 1px solid rgba(40, 167, 69, 0.3);
}

.screenshot-badge.evidence {
    background: rgba(23, 162, 184, 0.1);
    color: #17A2B8;
    border: 1px solid rgba(23, 162, 184, 0.3);
}

.screenshot-badge.other {
    background: rgba(108, 117, 125, 0.1);
    color: #6C757D;
    border: 1px solid rgba(108, 117, 125, 0.3);
}

.screenshot-title {
    font-size: 16px;
    font-weight: 600;
    color: var(--wpmu-gray-800);
    flex: 1;
}

.screenshot-container {
    padding: 20px;
    min-height: 250px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.screenshot-image {
    width: 100%;
    height: auto;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(44, 62, 80, 0.1);
    transition: all 0.3s ease;
    max-height: 400px;
    object-fit: contain;
}

.screenshot-image:hover {
    transform: scale(1.02);
    box-shadow: 0 12px 35px rgba(44, 62, 80, 0.15);
}

.screenshot-placeholder {
    text-align: center;
    color: var(--wpmu-gray-500);
    padding: 40px;
}

.screenshot-placeholder-section {
    text-align: center;
    color: var(--wpmu-gray-500);
    padding: 60px;
}

.placeholder-icon {
    font-size: 48px;
    margin-bottom: 16px;
    opacity: 0.5;
}

.placeholder-text {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 8px;
}

.placeholder-note {
    font-size: 14px;
    opacity: 0.7;
}

/* Responsive adjustments for screenshots */
@media (max-width: 768px) {
    .screenshot-gallery {
        grid-template-columns: 1fr;
    }
    
    .screenshot-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 8px;
    }
}

/* Print styles */
@media print {
    body {
        background: white;
    }
    
    .btnbar {
        display: none;
    }
    
    .card, .header {
        box-shadow: none;
        border: 1px solid var(--wpmu-gray-300);
    }
}
</style>
<?php 
} // End conditional styles
?>
<?php if (!empty($print_mode)): ?>
<script>window.addEventListener('load', () => { window.print(); });</script>
<?php endif; ?>
</head>
<body>
<!-- Hero Header Section -->
<div class="hero-header">
    <div class="hero-bg"></div>
    <div class="hero-content">
        <div class="hero-top">
            <div class="hero-logo">
                <?php if (!empty($data['brand']['logo']) && filter_var($data['brand']['logo'], FILTER_VALIDATE_URL)): ?>
                    <img src="<?php echo esc_url($data['brand']['logo']); ?>" alt="<?php echo esc_attr($data['brand']['name']); ?>" class="logo-img" style="max-width: 150px; height: auto;">
                <?php else: ?>
                    <div class="logo-text">WPMU DEV</div>
                <?php endif; ?>
                <div class="brand-tag">Security Services</div>
            </div>
            <div class="hero-status">
                <div class="status-badge">
                    <span class="status-icon">[SHIELD]</span>
                    <span class="status-text">Security Audit Complete</span>
                </div>
                <div class="report-id">Report #<?php echo intval($data['post_id']); ?></div>
            </div>
        </div>
        
        <div class="hero-main">
            <h1 class="hero-title">
                <span class="title-main">Malware Cleanup Report</span>
                <span class="title-sub">Professional Security Analysis & Remediation</span>
            </h1>
            
            <div class="hero-info">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-icon">🏢</div>
                        <div class="info-content">
                            <div class="info-label">Client</div>
                            <div class="info-value"><?php echo esc_html($data['client'] ?: 'Confidential Client'); ?></div>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon">🌐</div>
                        <div class="info-content">
                            <div class="info-label">Website</div>
                            <div class="info-value">
                                <a href="<?php echo esc_url($data['site']); ?>" class="site-link" target="_blank">
                                    <?php echo esc_html(parse_url($data['site'], PHP_URL_HOST) ?: $data['site']); ?>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-icon">📅</div>
                        <div class="info-content">
                            <div class="info-label">Completed</div>
                            <div class="info-value"><?php echo esc_html($data['date'] ?: date('F j, Y')); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="container">

    <!-- Executive Summary Section -->
    <div class="executive-summary">
        <div class="summary-header">
            <h2 class="summary-title">
                <span class="summary-icon">⚡</span>
                Executive Summary
            </h2>
            <div class="summary-subtitle">Security analysis results at a glance</div>
        </div>
        
        <div class="summary-grid">
            <a href="#action-items" class="summary-card critical">
                <div class="card-header">
                    <div class="card-icon">🚨</div>
                    <div class="card-badge critical">Critical</div>
                </div>
                <div class="card-number"><?php echo intval($data['s_critical']); ?></div>
                <div class="card-description">Issues requiring immediate attention</div>
                <div class="card-action">Click to view details →</div>
            </a>
            
            <a href="#action-items" class="summary-card medium">
                <div class="card-header">
                    <div class="card-icon">!</div>
                    <div class="card-badge medium">Medium</div>
                </div>
                <div class="card-number"><?php echo intval($data['s_medium']); ?></div>
                <div class="card-description">Issues to address soon</div>
                <div class="card-action">Click to view details →</div>
            </a>
            
            <a href="#action-items" class="summary-card low">
                <div class="card-header">
                    <div class="card-icon">▢</div>
                    <div class="card-badge low">Low</div>
                </div>
                <div class="card-number"><?php echo intval($data['s_low']); ?></div>
                <div class="card-description">Minor improvements</div>
                <div class="card-action">Click to view details →</div>
            </a>
        </div>
        
        <div class="summary-stats">
            <div class="stat-item">
                <div class="stat-number"><?php echo intval($data['s_critical'] + $data['s_medium'] + $data['s_low']); ?></div>
                <div class="stat-label">Total Issues Found</div>
            </div>
            <div class="stat-item">
                <div class="stat-number"><?php echo max(0, 100 - ($data['s_critical'] * 20 + $data['s_medium'] * 10 + $data['s_low'] * 5)); ?>%</div>
                <div class="stat-label">Security Score</div>
            </div>
        </div>
    </div>

    <div class="enhanced-card infection-summary">
        <div class="card-header-enhanced">
            <div class="card-icon-large">[SHIELD]</div>
            <div class="card-title-section">
                <h2 class="card-title">Security Analysis Report</h2>
                <div class="card-subtitle">Detailed findings and infection summary</div>
            </div>
            <div class="card-status">
                <div class="status-indicator <?php echo ($data['s_critical'] > 0) ? 'critical' : (($data['s_medium'] > 0) ? 'warning' : 'clean'); ?>"></div>
                <div class="status-text"><?php echo ($data['s_critical'] > 0) ? 'Action Required' : (($data['s_medium'] > 0) ? 'Monitor' : 'Secure'); ?></div>
            </div>
        </div>
        <div class="card-content">
            <div class="infection-details">
                <?php echo $data['infection_summary']; ?>
            </div>
        </div>
    </div>

    <div class="enhanced-card screenshots-section">
        <div class="card-header-enhanced">
            <div class="card-icon-large">📸</div>
            <div class="card-title-section">
                <h2 class="card-title">Security Scan Evidence</h2>
                <div class="card-subtitle">Comprehensive documentation and verification screenshots</div>
            </div>
            <div class="card-status">
                <div class="status-indicator clean"></div>
                <div class="status-text">Verified</div>
            </div>
        </div>
        <div class="card-content">
            <?php if (!empty($data['screenshots']) && count($data['screenshots']) > 0): ?>
                <div class="screenshot-gallery">
                    <?php foreach ($data['screenshots'] as $index => $screenshot): ?>
                        <div class="screenshot-item">
                            <div class="screenshot-header">
                                <div class="screenshot-badge <?php echo esc_attr($screenshot['type']); ?>">
                                    <?php 
                                    $badge_icons = [
                                        'before' => '◀',
                                        'after' => '✅', 
                                        'evidence' => '▢',
                                        'other' => '📷'
                                    ];
                                    echo $badge_icons[$screenshot['type']] ?? '📷';
                                    echo ' ' . ucfirst($screenshot['type']);
                                    ?>
                                </div>
                                <div class="screenshot-title">
                                    <?php echo esc_html($screenshot['title'] ?: 'Security Scan Screenshot'); ?>
                                </div>
                            </div>
                            <div class="screenshot-container">
                                <?php if (!empty($screenshot['url']) && filter_var($screenshot['url'], FILTER_VALIDATE_URL)): ?>
                                <img src="<?php echo esc_url($screenshot['url']); ?>" 
                                     alt="<?php echo esc_attr($screenshot['title'] ?: 'Screenshot'); ?>" 
                                     class="screenshot-image"
                                     style="max-width: 100%; height: auto; border: 1px solid #ddd;">
                                <?php else: ?>
                                <div style="background: #f8f9fa; padding: 20px; text-align: center; border: 1px solid #ddd; color: #666;">
                                    Screenshot not available
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <?php 
                // Fallback to legacy screenshots if no new format screenshots
                $legacy_screenshots = [];
                if (!empty($data['ss_before'])) {
                    $legacy_screenshots[] = [
                        'url' => $data['ss_before'],
                        'title' => 'Security Scan — Issues Found',
                        'type' => 'before'
                    ];
                }
                if (!empty($data['ss_after'])) {
                    $legacy_screenshots[] = [
                        'url' => $data['ss_after'],
                        'title' => 'Security Scan — Clean Results',
                        'type' => 'after'
                    ];
                }
                ?>
                
                <?php if (!empty($legacy_screenshots)): ?>
                    <div class="screenshot-gallery">
                        <?php foreach ($legacy_screenshots as $screenshot): ?>
                            <div class="screenshot-item">
                                <div class="screenshot-header">
                                    <div class="screenshot-badge <?php echo esc_attr($screenshot['type']); ?>">
                                        <?php echo $screenshot['type'] === 'before' ? '◀ Before' : '✓ After'; ?>
                                    </div>
                                    <div class="screenshot-title"><?php echo esc_html($screenshot['title']); ?></div>
                                </div>
                                <div class="screenshot-container">
                                    <?php if (!empty($screenshot['url']) && filter_var($screenshot['url'], FILTER_VALIDATE_URL)): ?>
                                    <img src="<?php echo esc_url($screenshot['url']); ?>" 
                                         alt="<?php echo esc_attr($screenshot['title'] ?: 'Screenshot'); ?>" 
                                         class="screenshot-image"
                                         style="max-width: 100%; height: auto; border: 1px solid #ddd;">
                                    <?php else: ?>
                                    <div style="background: #f8f9fa; padding: 20px; text-align: center; border: 1px solid #ddd; color: #666;">
                                        Screenshot not available
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="screenshot-placeholder-section">
                        <div class="placeholder-icon">📷</div>
                        <div class="placeholder-text">No screenshots provided</div>
                        <div class="placeholder-note">Screenshots will be displayed here when added to the report</div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card" style="margin-bottom:32px">
        <div class="h2">SECURITY - Security Issues Summary</div>
        <div style="display: flex; justify-content: space-around; text-align: center; padding: 20px;">
            <div style="background: #dc3545; color: white; padding: 15px; border-radius: 8px; flex: 1; margin: 0 5px;">
                <div style="font-size: 24px; font-weight: bold;"><?php echo intval($data['s_critical']); ?></div>
                <div style="font-size: 12px;">Critical Issues</div>
            </div>
            <div style="background: #ffc107; color: #000; padding: 15px; border-radius: 8px; flex: 1; margin: 0 5px;">
                <div style="font-size: 24px; font-weight: bold;"><?php echo intval($data['s_medium']); ?></div>
                <div style="font-size: 12px;">Medium Issues</div>
            </div>
            <div style="background: #28a745; color: white; padding: 15px; border-radius: 8px; flex: 1; margin: 0 5px;">
                <div style="font-size: 24px; font-weight: bold;"><?php echo intval($data['s_low']); ?></div>
                <div style="font-size: 12px;">Low Issues</div>
            </div>
        </div>
        <div style="text-align: center; margin-top: 15px; font-size: 14px; color: #666;">
            Total Issues Found: <?php echo intval($data['s_critical'] + $data['s_medium'] + $data['s_low']); ?>
        </div>
    </div>

    <div class="card" style="margin-bottom:32px">
        <div class="h2">ACTIONS - Actions Taken</div>
        <ul style="font-size: 16px; line-height: 1.8;">
            <li>Create a complete backup whenever possible</li>
            <li>Thoroughly evaluate the infection scope and impact</li>
            <li>Manually check known malware locations and suspicious scripts</li>
            <li>Scan the website using multiple security tools and scanners</li>
            <li>Perform comprehensive cleanup, update plugins & themes, and reinstall free plugins when applicable</li>
            <li>Apply WPMU DEV Defender security configuration and hardening</li>
            <li>Manually audit the plugin and theme list for known security issues</li>
        </ul>
    </div>

    <?php if (!empty($data['verification_summary'])): ?>
    <div class="card" style="margin-bottom:32px">
        <div class="h2">VERIFICATION - Verification Summary</div>
        <div style="white-space:pre-wrap; color: var(--wpmu-gray-700); line-height: 1.7; font-size: 16px;"><?php echo wp_kses_post($data['verification_summary']); ?></div>
    </div>
    <?php endif; ?>

    <?php if (!empty($data['defender_checklist'])): ?>
    <div class="card" style="margin-bottom:32px">
        <div class="h2">DEFENDER - WPMU DEV Defender Configuration Checklist</div>
        <div style="margin-bottom: 20px; padding: 16px; background: var(--wpmu-primary-light); border-radius: 12px; border-left: 4px solid var(--wpmu-primary);">
            <div style="font-weight: 700; color: var(--wpmu-primary); margin-bottom: 8px;">i Configuration Overview</div>
            <div style="font-size: 15px; color: var(--wpmu-gray-700);">
                Our team will configure Defender settings to ensure your website is safe and protected, but some configurations require your attention. The below table demonstrates the details, please apply the "pending" points as soon as possible.
            </div>
        </div>
        
        <div style="overflow-x: auto; border-radius: 12px; border: 1px solid var(--wpmu-gray-200);">
            <table style="width: 100%; border-collapse: collapse; font-size: 14px; background: var(--wpmu-white);">
                <thead style="background: var(--wpmu-gray-100);">
                    <tr>
                        <th style="padding: 16px; text-align: left; font-weight: 700; color: var(--wpmu-gray-800); border-bottom: 2px solid var(--wpmu-gray-200);">Setting</th>
                        <th style="padding: 16px; text-align: center; font-weight: 700; color: var(--wpmu-gray-800); border-bottom: 2px solid var(--wpmu-gray-200);">Applied</th>
                        <th style="padding: 16px; text-align: left; font-weight: 700; color: var(--wpmu-gray-800); border-bottom: 2px solid var(--wpmu-gray-200);">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($data['defender_checklist'] as $config): ?>
                    <tr style="border-bottom: 1px solid var(--wpmu-gray-200);">
                        <td style="padding: 16px; font-weight: 600; color: var(--wpmu-gray-800);"><?php echo esc_html($config['setting']); ?></td>
                        <td style="padding: 16px; text-align: center;">
                            <?php if ($config['status'] === 'applied'): ?>
                                <span style="background: var(--wpmu-secondary-light); color: var(--wpmu-secondary); padding: 6px 12px; border-radius: 20px; font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">✅ Applied</span>
                            <?php elseif ($config['status'] === 'pending'): ?>
                                <span style="background: var(--wpmu-warning-light); color: var(--wpmu-warning); padding: 6px 12px; border-radius: 20px; font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">! Pending</span>
                            <?php else: ?>
                                <span style="background: var(--wpmu-gray-100); color: var(--wpmu-gray-600); padding: 6px 12px; border-radius: 20px; font-weight: 700; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px;">N/A</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 16px; color: var(--wpmu-gray-700); line-height: 1.5;">
                            <?php echo esc_html($config['notes'] ?: 'Configuration completed as per security best practices.'); ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div style="margin-top: 20px; padding: 16px; background: var(--wpmu-gray-50); border-radius: 12px; border-left: 4px solid var(--wpmu-secondary);">
            <div style="font-weight: 700; color: var(--wpmu-secondary); margin-bottom: 8px;">🔗 Quick Access Links:</div>
            <div style="font-size: 14px;">
                <a href="https://wpmudev.com/docs/wpmu-dev-plugins/defender/" target="_blank" style="color: var(--wpmu-primary); text-decoration: none; font-weight: 600;">→ Defender Documentation</a> • 
                <a href="https://wpmudev.com/docs/wpmu-dev-plugins/defender/#two-factor-authentication" target="_blank" style="color: var(--wpmu-primary); text-decoration: none; font-weight: 600;">→ 2FA Setup</a> • 
                <a href="https://wpmudev.com/docs/wpmu-dev-plugins/defender/#mask-login-area" target="_blank" style="color: var(--wpmu-primary); text-decoration: none; font-weight: 600;">→ Login Masking</a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card" id="action-items" style="margin-bottom:32px">
        <div class="h2">ACTION ITEMS - Action Items (for Site Owner)</div>
        <div class="grid">
            <?php if (!empty($data['reinstall_list'])): ?>
            <div class="tile amber" style="padding: 24px;">
                <div><strong>🔄 Reinstall Required (Premium licenses not provided):</strong></div>
                <div style="white-space:pre-wrap; margin-top: 12px; font-size: 15px; line-height: 1.6;"><?php echo wp_kses_post($data['reinstall_list']); ?></div>
            </div>
            <?php endif; ?>
            <?php if (!empty($data['replace_list'])): ?>
            <div class="tile red" style="padding: 24px;">
                <div><strong>🚨 Replace Immediately (Not receiving security updates):</strong></div>
                <div style="white-space:pre-wrap; margin-top: 12px; font-size: 15px; line-height: 1.6;"><?php echo wp_kses_post($data['replace_list']); ?></div>
            </div>
            <?php endif; ?>
        </div>

        <hr>
        <div class="section-title">� Security Configuration & Follow-ups</div>
        <div class="checkgrid">
            <div class="check">
                <span class="label">Configure SSL Certificate</span> 
                <span class="<?php echo $data['ssl']=='required' ? 'status-critical' : 'status-low'; ?>">
                    <?php echo $data['ssl']=='required' ? 'Required' : 'Completed'; ?>
                </span>
            </div>
            <div class="check">
                <span class="label">Configure Cloudflare Protection</span> 
                <span class="<?php echo $data['cloudflare']=='required' ? 'status-critical' : 'status-low'; ?>">
                    <?php echo $data['cloudflare']=='required' ? 'Required' : 'Completed'; ?>
                </span>
            </div>
            <div class="check">
                <span class="label">Audit Suspicious Admin Accounts</span> 
                <span class="<?php echo $data['check_admins']=='required' ? 'status-critical' : 'status-low'; ?>">
                    <?php echo $data['check_admins']=='required' ? 'Required' : 'Completed'; ?>
                </span>
            </div>
            <div class="check">
                <span class="label">Reset All Admin Passwords</span> 
                <span class="<?php echo $data['reset_passwords']=='required' ? 'status-critical' : 'status-low'; ?>">
                    <?php echo $data['reset_passwords']=='required' ? 'Required' : 'Completed'; ?>
                </span>
            </div>
            <div class="check">
                <span class="label">Configure Defender Mask Login URL</span> 
                <span class="<?php echo $data['mask_login']=='required' ? 'status-medium' : 'status-low'; ?>">
                    <?php echo $data['mask_login']=='required' ? 'Recommended' : 'Completed'; ?>
                </span>
            </div>
        </div>
    </div>

    <?php if (!empty($data['pending_actions'])): ?>
    <div class="card" style="margin-bottom:32px">
        <div class="h2">PENDING - Pending Actions</div>
        <div style="background: var(--wpmu-warning-light); border-radius: 12px; padding: 20px; border-left: 4px solid var(--wpmu-warning);">
            <div style="white-space:pre-wrap; color: var(--wpmu-gray-700); line-height: 1.7; font-size: 16px;"><?php echo wp_kses_post($data['pending_actions']); ?></div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($data['documentation_links'])): ?>
    <div class="card" style="margin-bottom:32px">
        <div class="h2">📚 Documentation & Resources</div>
        <div style="background: var(--wpmu-gray-50); border-radius: 12px; padding: 20px; border: 1px solid var(--wpmu-gray-200);">
            <div style="color: var(--wpmu-gray-700); margin-bottom: 16px; font-weight: 600;">
                📖 Helpful guides and resources for maintaining your site's security:
            </div>
            <?php 
            $links = explode("\n", $data['documentation_links']);
            foreach ($links as $link) {
                $link = trim($link);
                if (empty($link)) continue;
                
                // Parse link format: "Title: URL" or just "URL"
                if (strpos($link, ': ') !== false) {
                    list($title, $url) = explode(': ', $link, 2);
                    $title = trim($title);
                    $url = trim($url);
                } else {
                    $url = $link;
                    $title = parse_url($url, PHP_URL_HOST) . parse_url($url, PHP_URL_PATH);
                }
                
                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    echo '<div style="margin-bottom: 12px;">';
                    echo '<a href="' . esc_url($url) . '" target="_blank" style="color: var(--wpmu-primary); text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 8px;">';
                    echo '<span style="font-size: 16px;">🔗</span> ' . esc_html($title);
                    echo '</a>';
                    echo '</div>';
                }
            }
            ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid grid-2" style="margin-bottom:32px">
        <div class="card">
            <div class="h2">SEO - SEO Impact Notice</div>
            <div style="white-space:pre-wrap; color: var(--wpmu-gray-700); line-height: 1.7; font-size: 16px;">
                <?php echo wp_kses_post($data['seo_note'] ?: "IMPORTANT: Due to prior malware infection, search results may temporarily show unwanted content or warnings.\n\nRECOMMENDED ACTIONS:\n• Check Google results with: site:". parse_url($data['site'], PHP_URL_HOST) ."\n• If you see unexpected results, resubmit your sitemap in Google Search Console\n• Also resubmit in Bing Webmaster Tools\n• Monitor for 2-4 weeks as search engines update their index\n\nTIMELINE: It typically takes 1-4 weeks for Google to fully update search results after malware removal."); ?>
            </div>
        </div>
        <div class="card">
            <div class="h2">PROTECTION - Ongoing Protection</div>
            <div style="white-space:pre-wrap; color: var(--wpmu-gray-700); line-height: 1.7; font-size: 16px;">
                <?php echo wp_kses_post($data['protect_note'] ?: "ESSENTIAL SECURITY MEASURES:\n\n• Configure and enforce 2FA for all admin users (WPMU DEV Defender)\n• Enable automatic security updates (WPMU DEV Automate)\n• Regular security scans and monitoring\n• Keep WordPress core, plugins, and themes updated\n• Use strong, unique passwords for all accounts\n• Regular backup schedule (WPMU DEV Backup)\n\nWPMU DEV TOOLS:\nDefender for security, Automate for updates, and Backup for peace of mind."); ?>
            </div>
        </div>
    </div>

    <?php if (!empty($data['actions'])): ?>
    <div class="card" style="margin-bottom:32px">
        <div class="h2">💡 Additional Recommendations</div>
        <div class="grid" style="grid-template-columns:1fr">
        <?php foreach ($data['actions'] as $a):
            $class = $a['level']=='CRITICAL' ? 'red' : ($a['level']=='RECOMMENDED' ? 'amber' : 'green');
            $icon = $a['level']=='CRITICAL' ? '[!]' : ($a['level']=='RECOMMENDED' ? '[!]' : '[i]'); ?>
            <div class="tile <?php echo $class; ?>" style="padding: 24px;">
                <div><strong><?php echo $icon; ?> <?php echo esc_html($a['level']); ?></strong> — <?php echo esc_html($a['text']); ?></div>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="footer">
        <div style="font-size: 16px; font-weight: 600; color: var(--wpmu-gray-800); margin-bottom: 8px;">
            📄 Report ID: #<?php echo intval($data['post_id']); ?>
        </div>
        <div style="margin-bottom: 16px;">
            <?php echo esc_html($data['brand']['footer']); ?>
        </div>
        <div style="font-size: 14px; color: var(--wpmu-gray-600); margin-bottom: 24px;">
            Generated with ❤️ by WPMU DEV Services • Professional WordPress Support & Security
        </div>

    </div>
</div>
</body>
</html>
