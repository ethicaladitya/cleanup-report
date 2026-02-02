<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

class CRG_Admin {

    public static function register_meta_boxes() {
        add_meta_box('crg_meta_main', 'Report Details', [ __CLASS__, 'meta_main' ], 'cleanup_report', 'normal', 'high');
        add_meta_box('crg_meta_actions', 'Actions & Follow-ups', [ __CLASS__, 'meta_actions' ], 'cleanup_report', 'normal', 'default');
        add_meta_box('crg_meta_info', 'Important Information (Client Guidance)', [ __CLASS__, 'meta_info' ], 'cleanup_report', 'normal', 'low');
        
        // Add review workflow box for editors
        if (current_user_can('edit_others_posts')) {
            add_meta_box('crg_review', 'Review Workflow', [__CLASS__, 'meta_review'], 'cleanup_report', 'side', 'high');
        }
        
        // Add status box for authors
        if (current_user_can('edit_posts') && !current_user_can('edit_others_posts')) {
            add_meta_box('crg_status', 'Report Status', [__CLASS__, 'meta_status'], 'cleanup_report', 'side', 'high');
        }
    }

    private static function f($post, $k, $d=''){
        $m = get_post_meta($post->ID);
        return esc_attr($m[$k][0] ?? $d);
    }

    public static function meta_main( $post ) {
        wp_enqueue_media();
        $f = function($k,$d='') use ($post){ return self::f($post,$k,$d); };
        ?>
        <style>.crg-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:12px}.crg-grid2{display:grid;grid-template-columns:repeat(2,1fr);gap:12px}.crg-box{border:1px solid #e5e7eb;padding:12px;border-radius:8px;background:#fff}</style>
        <div class="crg-grid">
            <div class="crg-box"><label>Client Name<input class="widefat" name="crg_client_name" value="<?php echo $f('crg_client_name'); ?>"></label></div>
            <div class="crg-box"><label>Website URL<input class="widefat" name="crg_site_url" value="<?php echo $f('crg_site_url'); ?>" placeholder="https://example.com"></label></div>
            <div class="crg-box"><label>Report Date<input type="date" class="widefat" name="crg_report_date" value="<?php echo $f('crg_report_date', date('Y-m-d')); ?>"></label></div>
        </div>
        <div class="crg-box" style="margin-top:12px;">
            <label>Request Link<input class="widefat" name="crg_request_link" value="<?php echo $f('crg_request_link'); ?>" placeholder="Internal request URL"></label>
        </div>

        <h4>Screenshots</h4>
        <div class="crg-box">
            <div class="crg-help">Add multiple screenshots to document the security scan process. You can add before/after screenshots and additional evidence.</div>
            
            <div id="crg-screenshots-container">
                <?php
                $screenshots = get_post_meta($post->ID, 'crg_screenshots', true);
                if (!is_array($screenshots)) {
                    // Migrate old format
                    $screenshots = [];
                    if ($f('crg_ss_before')) {
                        $screenshots[] = [
                            'url' => $f('crg_ss_before'),
                            'title' => 'Security Scan - Before Cleaning',
                            'type' => 'before'
                        ];
                    }
                    if ($f('crg_ss_after')) {
                        $screenshots[] = [
                            'url' => $f('crg_ss_after'),
                            'title' => 'Security Scan - After Cleaning',
                            'type' => 'after'
                        ];
                    }
                }
                
                if (empty($screenshots)) {
                    $screenshots = [
                        ['url' => '', 'title' => 'Security Scan - Before Cleaning', 'type' => 'before'],
                        ['url' => '', 'title' => 'Security Scan - After Cleaning', 'type' => 'after']
                    ];
                }
                
                foreach ($screenshots as $index => $screenshot): ?>
                <div class="screenshot-item" data-index="<?php echo $index; ?>">
                    <div style="display: grid; grid-template-columns: 1fr 200px 100px 30px; gap: 12px; align-items: end; margin-bottom: 12px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px;">
                        <div>
                            <label>Screenshot URL</label>
                            <input class="widefat" name="crg_screenshots[<?php echo $index; ?>][url]" value="<?php echo esc_attr($screenshot['url'] ?? ''); ?>" placeholder="Image URL">
                        </div>
                        <div>
                            <label>Title/Description</label>
                            <input class="widefat" name="crg_screenshots[<?php echo $index; ?>][title]" value="<?php echo esc_attr($screenshot['title'] ?? ''); ?>" placeholder="Screenshot description">
                        </div>
                        <div>
                            <label>Type</label>
                            <select name="crg_screenshots[<?php echo $index; ?>][type]" class="widefat">
                                <option value="before" <?php selected($screenshot['type'] ?? '', 'before'); ?>>Before</option>
                                <option value="after" <?php selected($screenshot['type'] ?? '', 'after'); ?>>After</option>
                                <option value="evidence" <?php selected($screenshot['type'] ?? '', 'evidence'); ?>>Evidence</option>
                                <option value="other" <?php selected($screenshot['type'] ?? '', 'other'); ?>>Other</option>
                            </select>
                        </div>
                        <div>
                            <button type="button" class="button crg-pick" data-media-target="crg_screenshots[<?php echo $index; ?>][url]">📁</button>
                            <button type="button" class="button remove-screenshot" style="color: #dc3545; margin-top: 4px;">🗑️</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <button type="button" id="add-screenshot" class="button button-secondary">+ Add Another Screenshot</button>
        </div>

        <script>
        jQuery(document).ready(function($) {
            let screenshotIndex = <?php echo count($screenshots); ?>;
            
            $('#add-screenshot').on('click', function() {
                const html = `
                <div class="screenshot-item" data-index="${screenshotIndex}">
                    <div style="display: grid; grid-template-columns: 1fr 200px 100px 30px; gap: 12px; align-items: end; margin-bottom: 12px; padding: 12px; border: 1px solid #e5e7eb; border-radius: 8px;">
                        <div>
                            <label>Screenshot URL</label>
                            <input class="widefat" name="crg_screenshots[${screenshotIndex}][url]" value="" placeholder="Image URL">
                        </div>
                        <div>
                            <label>Title/Description</label>
                            <input class="widefat" name="crg_screenshots[${screenshotIndex}][title]" value="" placeholder="Screenshot description">
                        </div>
                        <div>
                            <label>Type</label>
                            <select name="crg_screenshots[${screenshotIndex}][type]" class="widefat">
                                <option value="before">Before</option>
                                <option value="after">After</option>
                                <option value="evidence" selected>Evidence</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <button type="button" class="button crg-pick" data-media-target="crg_screenshots[${screenshotIndex}][url]">📁</button>
                            <button type="button" class="button remove-screenshot" style="color: #dc3545; margin-top: 4px;">🗑️</button>
                        </div>
                    </div>
                </div>`;
                $('#crg-screenshots-container').append(html);
                screenshotIndex++;
            });
            
            $(document).on('click', '.remove-screenshot', function() {
                $(this).closest('.screenshot-item').remove();
            });
        });
        </script>

        <h4>Summary of Infection</h4>
        <div class="crg-box">
            <p class="crg-help">Write a detailed summary with formatting. Cover: unsafe plugins/themes detected, outdated plugins, suggested improvements (e.g., reduce plugin count).</p>
            <?php
            $infection_summary = get_post_meta($post->ID, 'crg_infection_summary', true);
            wp_editor($infection_summary, 'crg_infection_summary', [
                'textarea_name' => 'crg_infection_summary',
                'media_buttons' => false,
                'textarea_rows' => 8,
                'teeny' => false,
                'tinymce' => [
                    'toolbar1' => 'bold,italic,underline,strikethrough,|,bullist,numlist,blockquote,|,link,unlink,|,undo,redo',
                    'toolbar2' => 'formatselect,forecolor,backcolor,|,alignleft,aligncenter,alignright,|,outdent,indent,|,removeformat',
                    'toolbar3' => '',
                ],
                'quicktags' => [
                    'buttons' => 'strong,em,ul,ol,li,link,close'
                ]
            ]);
            ?>
        </div>

        <h4>Summary Counts</h4>
        <div class="crg-grid">
            <div class="crg-box"><label>Critical <input type="number" name="crg_summary_critical" value="<?php echo $f('crg_summary_critical',0); ?>"></label></div>
            <div class="crg-box"><label>Medium <input type="number" name="crg_summary_medium" value="<?php echo $f('crg_summary_medium',0); ?>"></label></div>
            <div class="crg-box"><label>Low <input type="number" name="crg_summary_low" value="<?php echo $f('crg_summary_low',0); ?>"></label></div>
        </div>

        <div class="crg-box" style="margin-top:12px;">
            <label><strong>Highlights / Executive Summary</strong></label>
            <p class="crg-help">Key findings and recommendations for executives and stakeholders.</p>
            <?php
            $highlights = get_post_meta($post->ID, 'crg_highlights', true);
            wp_editor($highlights, 'crg_highlights', [
                'textarea_name' => 'crg_highlights',
                'media_buttons' => false,
                'textarea_rows' => 6,
                'teeny' => false,
                'tinymce' => [
                    'toolbar1' => 'bold,italic,underline,|,bullist,numlist,|,link,unlink,|,undo,redo',
                    'toolbar2' => 'formatselect,forecolor,|,alignleft,aligncenter,alignright,|,removeformat',
                    'toolbar3' => '',
                ],
                'quicktags' => [
                    'buttons' => 'strong,em,ul,ol,li,link,close'
                ]
            ]);
            ?>
        </div>
        <?php
        wp_nonce_field('crg_save_'.$post->ID, 'crg_nonce');
    }

