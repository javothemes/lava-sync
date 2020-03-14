<?php
namespace WPSynchro\Files;

/**
 * Class for populating section file lists
 * @since 1.0.3
 */
class PopulateListHandler
{

    // Data objects
    public $job = null;
    public $installation = null;
    public $sync_list = null;
    public $timer = null;

    /**
     *  Constructor
     *  @since 1.0.3
     */
    public function __construct()
    {
    }

    /**
     *  Initialize class
     *  @since 1.0.3
     */
    public function init(\WPSynchro\Files\SyncList &$sync_list, \WPSynchro\Installation &$installation, \WPSynchro\Job &$job)
    {
        $this->sync_list = $sync_list;
        $this->installation = $installation;
        $this->job = $job;
    }

    /**
     * Populate File List
     * @since 1.0.3
     */
    public function populateFilelist()
    {

        // Timer
        global $wpsynchro_container;
        $this->timer = $wpsynchro_container->get("class.SyncTimerList");
        // Logger
        $logger = $wpsynchro_container->get("class.Logger");

        /**
         *  Validate file sections (for overlapping sections etc.)
         */
        if (!$this->job->files_population_sections_validated) {
            $this->job->files_population_sections_validated = true;
            if (!$this->validateFileSections()) {
                return;
            }
        }

        /**
         *  Populate from source
         */
        if (!$this->job->files_population_source) {
            foreach ($this->job->files_sections as $key => &$section) {
                if (!$section->source_files_population_complete) {
                    $this->handleSectionPopulation("source", $section);
                    return;
                }
            }
            $logger->log("DEBUG", "Files population from source completed");
            $this->job->files_population_source = true;

            // After target population is done, request full timeframe for next task
            $this->job->request_full_timeframe = true;
            
            return;
        }


        /**
         *  Populate from target
         */
        if (!$this->job->files_population_target) {
            foreach ($this->job->files_sections as $key => &$section) {
                if (!$section->target_files_population_complete) {
                    $this->handleSectionPopulation("target", $section);
                    return;
                }
            }
            $logger->log("DEBUG", "Files population from target completed");
            $this->job->files_population_target = true;

            // After target population is done, request full timeframe for next task
            $this->job->request_full_timeframe = true;

            return;
        }
    }

    /**
     *  Validate file sections before starting population
     *  @since 1.2.0
     */
    public function validateFileSections()
    {
        global $wpsynchro_container;
        $logger = $wpsynchro_container->get("class.Logger");

        $valid = true;

        /**
         *  Check if there is overlapping full paths
         */
        $fullpath_sections = array();
        foreach ($this->job->files_sections as $key => $section) {
            foreach ($section->temp_locations_in_basepath as $basepath => $notused) {
                $fullpath_sections[] = trailingslashit(trailingslashit($section->source_basepath) . trim($basepath, "/"));
            }
        }

        foreach ($fullpath_sections as $fullpath1) {
            foreach ($fullpath_sections as $fullpath2) {
                if (substr($fullpath1, 0, strlen($fullpath2)) === $fullpath2 && $fullpath1 != $fullpath2) {
                    $errormsg = sprintf(__("Found overlapping filepaths to synchronize: %s and %s. Please remove one of them before starting again.", "wpsynchro"), $fullpath2, $fullpath1);
                    $this->job->errors[] = $errormsg;
                    $logger->log("CRITICAL", $errormsg);
                    $valid = false;
                    break;
                }
            }
        }

        return $valid;
    }

    /**
     *  Handle the population of a section with a type, that can be source or target
     *  @since 1.2.0
     */
    public function handleSectionPopulation($type, &$section)
    {
        global $wpsynchro_container;
        $logger = $wpsynchro_container->get("class.Logger");

        // Check we have enough time (REST service check for 4 seconds or more, so we just check for 5)
        if ($this->timer->getRemainingSyncTime() < 5) {
            $this->job->request_full_timeframe = true;
            $logger->log("DEBUG","Breaking out of file population and requesting full timeframe, with remaining time: " . $this->timer->getRemainingSyncTime());
            return;
        }

        $get_file_data_timer = $this->timer->startTimer("filessync", "population", "servicecall");
        $restbody = $this->getFileDataFromSource($type, $section);
        $logger->log("INFO", sprintf("Got response from remote file population in %f seconds", $this->timer->getElapsedTimeToNow($get_file_data_timer)));

        // Check if we get a proper response back
        if ($restbody !== false) {
            // Set files found
            if ($type == "source") {
                $section->files_population_source_count = $restbody->state->files_found;
            } else {
                $section->files_population_target_count = $restbody->state->files_found;
            }

            // Check if REST service returned a complete state or is still populating data
            if ($restbody->state->state == "completed") {
                if (isset($restbody->filelist)) {
                    // If set is empty, just set completed on this section
                    if (count($restbody->filelist) == 0) {
                        if ($type == "source") {
                            $section->source_files_population_complete = true;
                        } else {
                            $section->target_files_population_complete = true;
                        }
                    }
                    // If not empty, handle the file list and add to database
                    $sql_insert_result = $this->sync_list->addUpdateFilelistFromPopulation($type, $section->id, $restbody->filelist);
                    if ($sql_insert_result) {
                        $logger->log("INFO", "Populated section " . $section->name . " on " . $type . " with " . count($restbody->filelist) . " files");
                    }
                }
            }
        }
    }

