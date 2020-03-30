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
            <h2>Lava Synchro <?= WPSYNCHRO_VERSION ?> ! - <?php _e('Support', 'wpsynchro'); ?></h2>

            <?php
            if ($this->show_delete_settings_notice) {

                ?>
                <div class="notice notice-success">
                    <p><?php _e('WP Synchro data clean up completed - It is nice and clean now', 'wpsynchro'); ?></p>
                </div>
                <?php
            }

            ?>

            <div class="sectionheader"><span class="dashicons dashicons-awards"></span> <?php _e('Health check', 'wpsynchro'); ?></div>

            <healthcheck showinline></healthcheck>

            <div class="sectionheader"><span class="dashicons dashicons-admin-generic"></span> <?php _e('Debug JSON information', 'wpsynchro'); ?></div>
            <p><?php _e('Contains debug information about the installation, such as configuration and file locations. No personal information is included.', 'wpsynchro'); ?></p>
            <textarea class="debugjson"><?php echo $debug_json; ?></textarea>

            <div class="sectionheader"><span class="dashicons dashicons-no"></span> <?php _e('Delete Synchro data', 'wpsynchro'); ?></div>
            <p><?php _e('Delete all data related to Synchro, in database and files. Can be used to clean up after Synchro if needed.', 'wpsynchro'); ?><br><?php _e('Removes data like log files and installations.', 'wpsynchro'); ?></p>

            <form  method="POST" >
                <input type="hidden" name="deletesettings" value="1" />
                <p><button type="submit" class="deletesettingsbutton" /><?php _e('Delete all Lava Synchro data', 'wpsynchro'); ?></button></p>

            </form>


        </div>
        <?php
    }
}