    public static function meta_actions( $post ) {
        $m = get_post_meta($post->ID);
        $f = function($k,$d='') use ($m){ return esc_attr($m[$k][0] ?? $d); };
        $cb = function($name) use ($m){ $v = $m[$name][0] ?? ''; return [
            'req' => checked($v,'required',false),
            'nr'  => checked($v,'not_required',false),
        ]; };
        $ssl = $cb('crg_ssl'); $cf = $cb('crg_cloudflare'); $admins = $cb('crg_check_admins'); $reset = $cb('crg_reset_passwords'); $mask = $cb('crg_mask_login');
        ?>
        <h4>WPMU DEV Cleanup – Actions Taken (standard)</h4>
        <div class="crg-help">These are typically performed for every cleanup.</div>
        <ul class="crg-box">
            <li>Create a backup whenever possible</li>
            <li>Evaluate the infection</li>
            <li>Manually check known malware locations and scripts</li>
            <li>Scan the website using different tools</li>
            <li>Perform cleanup, update plugins & theme, reinstall free plugins when applicable</li>
            <li>Apply Defender security configuration</li>
            <li>Manually review plugin and theme list for known issues</li>
        </ul>

        <h4>Actions required by client</h4>
        <div class="crg-box">
            <label><strong>Reinstall the following premium plugins & themes (not provided):</strong></label>
            <?php
            $reinstall_list = get_post_meta($post->ID, 'crg_reinstall_list', true);
            wp_editor($reinstall_list, 'crg_reinstall_list', [
                'textarea_name' => 'crg_reinstall_list',
                'media_buttons' => false,
                'textarea_rows' => 4,
                'teeny' => true,
                'tinymce' => [
                    'toolbar1' => 'bold,italic,bullist,numlist,link,unlink,undo,redo',
                    'toolbar2' => '',
                ],
                'quicktags' => [
                    'buttons' => 'strong,em,ul,ol,li,link,close'
                ]
            ]);
            ?>
        </div>
        <div class="crg-box">
            <label><strong>Replace the following plugins & themes</strong> (no updates in past year / deprecated):</label>
            <?php
            $replace_list = get_post_meta($post->ID, 'crg_replace_list', true);
            wp_editor($replace_list, 'crg_replace_list', [
                'textarea_name' => 'crg_replace_list',
                'media_buttons' => false,
                'textarea_rows' => 4,
                'teeny' => true,
                'tinymce' => [
                    'toolbar1' => 'bold,italic,bullist,numlist,link,unlink,undo,redo',
                    'toolbar2' => '',
                ],
                'quicktags' => [
                    'buttons' => 'strong,em,ul,ol,li,link,close'
                ]
            ]);
            ?>
        </div>

        <h4>Configuration / Security follow-ups</h4>
        <div class="crg-grid">
            <div class="crg-box">
                <strong>Configure SSL</strong><br>
                <label><input type="radio" name="crg_ssl" value="required" <?php echo $ssl['req']; ?>> Required</label><br>
                <label><input type="radio" name="crg_ssl" value="not_required" <?php echo $ssl['nr']; ?>> Not Required</label>
            </div>
            <div class="crg-box">
                <strong>Configure Cloudflare</strong><div class="crg-help">Cloudflare will cache globally and add security.</div>
                <label><input type="radio" name="crg_cloudflare" value="required" <?php echo $cf['req']; ?>> Required</label><br>
                <label><input type="radio" name="crg_cloudflare" value="not_required" <?php echo $cf['nr']; ?>> Not Required</label>
            </div>
            <div class="crg-box">
                <strong>Check any suspect admins</strong><br>
                <label><input type="radio" name="crg_check_admins" value="required" <?php echo $admins['req']; ?>> Required</label><br>
                <label><input type="radio" name="crg_check_admins" value="not_required" <?php echo $admins['nr']; ?>> Not Required</label>
            </div>
            <div class="crg-box">
                <strong>Reset all admin passwords</strong><br>
                <label><input type="radio" name="crg_reset_passwords" value="required" <?php echo $reset['req']; ?>> Required</label><br>
                <label><input type="radio" name="crg_reset_passwords" value="not_required" <?php echo $reset['nr']; ?>> Not Required</label>
            </div>
            <div class="crg-box">
                <strong>Configure Defender mask login URL</strong><div class="crg-help"><a href="https://wpmudev.com/docs/wpmu-dev-plugins/defender/#mask-login-area" target="_blank">Docs: Mask login area</a></div>
                <label><input type="radio" name="crg_mask_login" value="required" <?php echo $mask['req']; ?>> Required</label><br>
                <label><input type="radio" name="crg_mask_login" value="not_required" <?php echo $mask['nr']; ?>> Not Required</label>
                <div class="crg-help">Note: Mask URL & notifications require manual action by the site owner.</div>
            </div>
        </div>
        <?php
    }