    /**
     *  Get file list data from source installation
     *  @since 1.0.3
     */
    public function getFileDataFromSource($type, &$section)
    {
        global $wpsynchro_container;

        // Determine URL and key
        if ($type == "source") {
            $url = $this->job->from_rest_base_url . "wpsynchro/v1/populatefilelist/";
        } else {
            $url = $this->job->to_rest_base_url . "wpsynchro/v1/populatefilelist/";
        }

        // Gather exclusions
        $exclusions = array();
        if (strlen(trim($this->installation->files_exclude_files_match)) > 0) {
            $exclusions = array_merge($exclusions, explode(",", $this->installation->files_exclude_files_match));
        }
        if (strlen(trim($section->exclusions)) > 0) {
            $exclusions = array_merge($exclusions, explode(",", $section->exclusions));
        }
        $exclusions = array_merge($exclusions, $this->getFilePopulationExclusions($type)); // To prevent moving WP Synchro plugin, uploads folder and the likes
        // Do some fixy fixy magic on the paths
        array_walk($exclusions, function (&$value, &$key) {
            global $wpsynchro_container;
            $common = $wpsynchro_container->get("class.CommonFunctions");
            $value = trim($value, " ");
            $value = $common->fixPath($value);
        });

        // Genereate request
        $body = new \stdClass();
        $body->exclusions = $exclusions;
        $body->section = $section;
        $body->type = $type;
        $body->allotted_time = $this->timer->getRemainingSyncTime();
        $body->requestid = $this->job->id;

        // Get remote transfer object
        $remotetransport = $wpsynchro_container->get('class.RemoteTransfer');
        $remotetransport->init();
        $remotetransport->setUrl($url);
        $remotetransport->setDataObject($body);
        $remote_filedata_result = $remotetransport->remotePOST();

        if ($remote_filedata_result->isSuccess()) {
            $result_body = $remote_filedata_result->getBody();
            if (is_object($result_body)) {
                return $result_body;
            } else {
                $this->job->errors[] = __("Data returned during file population is invalid, which means we can not continue the synchronization.", "wpsynchro");
                return false;
            }
        } else {
            $this->job->errors[] = __("File population failed, which means we can not continue the synchronization.", "wpsynchro");
            return false;
        }
    }

    /**
     * Get file exclusion paths
     * @since 1.2.0
     */
    public function getFilePopulationExclusions($type)
    {
        $exclusion_arr = array();

        // Add wp-admin, wp-includes
        if ($type == "source") {
            $files_wp_dir_b1 = basename($this->job->from_files_wp_dir);
        } else {
            $files_wp_dir_b1 = basename($this->job->to_files_wp_dir);
        }

        $exclusion_arr[] = $files_wp_dir_b1 . "/wp-admin";
        $exclusion_arr[] = $files_wp_dir_b1 . "/wp-includes";

        // Add plugin location
        if ($type == "source") {
            $plugin_basename = basename($this->job->from_files_plugins_dir);
            $wpcontent_basename = basename($this->job->from_files_wp_content_dir);
        } else {
            $plugin_basename = basename($this->job->to_files_plugins_dir);
            $wpcontent_basename = basename($this->job->to_files_wp_content_dir);
        }

        $exclusion_arr[] = $wpcontent_basename . "/" . $plugin_basename . "/lava-sync";

        // Add uploads location
        if ($type == "source") {
            $uploads_basename = basename($this->job->from_files_uploads_dir);
        } else {
            $uploads_basename = basename($this->job->to_files_uploads_dir);
        }

        $exclusion_arr[] = $wpcontent_basename . "/" . $uploads_basename . "/lava-sync";

        // Add .htaccess in web root, to prevent troubles with https redirects and other stuff
        if ($type == "source") {
            $exclusion_arr[] = basename($this->job->from_files_home_dir) . "/.htaccess";
        } else {
            $exclusion_arr[] = basename($this->job->to_files_home_dir) . "/.htaccess";
        }

        // If multisite and subsite, dont synchronize the uploads folder of other sites
        /*
          if (is_multisite()) {
          error_log(print_r($exclusion_arr, true));
          $current_blog_id = get_current_blog_id();
          $main_blog_id = get_network()->site_id;
          // If not main site (aka migrating everything)
          if ($current_blog_id != $main_blog_id) {
          $blog_ids = get_sites();
          foreach ($blog_ids as $b) {
          if ($b->blog_id != $main_blog_id || $b->blog_id != $current_blog_id) {
          continue;
          }
          $exclusion_arr[] = $uploads_basename . "/sites/" . $b->blog_id;
          }
          }
          }
         */




        return $exclusion_arr;
    }
}
