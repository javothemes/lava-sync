<?php
namespace WPSynchro\Pages;

/**
 * Class for handling what to show when clicking on support in the menu in wp-admin
 *
 * @since 1.0.3
 */
class AdminSupport
{

    private $show_delete_settings_notice = false;

    /**
     *  Called from WP menu to show support
     *  @since 1.0.3
     */
    public static function render()
    {
        $instance = new self;
        // Handle post
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $instance->handlePOST();
        }
        $instance->handleGET();
    }

    /**
     *  Handle the update of data from support screen 
     *  @since 1.0.3
     */
    private function handlePOST()
    {
        // Check if it is delete settings
        if (isset($_POST['deletesettings']) && $_POST['deletesettings'] == 1) {
            global $wpsynchro_container;
            $common = $wpsynchro_container->get("class.CommonFunctions");
            $common->cleanUpPluginInstallation();
            $this->show_delete_settings_notice = true;
            return;
        }
    }

    /**
     *  Show WP Synchro support screen
     *  @since 1.0.3
     */
    private function handleGET()
    {
        global $wpsynchro_container;
        $debug_obj = $wpsynchro_container->get("class.DebugInformation");
        $debug_json = $debug_obj->getJSONDebugInformation();
      
        if (\WPSynchro\CommonFunctions::isPremiumVersion()) {
            // Licensing
            $licensing = $wpsynchro_container->get("class.Licensing");
        }

        ?>
        <div id="wpsynchro-support" class="wrap">
            <h2>WP Synchro <?= WPSYNCHRO_VERSION ?> <?php echo ( \WPSynchro\CommonFunctions::isPremiumVersion() ? 'PRO' : 'FREE' ); ?> - <?php _e('Support', 'moon114textdomain'); ?></h2>

            <?php
            if ($this->show_delete_settings_notice) {

                ?>
                <div class="notice notice-success">
                    <p><?php _e('WP Synchro data clean up completed - It is nice and clean now', 'moon114textdomain'); ?></p>
                </div>
                <?php
            }

            ?>

            <p><?php _e('Here is how you get help on a support issue for WP Synchro.', 'moon114textdomain'); ?></p>
            <div class="sectionheader"><span class="dashicons dashicons-lightbulb"></span> <?php _e('Getting support', 'moon114textdomain'); ?></div>
            <?php
            if (\WPSynchro\CommonFunctions::isPremiumVersion() && $licensing->verifyLicense()) {

                ?>
                <p><?php _e('You are on the PRO version with a validated license, so you have access to priority email support.', 'moon114textdomain'); ?></p>
                <p><?php _e('Contact us on', 'moon114textdomain'); ?> <a href="mailto:support@moon114.com">support@moon114.com</a>.</p>
                <p><?php _e('Be sure to include relevant information, such as:', 'moon114textdomain'); ?></p>

                <ul>
                    <li> - <?php _e('Description of problem(s)', 'moon114textdomain'); ?></li>
                    <li> - <?php _e('Screenshot of problem(s)', 'moon114textdomain'); ?></li>
                    <li> - <?php _e('Result of Health check just below', 'moon114textdomain'); ?></li>
                    <li> - <?php _e('Log file from synchronization (found in menu "Logs") with debug enabled (found in menu "Setup")', 'moon114textdomain'); ?></li>                    
                </ul>
                <p><?php _e('We will then get back to you as soon as we have investigated and we will ask for further information if needed.', 'moon114textdomain'); ?></p>

                <?php
            } else {

                ?>
                <p><?php _e('You are using the free version of WP Synchro, which we also provide email support for.', 'moon114textdomain'); ?></p>
                <p><?php _e('Users on the PRO version have priority support, so free version support requests can take more time depending on support load.<br>Check out <a href="https://moon114.com" target="_blank">https://moon114.com</a> on how to get the PRO version. The PRO version also contains more useful features, such as synchronizing files.', 'moon114textdomain'); ?></p>
                <p><?php _e('If you just have a bug report, security issue or a good idea for WP Synchro, we would still like to hear from you.', 'moon114textdomain'); ?></p>
                <p><?php _e('Contact us on', 'moon114textdomain'); ?> <a href="mailto:support@moon114.com">support@moon114.com</a>.</p>
                <p><?php _e('Be sure to include relevant information, such as:', 'moon114textdomain'); ?></p>

                <ul>
                    <li> - <?php _e('Description of problem(s)', 'moon114textdomain'); ?></li>
                    <li> - <?php _e('Screenshot of problem(s)', 'moon114textdomain'); ?></li>
                    <li> - <?php _e('Result of Health check just below', 'moon114textdomain'); ?></li>
                    <li> - <?php _e('Log file from synchronization (found in menu "Logs")', 'moon114textdomain'); ?></li>                    
                </ul>
                <?php
            }

            ?>              

            <div class="sectionheader"><span class="dashicons dashicons-awards"></span> <?php _e('Health check', 'moon114textdomain'); ?></div>

            <healthcheck showinline></healthcheck>

            <div class="sectionheader"><span class="dashicons dashicons-admin-generic"></span> <?php _e('Debug JSON information', 'moon114textdomain'); ?></div>
            <p><?php _e('Contains debug information about the installation, such as configuration and file locations. No personal information is included.', 'moon114textdomain'); ?></p>
            <textarea class="debugjson"><?php echo $debug_json; ?></textarea>

            <div class="sectionheader"><span class="dashicons dashicons-no"></span> <?php _e('Delete WP Synchro data', 'moon114textdomain'); ?></div>
            <p><?php _e('Delete all data related to WP Synchro, in database and files. Can be used to clean up after WP Synchro if needed.', 'moon114textdomain'); ?><br><?php _e('Does not reset access key and license key setup, but removes data like log files and installations.', 'moon114textdomain'); ?></p>

            <form  method="POST" >
                <input type="hidden" name="deletesettings" value="1" />
                <p><button type="submit" class="deletesettingsbutton" /><?php _e('Delete all WP Synchro data', 'moon114textdomain'); ?></button></p>

            </form>


        </div>
        <?php
    }
}