    public static function meta_info( $post ) {
        $m = get_post_meta($post->ID);
        ?>
        <div class="crg-box">
            <h4>📋 Verification Summary</h4>
            <p class="crg-help">Document verification steps taken and current security status with detailed formatting.</p>
            <?php
            $verification_summary = get_post_meta($post->ID, 'crg_verification_summary', true);
            if (empty($verification_summary)) {
                $verification_summary = "We performed a full malware check on the website:\n\n• WordPress core, plugins and theme integrity;\n• Database scan for malware signatures;\n• Hidden PHP files inside upload directory;\n• Front end scan using Sucuri and Virus total;\n• Plugins and theme security audit;\n• Manual check for theme functions.php, header.php and footer.php\n• Manual audit for Admin users to verify if any user has configured the App password";
            }
            wp_editor($verification_summary, 'crg_verification_summary', [
                'textarea_name' => 'crg_verification_summary',
                'media_buttons' => false,
                'textarea_rows' => 8,
                'teeny' => false,
                'tinymce' => [
                    'toolbar1' => 'bold,italic,underline,|,bullist,numlist,blockquote,|,link,unlink,|,undo,redo',
                    'toolbar2' => 'formatselect,forecolor,|,alignleft,aligncenter,alignright,|,outdent,indent,|,removeformat',
                    'toolbar3' => '',
                ],
                'quicktags' => [
                    'buttons' => 'strong,em,ul,ol,li,link,close'
                ]
            ]);
            ?>
        </div>

        <div class="crg-box">
            <h4>🛡️ Defender Configuration Checklist</h4>
            <p class="crg-help">Track WPMU DEV Defender security configurations applied. Our team will configure Defender settings to ensure your website is safe and protected, but some configurations require your attention.</p>
            
            <div class="crg-grid">
                <?php 
                $m = get_post_meta($post->ID);
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
                
                foreach ($defender_configs as $key => $label): 
                    $status = get_post_meta($post->ID, 'crg_defender_' . $key, true);
                    $notes = get_post_meta($post->ID, 'crg_defender_' . $key . '_notes', true);
                ?>
                <div class="crg-card" style="margin-bottom: 16px; padding: 16px; border: 1px solid #e2e8f0; border-radius: 8px;">
                    <div style="font-weight: 600; margin-bottom: 8px;"><?php echo esc_html($label); ?></div>
                    <div style="margin-bottom: 8px;">
                        <label style="margin-right: 16px;">
                            <input type="radio" name="crg_defender_<?php echo $key; ?>" value="applied" <?php checked($status, 'applied'); ?>>
                            ✅ Applied
                        </label>
                        <label>
                            <input type="radio" name="crg_defender_<?php echo $key; ?>" value="pending" <?php checked($status, 'pending'); ?>>
                            ⚠️ Pending
                        </label>
                    </div>
                    <div>
                        <label style="font-size: 13px; color: #64748b;">Notes:</label>
                        <input type="text" name="crg_defender_<?php echo $key; ?>_notes" value="<?php echo esc_attr($notes); ?>" class="widefat" style="font-size: 13px;" placeholder="Configuration details, links, or instructions...">
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="crg-box">
            <h4>📚 Documentation & Resources</h4>
            <p class="crg-help">Provide helpful links for the client (one per line).</p>
            <textarea class="widefat" rows="5" name="crg_documentation_links" placeholder="WPMU DEV Defender Setup Guide: https://wpmudev.com/docs/wpmu-dev-plugins/defender/&#10;WordPress Security Best Practices: https://wpmudev.com/blog/wordpress-security/&#10;Two-Factor Authentication Setup: https://wpmudev.com/docs/wpmu-dev-plugins/defender/#two-factor-authentication&#10;Malware Prevention Guide: https://wpmudev.com/blog/wordpress-malware-removal/&#10;Site Backup Best Practices: https://wpmudev.com/docs/wpmu-dev-plugins/snapshot/"><?php echo esc_textarea($m['crg_documentation_links'][0] ?? ''); ?></textarea>
        </div>

        <div class="crg-box">
            <h4>⚠️ Pending Actions</h4>
            <p class="crg-help">List any actions that still need to be completed (leave empty if none).</p>
            <textarea class="widefat" rows="3" name="crg_pending_actions" placeholder="As no malware was identified, there aren't any further pending actions."><?php echo esc_textarea($m['crg_pending_actions'][0] ?? ''); ?></textarea>
        </div>

        <div class="crg-box">
            <h4>It is important to know – SEO hacking</h4>
            <p class="crg-help">Advise the client to search <code>site:mysite.com</code> on Google to spot unwanted indexed pages. If present, resubmit sitemap in Google & Bing Search Console; allow time for reindexing.</p>
            <textarea class="widefat" rows="4" name="crg_seo_note"><?php echo esc_textarea($m['crg_seo_note'][0] ?? ''); ?></textarea>
        </div>
        <div class="crg-box">
            <h4>Protect your site</h4>
            <p class="crg-help">Key recommendations (2FA, updates via Automate, etc.).</p>
            <textarea class="widefat" rows="4" name="crg_protect_note"><?php echo esc_textarea($m['crg_protect_note'][0] ?? "Configure and enforce 2FA (WPMU DEV Defender).\nKeep plugins & themes updated (WPMU DEV Automate)."); ?></textarea>
        </div>
        <?php
    }

