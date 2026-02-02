<?php
/**
 * PDF Export Handler
 * Handles PDF generation using multiple libraries with fallbacks
 */

if ( ! class_exists( 'CRG_PDF' ) ) {

class CRG_PDF {
    
    /**
     * Available PDF libraries
     */
    private static $libraries = [
        'dompdf' => 'Dompdf\\Dompdf',
        'tcpdf' => 'TCPDF',
        'mpdf' => 'Mpdf\\Mpdf'
    ];
    
    /**
     * Generate PDF using the best available library
     */
    public static function generate_pdf( $post_id ) {
        try {
            // Check if DOMpdf is available
            if ( class_exists( 'Dompdf\\Dompdf' ) ) {
                return self::generate_with_dompdf_advanced( $post_id );
            }
            
            // If no DOMpdf, output HTML for manual PDF conversion
            return self::output_html_for_pdf( $post_id );
            
        } catch ( Exception $e ) {
            error_log( 'PDF Generation Error: ' . $e->getMessage() );
            // Fallback to HTML output
            return self::output_html_for_pdf( $post_id );
        }
    }
    
    /**
     * Generate PDF using DOMpdf with advanced options
     */
    private static function generate_with_dompdf_advanced( $post_id ) {
        // Get report data
        $data = CRG_Render::get_data( $post_id );
        if ( ! $data ) {
            wp_die( 'Report data not found.' );
        }
        
        // Generate HTML content
        ob_start();
        CRG_Render::render_report_html( $data, true ); // true for print mode
        $html = ob_get_clean();
        
        // Initialize DOMpdf with optimized options
        $options = new \Dompdf\Options();
        $options->set( 'defaultFont', 'Arial' );
        $options->set( 'isRemoteEnabled', true );
        $options->set( 'isHtml5ParserEnabled', true );
        $options->set( 'isFontSubsettingEnabled', true );
        $options->set( 'isPhpEnabled', false );
        $options->set( 'isJavascriptEnabled', false );
        $options->set( 'debugKeepTemp', false );
        $options->set( 'debugCss', false );
        $options->set( 'debugLayout', false );
        $options->set( 'debugLayoutLines', false );
        $options->set( 'debugLayoutBlocks', false );
        $options->set( 'debugLayoutInline', false );
        $options->set( 'debugLayoutPaddingBox', false );
        
        // Set memory and timeout limits
        $options->set( 'logOutputFile', false );
        $options->set( 'tempDir', sys_get_temp_dir() );
        
        $dompdf = new \Dompdf\Dompdf( $options );
        
        // Load HTML and generate PDF
        $dompdf->loadHtml( $html );
        $dompdf->setPaper( 'A4', 'portrait' );
        $dompdf->render();
        
        // Generate filename
        $client_name = sanitize_file_name( $data['client'] ?: 'client' );
        $filename = $client_name . '-cleanup-report-' . date('Y-m-d') . '.pdf';
        
        // Output PDF
        $dompdf->stream( $filename, array( 'Attachment' => true ) );
        exit;
    }
    
    /**
     * Output HTML version for manual PDF conversion
     */
    private static function output_html_for_pdf( $post_id ) {
        // Get report data
        $data = CRG_Render::get_data( $post_id );
        if ( ! $data ) {
            wp_die( 'Report data not found.' );
        }
        
        // Set headers for HTML output
        header( 'Content-Type: text/html; charset=utf-8' );
        
        // Output a message and the full HTML report
        echo '<div style="background: #f0f0f0; padding: 20px; margin-bottom: 20px; border: 1px solid #ccc; font-family: Arial, sans-serif;">';
        echo '<h3>📄 PDF Library Not Available</h3>';
        echo '<p><strong>Please use your browser\'s "Print to PDF" function to save this report as a PDF.</strong></p>';
        echo '<p>Press <kbd>Ctrl+P</kbd> (Windows) or <kbd>Cmd+P</kbd> (Mac) and select "Save as PDF".</p>';
        echo '<hr style="margin: 15px 0;">';
        echo '<p><em>Note: The report below contains all the same content and formatting that would appear in the PDF.</em></p>';
        echo '</div>';
        
        // Render the full HTML report with print mode enabled
        CRG_Render::render_report_html( $data, true );
        exit;
    }
    
    /**
     * Generate PDF using HTML template and basic conversion
     */
    private static function generate_with_simple_html( $data, $post_id ) {
        // Get HTML content from the template
        ob_start();
        CRG_Render::render_report_html( $data, true ); // true for print mode
        $html = ob_get_clean();
        
        // Try Dompdf if available
        if ( class_exists( 'Dompdf\\Dompdf' ) ) {
            return self::generate_with_dompdf_html( $html, $post_id );
        }
        
        // Otherwise use our ultra simple PDF
        return self::generate_ultra_simple_pdf( $data, $post_id );
    }
    
    /**
     * Generate PDF using Dompdf with HTML
     */
    private static function generate_with_dompdf_html( $html, $post_id ) {
        try {
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->set_option( 'isHtml5ParserEnabled', true );
            $dompdf->set_option( 'isRemoteEnabled', true );
            $dompdf->loadHtml( $html );
            $dompdf->setPaper( 'A4', 'portrait' );
            $dompdf->render();
            
            $filename = 'cleanup-report-' . $post_id . '-' . date( 'Y-m-d' ) . '.pdf';
            $dompdf->stream( $filename, array( 'Attachment' => true ) );
            exit;
        } catch ( Exception $e ) {
            error_log( 'Dompdf Error: ' . $e->getMessage() );
            // Fall back to simple PDF
            $data = CRG_Render::get_data( $post_id );
            return self::generate_ultra_simple_pdf( $data, $post_id );
        }
    }
    
    /**
     * Check if a PDF library is available
     */
    private static function is_library_available( $class_name ) {
        return class_exists( $class_name );
    }
    
    /**
     * Ultra simple PDF fallback - outputs printable HTML
     */
    private static function generate_ultra_simple_pdf( $data, $post_id ) {
        // If we can't generate a proper PDF, output the HTML report with print-friendly styling
        // and let the browser handle PDF conversion
        
        // Set headers for HTML output
        header( 'Content-Type: text/html; charset=utf-8' );
        header( 'Content-Disposition: inline; filename="cleanup-report-' . $post_id . '.html"' );
        
        // Output a message and the full HTML report
        echo '<div style="background: #f0f0f0; padding: 20px; margin-bottom: 20px; border: 1px solid #ccc;">';
        echo '<h3>PDF Library Not Available</h3>';
        echo '<p>Please use your browser\'s "Print to PDF" function to save this report as a PDF.</p>';
        echo '<p>Press Ctrl+P (Windows) or Cmd+P (Mac) and select "Save as PDF".</p>';
        echo '</div>';
        
        // Render the full HTML report
        CRG_Render::render_report_html( $data, true );
        exit;
    }
    
    /**
     * Split long text into lines for PDF output
     */
    private static function split_text( $text, $max_chars = 75 ) {
        $lines = [];
        $words = explode( ' ', $text );
        $current_line = '';
        
        foreach ( $words as $word ) {
            if ( strlen( $current_line . ' ' . $word ) > $max_chars ) {
                if ( !empty( $current_line ) ) {
                    $lines[] = $current_line;
                    $current_line = $word;
                } else {
                    $lines[] = $word;
                }
            } else {
                $current_line .= ( empty( $current_line ) ? '' : ' ' ) . $word;
            }
        }
        
        if ( !empty( $current_line ) ) {
            $lines[] = $current_line;
        }
        
        return $lines;
    }
    
    /**
     * Generate PDF using TCPDF (preferred)
     */
    private static function generate_with_tcpdf( $data, $post_id ) {
        require_once( ABSPATH . 'wp-content/plugins/cleanup-report-generator/lib/tcpdf/tcpdf.php' );
        
        $pdf = new TCPDF( PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false );
        
        // Set document information
        $pdf->SetCreator( 'Cleanup Report Generator' );
        $pdf->SetAuthor( get_option( 'crg_brand_name', 'Security Team' ) );
        $pdf->SetTitle( $data['title'] . ' - Cleanup Report' );
        $pdf->SetSubject( 'Malware Cleanup Report' );
        
        // Set default header data
        $pdf->SetHeaderData( '', 0, $data['title'], 'Cleanup Report - ' . date( 'Y-m-d' ) );
        
        // Set header and footer fonts
        $pdf->setHeaderFont( Array( PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN ) );
        $pdf->setFooterFont( Array( PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA ) );
        
        // Set default monospaced font
        $pdf->SetDefaultMonospacedFont( PDF_FONT_MONOSPACED );
        
        // Set margins
        $pdf->SetMargins( PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT );
        $pdf->SetHeaderMargin( PDF_MARGIN_HEADER );
        $pdf->SetFooterMargin( PDF_MARGIN_FOOTER );
        
        // Set auto page breaks
        $pdf->SetAutoPageBreak( TRUE, PDF_MARGIN_BOTTOM );
        
        // Set image scale factor
        $pdf->setImageScale( PDF_IMAGE_SCALE_RATIO );
        
        // Add a page
        $pdf->AddPage();
        
        // Generate HTML content
        $html = self::generate_pdf_html( $data );
        
        // Write HTML content
        $pdf->writeHTML( $html, true, false, true, false, '' );
        
        // Output PDF
        $filename = 'cleanup-report-' . $post_id . '-' . date( 'Y-m-d' ) . '.pdf';
        $pdf->Output( $filename, 'D' );
        exit;
    }
    
    /**
     * Generate PDF using Dompdf
     */
    private static function generate_with_dompdf( $data, $post_id ) {
        $dompdf = new \Dompdf\Dompdf();
        
        // Configure Dompdf
        $dompdf->set_option( 'isHtml5ParserEnabled', true );
        $dompdf->set_option( 'isRemoteEnabled', true );
        $dompdf->set_option( 'defaultFont', 'Arial' );
        
        // Generate HTML content
        $html = self::generate_pdf_html( $data );
        
        $dompdf->loadHtml( $html );
        $dompdf->setPaper( 'A4', 'portrait' );
        $dompdf->render();
        
        // Output PDF
        $filename = 'cleanup-report-' . $post_id . '-' . date( 'Y-m-d' ) . '.pdf';
        $dompdf->stream( $filename, array( 'Attachment' => true ) );
        exit;
    }
    
    /**
     * Generate PDF using mPDF
     */
    private static function generate_with_mpdf( $data, $post_id ) {
        $mpdf = new \Mpdf\Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'orientation' => 'P',
            'margin_left' => 15,
            'margin_right' => 15,
            'margin_top' => 16,
            'margin_bottom' => 16,
            'margin_header' => 9,
            'margin_footer' => 9
        ]);
        
        // Set document information
        $mpdf->SetTitle( $data['title'] . ' - Cleanup Report' );
        $mpdf->SetAuthor( get_option( 'crg_brand_name', 'Security Team' ) );
        $mpdf->SetCreator( 'Cleanup Report Generator' );
        
        // Generate HTML content
        $html = self::generate_pdf_html( $data );
        
        $mpdf->WriteHTML( $html );
        
        // Output PDF
        $filename = 'cleanup-report-' . $post_id . '-' . date( 'Y-m-d' ) . '.pdf';
        $mpdf->Output( $filename, 'D' );
        exit;
    }
    
    /**
     * Generate PDF using FPDF (basic fallback)
     */
    private static function generate_with_fpdf( $data, $post_id ) {
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont( 'Arial', 'B', 16 );
        
        // Title
        $pdf->Cell( 0, 10, utf8_decode( $data['title'] . ' - Cleanup Report' ), 0, 1, 'C' );
        $pdf->Ln( 10 );
        
        // Basic content
        $pdf->SetFont( 'Arial', '', 12 );
        $pdf->Cell( 0, 8, 'Date: ' . date( 'Y-m-d H:i:s' ), 0, 1 );
        $pdf->Cell( 0, 8, 'Site URL: ' . utf8_decode( $data['site_url'] ), 0, 1 );
        $pdf->Ln( 5 );
        
        // Summary
        $pdf->SetFont( 'Arial', 'B', 14 );
        $pdf->Cell( 0, 8, 'Summary', 0, 1 );
        $pdf->SetFont( 'Arial', '', 10 );
        
        $summary_items = [
            'Files Cleaned' => $data['files_cleaned'],
            'Threats Removed' => $data['threats_removed'], 
            'Scan Duration' => $data['scan_duration'],
            'Total Files Scanned' => $data['total_files']
        ];
        
        foreach ( $summary_items as $label => $value ) {
            if ( !empty( $value ) ) {
                $pdf->Cell( 0, 6, $label . ': ' . utf8_decode( $value ), 0, 1 );
            }
        }
        
        // Output PDF
        $filename = 'cleanup-report-' . $post_id . '-' . date( 'Y-m-d' ) . '.pdf';
        $pdf->Output( 'D', $filename );
        exit;
    }
    
    /**
     * Generate simple PDF as ultimate fallback
     */
    private static function generate_simple_pdf( $data, $post_id ) {
        // Create a simple text-based PDF using built-in PHP functionality
        $content = self::generate_text_content( $data );
        
        // Basic PDF structure
        $pdf_content = "%PDF-1.4\n";
        $pdf_content .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $pdf_content .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $pdf_content .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>\nendobj\n";
        $pdf_content .= "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>\nendobj\n";
        $pdf_content .= "5 0 obj\n<< /Length " . strlen( $content ) . " >>\nstream\n";
        $pdf_content .= "BT /F1 12 Tf 50 750 Td (" . addslashes( $content ) . ") Tj ET\n";
        $pdf_content .= "endstream\nendobj\n";
        $pdf_content .= "xref\n0 6\n0000000000 65535 f \n0000000009 00000 n \n0000000058 00000 n \n0000000115 00000 n \n0000000244 00000 n \n0000000323 00000 n \n";
        $pdf_content .= "trailer\n<< /Size 6 /Root 1 0 R >>\nstartxref\n" . strlen( $pdf_content ) . "\n%%EOF";
        
        // Output PDF
        header( 'Content-Type: application/pdf' );
        header( 'Content-Disposition: attachment; filename="cleanup-report-' . $post_id . '-' . date( 'Y-m-d' ) . '.pdf"' );
        header( 'Content-Length: ' . strlen( $pdf_content ) );
        echo $pdf_content;
        exit;
    }
    
    /**
     * Generate HTML content optimized for PDF
     */
    private static function generate_pdf_html( $data ) {
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title><?php echo esc_html( $data['title'] ); ?> - Cleanup Report</title>
            <style>
                body {
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                    line-height: 1.4;
                    color: #212529;
                    margin: 20px;
                }
                h1 {
                    color: #003366;
                    font-size: 24px;
                    margin-bottom: 20px;
                    text-align: center;
                    border-bottom: 2px solid #003366;
                    padding-bottom: 10px;
                }
                h2 {
                    color: #003366;
                    font-size: 18px;
                    margin-top: 25px;
                    margin-bottom: 15px;
                    border-left: 4px solid #003366;
                    padding-left: 10px;
                }
                h3 {
                    color: #495057;
                    font-size: 14px;
                    margin-top: 20px;
                    margin-bottom: 10px;
                }
                .summary-grid {
                    display: table;
                    width: 100%;
                    margin: 20px 0;
                }
                .summary-item {
                    display: table-cell;
                    width: 25%;
                    padding: 15px;
                    background: #F8F9FA;
                    border: 1px solid #E9ECEF;
                    text-align: center;
                    vertical-align: top;
                }
                .summary-number {
                    font-size: 24px;
                    font-weight: bold;
                    color: #003366;
                    display: block;
                }
                .summary-label {
                    font-size: 12px;
                    color: #6C757D;
                    margin-top: 5px;
                }
                .info-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 15px 0;
                }
                .info-table th,
                .info-table td {
                    border: 1px solid #E9ECEF;
                    padding: 8px;
                    text-align: left;
                }
                .info-table th {
                    background-color: #003366;
                    color: white;
                    font-weight: bold;
                }
                .info-table tr:nth-child(even) {
                    background-color: #F8F9FA;
                }
                .status {
                    padding: 4px 8px;
                    border-radius: 4px;
                    color: white;
                    font-size: 11px;
                    font-weight: bold;
                }
                .status.completed {
                    background-color: #00C851;
                }
                .status.warning {
                    background-color: #FFB400;
                }
                .status.pending {
                    background-color: #FF6900;
                }
                .checkbox {
                    width: 12px;
                    height: 12px;
                    display: inline-block;
                    border: 1px solid #333;
                    margin-right: 5px;
                }
                .checked {
                    background-color: #00C851;
                }
                .footer {
                    margin-top: 30px;
                    text-align: center;
                    font-size: 10px;
                    color: #666;
                    border-top: 1px solid #ddd;
                    padding-top: 10px;
                }
            </style>
        </head>
        <body>
            <h1><?php echo esc_html( $data['title'] ); ?> - Cleanup Report</h1>
            
            <div style="margin-bottom: 20px;">
                <strong>Date:</strong> <?php echo date( 'F j, Y \a\t g:i A' ); ?><br>
                <strong>Site URL:</strong> <?php echo esc_html( $data['site_url'] ); ?><br>
                <strong>Report Generated By:</strong> <?php echo esc_html( get_option( 'crg_brand_name', 'Security Team' ) ); ?>
            </div>
            
            <h2>Summary</h2>
            <div class="summary-grid">
                <div class="summary-item">
                    <span class="summary-number"><?php echo esc_html( $data['files_cleaned'] ?: '0' ); ?></span>
                    <div class="summary-label">Files Cleaned</div>
                </div>
                <div class="summary-item">
                    <span class="summary-number"><?php echo esc_html( $data['threats_removed'] ?: '0' ); ?></span>
                    <div class="summary-label">Threats Removed</div>
                </div>
                <div class="summary-item">
                    <span class="summary-number"><?php echo esc_html( $data['scan_duration'] ?: 'N/A' ); ?></span>
                    <div class="summary-label">Scan Duration</div>
                </div>
                <div class="summary-item">
                    <span class="summary-number"><?php echo esc_html( $data['total_files'] ?: '0' ); ?></span>
                    <div class="summary-label">Files Scanned</div>
                </div>
            </div>
            
            <?php if ( !empty( $data['timeline'] ) ): ?>
            <h2>Cleanup Timeline</h2>
            <table class="info-table">
                <thead>
                    <tr>
                        <th>Step</th>
                        <th>Status</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $timeline = explode( "\n", $data['timeline'] );
                    foreach ( $timeline as $item ): 
                        if ( trim( $item ) ):
                    ?>
                    <tr>
                        <td><?php echo esc_html( trim( $item ) ); ?></td>
                        <td><span class="status completed">Completed</span></td>
                        <td>Successfully executed</td>
                    </tr>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </tbody>
            </table>
            <?php endif; ?>
            
            <?php if ( !empty( $data['defender_checklist'] ) ): ?>
            <h2>WPMU DEV Defender Configuration</h2>
            <table class="info-table">
                <thead>
                    <tr>
                        <th style="width: 30px;">✓</th>
                        <th>Configuration Item</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $checklist = [
                        'scan_core' => 'WordPress Core File Scanning',
                        'scan_plugins' => 'Plugin Vulnerability Scanning', 
                        'scan_themes' => 'Theme Security Scanning',
                        'malware_scan' => 'Malware Detection',
                        'ip_lockout' => 'IP Lockout Protection',
                        'login_protection' => 'Login Protection',
                        'two_factor' => 'Two-Factor Authentication',
                        'security_headers' => 'Security Headers',
                        'audit_logging' => 'Audit Logging'
                    ];
                    
                    foreach ( $checklist as $key => $label ):
                        $checked = !empty( $data['defender_' . $key] );
                    ?>
                    <tr>
                        <td><span class="checkbox <?php echo $checked ? 'checked' : ''; ?>"></span></td>
                        <td><?php echo esc_html( $label ); ?></td>
                        <td><span class="status <?php echo $checked ? 'completed' : 'pending'; ?>"><?php echo $checked ? 'Enabled' : 'Pending'; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            
            <?php if ( !empty( $data['action_items'] ) ): ?>
            <h2>Action Items</h2>
            <table class="info-table">
                <thead>
                    <tr>
                        <th>Action</th>
                        <th>Priority</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $actions = explode( "\n", $data['action_items'] );
                    foreach ( $actions as $action ): 
                        if ( trim( $action ) ):
                    ?>
                    <tr>
                        <td><?php echo esc_html( trim( $action ) ); ?></td>
                        <td><span class="status warning">Medium</span></td>
                        <td><span class="status pending">Pending</span></td>
                    </tr>
                    <?php 
                        endif;
                    endforeach; 
                    ?>
                </tbody>
            </table>
            <?php endif; ?>
            
            <div class="footer">
                Generated by Cleanup Report Generator on <?php echo date( 'Y-m-d H:i:s' ); ?>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generate text content for simple PDF fallback
     */
    private static function generate_text_content( $data ) {
        $content = $data['title'] . " - Cleanup Report\n\n";
        $content .= "Date: " . date( 'Y-m-d H:i:s' ) . "\n";
        $content .= "Site URL: " . $data['site_url'] . "\n\n";
        $content .= "SUMMARY\n";
        $content .= "Files Cleaned: " . ( $data['files_cleaned'] ?: '0' ) . "\n";
        $content .= "Threats Removed: " . ( $data['threats_removed'] ?: '0' ) . "\n";
        $content .= "Scan Duration: " . ( $data['scan_duration'] ?: 'N/A' ) . "\n";
        $content .= "Total Files: " . ( $data['total_files'] ?: '0' ) . "\n\n";
        
        if ( !empty( $data['timeline'] ) ) {
            $content .= "TIMELINE\n";
            $content .= $data['timeline'] . "\n\n";
        }
        
        if ( !empty( $data['action_items'] ) ) {
            $content .= "ACTION ITEMS\n";
            $content .= $data['action_items'] . "\n\n";
        }
        
        return $content;
    }
    
    /**
     * Get list of available PDF libraries
     */
    public static function get_available_libraries() {
        $available = [];
        foreach ( self::$libraries as $key => $class ) {
            if ( self::is_library_available( $class ) ) {
                $available[] = $key;
            }
        }
        return $available;
    }
    
    /**
     * Install notification for PDF libraries status
     */
    public static function show_library_notice() {
        global $pagenow;
        
        // Only show on cleanup report pages
        if ( $pagenow !== 'edit.php' || $_GET['post_type'] !== 'cleanup_report' ) {
            return;
        }
        
        $available = self::get_available_libraries();
        
        if ( in_array( 'dompdf', $available ) ) {
            echo '<div class="notice notice-success"><p><strong>PDF Export Ready:</strong> Dompdf library detected - full HTML-to-PDF conversion available.</p></div>';
        } elseif ( !empty( $available ) ) {
            echo '<div class="notice notice-success"><p><strong>PDF Export Ready:</strong> PDF library available (' . implode( ', ', $available ) . ').</p></div>';
        } else {
            echo '<div class="notice notice-warning"><p><strong>PDF Export Limited:</strong> Basic PDF generation available. Install TCPDF, Dompdf, or mPDF for better formatting.</p></div>';
        }
    }
}

} // End class_exists check