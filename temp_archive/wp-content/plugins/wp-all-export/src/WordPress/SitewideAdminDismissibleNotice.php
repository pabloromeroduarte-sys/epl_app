<?php

namespace Wpae\WordPress;


class SitewideAdminDismissibleNotice extends AdminNotice
{
    private $noticeId;

    public function __construct($message, $noticeId)
    {
        parent::__construct($message);
        $this->noticeId = $noticeId;
    }

    public function showNotice()
    {

	    $wp_all_export_ajax_nonce = wp_create_nonce("wp_all_export_secure");

        ?>
        <script type="text/javascript">
            jQuery(document).ready(function(){
                jQuery('.wpae-general-notice-dismiss').on('click', function(){

                    var $parent = jQuery(this).parent();
                    var noticeId = jQuery(this).attr('data-noticeId');
                    var wp_all_export_security = wp_all_export_security || '<?php echo esc_js($wp_all_export_ajax_nonce); ?>';

                    var request = {
                        action: 'dismiss_warnings',
                        data: {
                            notice_id: noticeId
                        },
                        security: wp_all_export_security
                    };

                    $parent.slideUp();

                    jQuery.ajax({
                        type: 'POST',
                        url: ajaxurl,
                        data: request,
                        success: function(response) {},
                        dataType: "json"
                    });

                });
            });
        </script>
        <div class="<?php echo esc_attr($this->getType());?>" style="position: relative;"><p>
                <?php
                    echo wp_kses_post($this->message);
                ?>
            </p>
            <button class="notice-dismiss wpae-general-notice-dismiss" type="button" data-noticeId="<?php echo esc_attr($this->noticeId); ?>"><span class="screen-reader-text">Dismiss this notice.</span></button>
        </div>
        <?php
    }

    public function render()
    {
        add_action('admin_notices', array($this, 'showNotice'));
    }

    public function getType()
    {
        return 'error';
    }

    public function isDismissed()
    {
        $optionName = 'wpae_dismiss_warnings_'.$this->noticeId;
        $optionValue = get_option($optionName, false);

        return $optionValue;
    }
}