    public static function meta_review( $post ) {
        $status = get_post_meta($post->ID, 'crg_review_status', true) ?: 'draft';
        $author_notes = get_post_meta($post->ID, 'crg_author_notes', true);
        $review_notes = get_post_meta($post->ID, 'crg_review_notes', true);
        $submitted_date = get_post_meta($post->ID, 'crg_submitted_date', true);
        $reviewed_date = get_post_meta($post->ID, 'crg_reviewed_date', true);
        ?>
        <div class="crg-review-workflow">
            <div style="margin-bottom: 16px;">
                <strong>Current Status:</strong>
                <span class="crg-status-badge crg-status-<?php echo esc_attr($status); ?>">
                    <?php 
                    switch($status) {
                        case 'draft': echo '📝 Draft'; break;
                        case 'submitted': echo '📋 Submitted for Review'; break;
                        case 'reviewed': echo '✅ Reviewed'; break;
                        case 'approved': echo '🎉 Approved'; break;
                        case 'needs_revision': echo '🔄 Needs Revision'; break;
                    }
                    ?>
                </span>
            </div>

            <?php if ($submitted_date): ?>
            <div style="margin-bottom: 12px; font-size: 13px; color: #666;">
                <strong>Submitted:</strong> <?php echo date('M j, Y g:i A', strtotime($submitted_date)); ?>
            </div>
            <?php endif; ?>

            <?php if ($reviewed_date): ?>
            <div style="margin-bottom: 12px; font-size: 13px; color: #666;">
                <strong>Reviewed:</strong> <?php echo date('M j, Y g:i A', strtotime($reviewed_date)); ?>
            </div>
            <?php endif; ?>

            <?php if ($author_notes): ?>
            <div style="margin-bottom: 16px;">
                <strong>Author Notes:</strong>
                <div style="padding: 8px; background: #f8fafc; border-radius: 4px; font-size: 13px;">
                    <?php echo nl2br(esc_html($author_notes)); ?>
                </div>
            </div>
            <?php endif; ?>

            <div style="margin-bottom: 16px;">
                <label><strong>Review Status:</strong></label>
                <select name="crg_review_status" style="width: 100%; margin-top: 4px;">
                    <option value="draft" <?php selected($status, 'draft'); ?>>📝 Draft</option>
                    <option value="submitted" <?php selected($status, 'submitted'); ?>>📋 Submitted for Review</option>
                    <option value="reviewed" <?php selected($status, 'reviewed'); ?>>✅ Reviewed</option>
                    <option value="approved" <?php selected($status, 'approved'); ?>>🎉 Approved</option>
                    <option value="needs_revision" <?php selected($status, 'needs_revision'); ?>>🔄 Needs Revision</option>
                </select>
            </div>

            <div>
                <label><strong>Review Notes:</strong></label>
                <textarea name="crg_review_notes" rows="4" style="width: 100%; margin-top: 4px;" placeholder="Add notes for the author about required changes, suggestions, or approval comments..."><?php echo esc_textarea($review_notes); ?></textarea>
            </div>
        </div>
        <?php
    }

