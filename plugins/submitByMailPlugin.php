<?php

/**
 * submitByMail plugin version 1.0a1
 * 
 * This plugin allows messages to be submitted to Phplist mailing lists by email.
 * 
 *
 * For more information about how to use this plugin, see
 * http://resources.phplist.com/plugins/.
 * 
 */

/**
 * Registers the plugin with phplist
 * 
 * @category  phplist
 * @package   conditionalPlaceholderPlugin
 */

class submitByMailPlugin extends phplistPlugin
{
    /*
     *  Inherited variables
     */
    public $name = 'Submit by Mail Plugin';
    public $version = '1.0a1';
    public $enabled = false;
    public $authors = 'Arnold Lesikar';
    public $description = 'Allows messages to be submitted to mailing lists by email';
}
?>