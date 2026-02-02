<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CRG_Render {

    public static function get_data( $post_id ) {
        $m = array_map(function($a){ return $a[0]; }, get_post_meta($post_id));
        $brand = [
            'name' => get_option('crg_brand_name','WPMU DEV Services'),
            'logo' => get_option('crg_brand_logo',''),
            'primary' => get_option('crg_brand_primary','#003366'),
            'accent'  => get_option('crg_brand_accent','#00C851'),
            'footer'  => get_option('crg_footer_text','© ' . date('Y') . ' WPMU DEV Services'),
        ];
        
        // Handle screenshots (new format)
        $screenshots = get_post_meta($post_id, 'crg_screenshots', true);
        if (!is_array($screenshots)) {
            // Migrate old format if exists
            $screenshots = [];
            if (!empty($m['crg_ss_before'])) {
                $screenshots[] = [
                    'url' => $m['crg_ss_before'],
                    'title' => 'Security Scan - Before Cleaning',
                    'type' => 'before'
                ];
            }
            if (!empty($m['crg_ss_after'])) {
                $screenshots[] = [
                    'url' => $m['crg_ss_after'],
                    'title' => 'Security Scan - After Cleaning',
                    'type' => 'after'
                ];
            }
        }
        
        // Filter out empty screenshots
        $screenshots = array_filter($screenshots, function($screenshot) {
            return !empty($screenshot['url']);
        });
        
        // Parse actions
        $actions_raw = $m['crg_actions'] ?? '';
        $lines = preg_split('/\r\n|\r|\n/', $actions_raw);
        $actions = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if (!$line) continue;
            if (preg_match('/^\[(CRITICAL|RECOMMENDED|OPTIONAL)\]\s*(.*)$/i', $line, $matches)) {
                $actions[] = ['level'=>strtoupper($matches[1]), 'text'=>$matches[2]];
            } else {
                $actions[] = ['level'=>'OPTIONAL', 'text'=>preg_replace('/^\[(.*?)\]\s*/','',$line)];
            }
        }
        
        // Collect Defender configuration data
        $defender_configs = [
            'defender_recommendations' => 'Defender recommendations',
            'malware_scanning' => 'Scheduled Malware scanning',
            'audit_logging' => 'Audit logging enabled',
            'firewall_antibot' => 'Firewall anti bot',
            'firewall_404' => 'Firewall 404 detection',
            'user_agent_banning' => 'User Agent Banning',
            'user_agent_bot_trap' => 'User Agent Bot Trap',
            'user_agent_empty_headers' => 'Empty Headers Protection',
            'user_agent_script_presets' => 'User Agent Script Presets',
            'two_factor_auth' => 'Two-Factor Authentication (2FA)',
            'mask_login' => 'Mask Login Area',
            'security_headers' => 'Security Headers',
            'pwned_passwords' => 'Pwned Passwords Check',
            'strong_passwords' => 'Strong Passwords Policy',
            'password_reset' => 'Password Reset Protection',
            'session_protection' => 'Session Protection',
            'google_recaptcha' => 'Google reCAPTCHA',
            'ip_detection' => 'Correct IP Detection'
        ];
        
        $defender_data = [];
        foreach ($defender_configs as $key => $label) {
            $status = $m['crg_defender_' . $key] ?? '';
            $notes = $m['crg_defender_' . $key . '_notes'] ?? '';
            if (!empty($status)) {
                $defender_data[] = [
                    'setting' => $label,
                    'status' => $status,
                    'notes' => $notes
                ];
            }
        }
        
        return [
            'post_id' => $post_id,
            'title' => get_the_title($post_id),
            'client' => $m['crg_client_name'] ?? '',
            'site' => $m['crg_site_url'] ?? '',
            'date' => $m['crg_report_date'] ?? '',
            'request_link' => $m['crg_request_link'] ?? '',
            // Legacy screenshot support
            'ss_before' => $m['crg_ss_before'] ?? '',
            'ss_after' => $m['crg_ss_after'] ?? '',
            // New multiple screenshots support
            'screenshots' => $screenshots,
            // WYSIWYG content (apply wpautop for proper formatting)
            'infection_summary' => wpautop($m['crg_infection_summary'] ?? ''),
            's_critical' => intval($m['crg_summary_critical'] ?? 0),
            's_medium' => intval($m['crg_summary_medium'] ?? 0),
            's_low' => intval($m['crg_summary_low'] ?? 0),
            'highlights' => wpautop($m['crg_highlights'] ?? ''),
            'actions' => $actions,
            'reinstall_list' => wpautop($m['crg_reinstall_list'] ?? ''),
            'replace_list' => wpautop($m['crg_replace_list'] ?? ''),
            'ssl' => $m['crg_ssl'] ?? '',
            'cloudflare' => $m['crg_cloudflare'] ?? '',
            'check_admins' => $m['crg_check_admins'] ?? '',
            'reset_passwords' => $m['crg_reset_passwords'] ?? '',
            'mask_login' => $m['crg_mask_login'] ?? '',
            'verification_summary' => wpautop($m['crg_verification_summary'] ?? ''),
            'defender_checklist' => $defender_data,
            'documentation_links' => $m['crg_documentation_links'] ?? '',
            'pending_actions' => $m['crg_pending_actions'] ?? '',
            'seo_note' => $m['crg_seo_note'] ?? '',
            'protect_note' => $m['crg_protect_note'] ?? '',
            'brand' => $brand,
        ];
    }

    public static function quickchart_url($type, $labels, $data) {
        $cfg = ['type'=>$type,'data'=>['labels'=>$labels,'datasets'=>[['data'=>$data]]],'options'=>['plugins'=>['legend'=>['position'=>'bottom']]]];
        $url = 'https://quickchart.io/chart?c=' . rawurlencode(json_encode($cfg));
        return esc_url($url);
    }

    public static function render_report_html( $data, $print_mode=false ) {
        // Ensure print_mode is properly set
        $print_mode = (bool) $print_mode;
        
        // Generate chart URL for non-PDF mode only
        if (!$print_mode) {
            $labels = ['Critical','Medium','Low'];
            $chart1 = self::quickchart_url('doughnut',$labels,[$data['s_critical'],$data['s_medium'],$data['s_low']]);
        } else {
            $chart1 = ''; // No external charts in PDF mode
        }
        
        $brand_css = sprintf(':root{--primary:%s;--accent:%s}', esc_attr($data['brand']['primary']), esc_attr($data['brand']['accent']));
        include CRG_PATH . 'templates/report-template.php';
    }

    public static function render_report_page( $post_id, $print_mode=false ) {
        $data = self::get_data($post_id);
        status_header(200);
        nocache_headers();
        self::render_report_html( $data, $print_mode );
    }

    public static function output_pdf_dompdf( $post_id ) {
        $data = self::get_data($post_id);
        ob_start();
        self::render_report_html( $data, false );
        $html = ob_get_clean();

        // Dompdf render
        $dompdf = new \Dompdf\Dompdf();
        $dompdf->loadHtml( $html );
        $dompdf->setPaper('A4','portrait');
        $dompdf->render();
        $pdf = $dompdf->output();

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="cleanup-report-'.$post_id.'.pdf"');
        echo $pdf;
    }
}