    public static function meta_status( $post ) {
        $status = get_post_meta($post->ID, 'crg_review_status', true) ?: 'draft';
        $author_notes = get_post_meta($post->ID, 'crg_author_notes', true);
        $review_notes = get_post_meta($post->ID, 'crg_review_notes', true);
        $submitted_date = get_post_meta($post->ID, 'crg_submitted_date', true);
        ?>
        <div class="crg-author-status">
            <div style="margin-bottom: 16px;">
                <strong>Report Status:</strong>
                <span class="crg-status-badge crg-status-<?php echo esc_attr($status); ?>">
                    <?php 
                    switch($status) {
                        case 'draft': echo '📝 Draft'; break;
                        case 'submitted': echo '📋 Under Review'; break;
                        case 'reviewed': echo '✅ Reviewed'; break;
                        case 'approved': echo '🎉 Approved'; break;
                        case 'needs_revision': echo '🔄 Needs Revision'; break;
                    }
                    ?>
                </span>
            </div>

            <?php if ($status === 'draft'): ?>
            <div style="margin-bottom: 16px;">
                <label><strong>Notes for Reviewer:</strong></label>
                <textarea name="crg_author_notes" rows="3" style="width: 100%; margin-top: 4px;" placeholder="Add any notes or questions for the reviewer..."><?php echo esc_textarea($author_notes); ?></textarea>
            </div>
            
            <div style="margin-bottom: 16px;">
                <button type="button" id="crg-submit-review" class="button button-primary" style="width: 100%;">
                    📋 Submit for Review
                </button>
            </div>
            <?php endif; ?>

            <?php if ($submitted_date): ?>
            <div style="margin-bottom: 12px; font-size: 13px; color: #666;">
                <strong>Submitted:</strong> <?php echo date('M j, Y g:i A', strtotime($submitted_date)); ?>
            </div>
            <?php endif; ?>

            <?php if ($review_notes && $status !== 'draft'): ?>
            <div style="margin-bottom: 16px;">
                <strong>Review Notes:</strong>
                <div style="padding: 8px; background: #f0f9ff; border-left: 4px solid #1565D8; border-radius: 4px; font-size: 13px;">
                    <?php echo nl2br(esc_html($review_notes)); ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($status === 'needs_revision'): ?>
            <div style="margin-bottom: 16px;">
                <button type="button" id="crg-resubmit-review" class="button button-secondary" style="width: 100%;">
                    🔄 Resubmit for Review
                </button>
            </div>
            <?php endif; ?>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#crg-submit-review, #crg-resubmit-review').on('click', function() {
                $('input[name="crg_review_status"]').remove();
                $('<input>').attr({
                    type: 'hidden',
                    name: 'crg_review_status',
                    value: 'submitted'
                }).appendTo('form#post');
                
                $('input[name="crg_submitted_date"]').remove();
                $('<input>').attr({
                    type: 'hidden',
                    name: 'crg_submitted_date',
                    value: new Date().toISOString()
                }).appendTo('form#post');
                
                $('#publish').click();
            });
        });
        </script>
        <?php
    }

    public static function save_meta( $post_id ) {
        if ( ! isset($_POST['crg_nonce']) || ! wp_verify_nonce($_POST['crg_nonce'], 'crg_save_'.$post_id) ) return;
        
        // Handle new screenshots array format
        if (isset($_POST['crg_screenshots']) && is_array($_POST['crg_screenshots'])) {
            $screenshots = [];
            foreach ($_POST['crg_screenshots'] as $screenshot) {
                if (!empty($screenshot['url'])) {
                    $screenshots[] = [
                        'url' => esc_url_raw($screenshot['url']),
                        'title' => sanitize_text_field($screenshot['title']),
                        'type' => sanitize_text_field($screenshot['type'])
                    ];
                }
            }
            update_post_meta($post_id, 'crg_screenshots', $screenshots);
        }
        
        // Regular fields
        $fields = [
            'crg_client_name','crg_site_url','crg_report_date','crg_request_link',
            'crg_ss_before','crg_ss_after','crg_infection_summary',
            'crg_summary_critical','crg_summary_medium','crg_summary_low',
            'crg_highlights',
            'crg_reinstall_list','crg_replace_list',
            'crg_ssl','crg_cloudflare','crg_check_admins','crg_reset_passwords','crg_mask_login',
            'crg_verification_summary','crg_documentation_links','crg_pending_actions',
            'crg_seo_note','crg_protect_note'
        ];
        foreach ($fields as $f) {
            if (isset($_POST[$f])) {
                if (in_array($f, ['crg_highlights','crg_reinstall_list','crg_replace_list','crg_infection_summary','crg_verification_summary','crg_documentation_links','crg_pending_actions','crg_seo_note','crg_protect_note'])) {
                    update_post_meta($post_id, $f, wp_kses_post($_POST[$f]));
                } else {
                    update_post_meta($post_id, $f, sanitize_text_field($_POST[$f]));
                }
            }
        }
        
        // Review workflow fields
        $review_fields = ['crg_review_status', 'crg_author_notes', 'crg_review_notes', 'crg_submitted_date', 'crg_reviewed_date'];
        foreach ($review_fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_text_field($_POST[$field]));
            }
        }
        
        // Set reviewed date when status changes to reviewed/approved
        if (isset($_POST['crg_review_status']) && in_array($_POST['crg_review_status'], ['reviewed', 'approved'])) {
            $current_status = get_post_meta($post_id, 'crg_review_status', true);
            if ($current_status !== $_POST['crg_review_status']) {
                update_post_meta($post_id, 'crg_reviewed_date', current_time('mysql'));
            }
        }
        
        // Defender configuration checkboxes
        $defender_configs = [
            'defender_recommendations', 'malware_scanning', 'audit_logging', 'firewall_antibot',
            'firewall_404', 'user_agent_banning', 'user_agent_bot_trap', 'user_agent_empty_headers',
            'user_agent_script_presets', 'two_factor_auth', 'mask_login', 'security_headers',
            'pwned_passwords', 'strong_passwords', 'password_reset', 'session_protection',
            'google_recaptcha', 'ip_detection'
        ];
        
        foreach ($defender_configs as $config) {
            $status_field = 'crg_defender_' . $config;
            $notes_field = 'crg_defender_' . $config . '_notes';
            
            if (isset($_POST[$status_field])) {
                update_post_meta($post_id, $status_field, sanitize_text_field($_POST[$status_field]));
            }
            if (isset($_POST[$notes_field])) {
                update_post_meta($post_id, $notes_field, sanitize_text_field($_POST[$notes_field]));
            }
        }
    }
}
